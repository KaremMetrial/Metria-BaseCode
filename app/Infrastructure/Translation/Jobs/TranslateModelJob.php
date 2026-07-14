<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation\Jobs;

use App\Infrastructure\Translation\Events\TranslationCompleted;
use App\Infrastructure\Translation\Events\TranslationFailed;
use App\Infrastructure\Translation\Events\TranslationRequested;
use App\Infrastructure\Translation\Facades\Translation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class TranslateModelJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * The number of seconds the unique lock should be kept.
     */
    public int $uniqueFor = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly string|int $modelId,
        public readonly array $fields,
        public readonly string $sourceLocale,
        public readonly string $toLocale
    ) {
        $this->queue = config('translation.queue', 'translations');
    }

    /**
     * Get the unique ID for the job to prevent duplicate enqueues.
     */
    public function uniqueId(): string
    {
        $fieldsKey = implode(',', $this->fields);

        return md5("{$this->modelClass}:{$this->modelId}:{$fieldsKey}:{$this->sourceLocale}:{$this->toLocale}");
    }

    /**
     * Get the backoff intervals for retries.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600]; // 1m, 5m, 15m, 30m, 60m
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new \Illuminate\Queue\Middleware\RateLimited('translations')];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (! class_exists($this->modelClass)) {
            return;
        }

        /** @var Model|null $model */
        $model = $this->modelClass::find($this->modelId);
        if (! $model) {
            return;
        }

        // Only translate fields that are currently missing a translation in the target locale.
        // We will never overwrite existing, non-empty, human-entered translations.
        $valuesToTranslate = [];
        foreach ($this->fields as $field) {
            // Check if the target translation is already set
            $existingTarget = method_exists($model, 'getTranslation')
                ? $model->getTranslation($field, $this->toLocale, false)
                : null;

            if (empty($existingTarget)) {
                $sourceText = method_exists($model, 'getTranslation')
                    ? $model->getTranslation($field, $this->sourceLocale, false)
                    : null;

                if (! empty($sourceText)) {
                    $valuesToTranslate[$field] = $sourceText;
                }
            }
        }

        if (empty($valuesToTranslate)) {
            return;
        }

        $correlationId = (string) Str::uuid();
        $startTime = microtime(true);
        $providerName = config('translation.default', 'gemini');
        $promptVersion = config("translation.providers.{$providerName}.prompt_version", 'v1');

        \Illuminate\Support\Facades\DB::table('translation_jobs')
            ->where('model_type', $this->modelClass)
            ->where('model_id', (int) $this->modelId)
            ->where('locale', $this->toLocale)
            ->where('status', 'pending')
            ->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

        event(new TranslationRequested(
            $providerName,
            $promptVersion,
            $this->sourceLocale,
            $this->toLocale,
            $valuesToTranslate,
            $correlationId
        ));

        try {
            // Execute the translation via the fluent facade API
            $translatedValues = Translation::from($this->sourceLocale)
                ->to($this->toLocale)
                ->translate($valuesToTranslate);

            // Populate translations back to the model
            foreach ($translatedValues as $field => $translatedValue) {
                if (method_exists($model, 'setTranslation')) {
                    $model->setTranslation($field, $this->toLocale, $translatedValue);
                }
            }

            // Save the model quietly to avoid triggering saved event loops
            if (method_exists($model, 'saveQuietly')) {
                $model->saveQuietly();
            } else {
                $model->save();
            }

            $durationMs = (microtime(true) - $startTime) * 1000;

            \Illuminate\Support\Facades\DB::table('translation_jobs')
                ->where('model_type', $this->modelClass)
                ->where('model_id', (int) $this->modelId)
                ->where('locale', $this->toLocale)
                ->where('status', 'processing')
                ->update([
                    'status' => 'completed',
                    'finished_at' => now(),
                ]);

            event(new TranslationCompleted(
                $providerName,
                $promptVersion,
                $this->sourceLocale,
                $this->toLocale,
                $durationMs,
                $this->attempts(),
                false, // Cache hits are handled inside TranslationManager, so this API call was a cache miss
                $correlationId
            ));

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::table('translation_jobs')
                ->where('model_type', $this->modelClass)
                ->where('model_id', (int) $this->modelId)
                ->where('locale', $this->toLocale)
                ->whereIn('status', ['pending', 'processing'])
                ->update([
                    'status' => 'failed',
                    'error' => mb_substr($e->getMessage(), 0, 500),
                ]);

            event(new TranslationFailed(
                $providerName,
                $promptVersion,
                $this->sourceLocale,
                $this->toLocale,
                $this->attempts(),
                $e->getMessage(),
                $correlationId
            ));

            throw $e;
        }
    }
}

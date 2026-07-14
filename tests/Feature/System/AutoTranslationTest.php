<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use App\Core\Traits\HasTranslations;
use App\Infrastructure\Translation\Enums\ProviderState;
use App\Infrastructure\Translation\Events\TranslationCompleted;
use App\Infrastructure\Translation\Events\TranslationRequested;
use App\Infrastructure\Translation\Exceptions\ProviderUnavailableException;
use App\Infrastructure\Translation\Facades\Translation;
use App\Infrastructure\Translation\Jobs\TranslateModelJob;
use App\Infrastructure\Translation\Traits\AutoTranslates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Simple, self-contained test model to isolate from domain changes
class TestTranslatableModel extends Model
{
    use AutoTranslates, HasTranslations;

    public bool $disableAutoTranslation = false;

    protected $table = 'test_translatable_models';

    protected $fillable = ['name', 'description'];

    protected array $translatable = ['name', 'description'];
}

class AutoTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary sqlite table in-memory for testing
        Schema::create('test_translatable_models', function ($table) {
            $table->id();
            $table->json('name')->nullable();
            $table->json('description')->nullable();
            $table->timestamps();
        });

        // Set configuration variables explicitly for the test
        config([
            'translation.enabled' => true,
            'translation.default' => 'gemini',
            'translation.fallbacks' => ['logging', 'null'],
            'translation.queue' => 'translations',
            'localization.supported' => ['en', 'ar'],
            'localization.fallback' => 'en',
            'translation.circuit_breaker.failure_threshold' => 3,
            'translation.circuit_breaker.cooldown_seconds' => 5,
            'translation.providers.gemini.key' => 'test-api-key',
            'translation.providers.gemini.model' => 'gemini-1.5-flash',
            'translation.providers.gemini.prompt_version' => 'v1',
        ]);
    }

    public function test_auto_translates_saved_event_queues_job(): void
    {
        Queue::fake();

        $model = new TestTranslatableModel;
        $model->name = 'Cairo'; // Defaults to app locale (en)
        $model->save();

        Queue::assertPushed(TranslateModelJob::class, function ($job) use ($model) {
            return $job->modelClass === TestTranslatableModel::class
                && $job->modelId === $model->id
                && $job->fields === ['name']
                && $job->sourceLocale === 'en'
                && $job->toLocale === 'ar';
        });
    }

    public function test_auto_translates_ignores_non_dirty_saves(): void
    {
        $model = new TestTranslatableModel;
        $model->name = 'Cairo';
        $model->save();

        Queue::fake();

        // Re-saving without changes shouldn't enqueue anything
        $model->save();
        Queue::assertNothingPushed();
    }

    public function test_auto_translates_ignores_when_all_translations_exist(): void
    {
        Queue::fake();

        $model = new TestTranslatableModel;
        $model->setTranslation('name', 'en', 'Cairo');
        $model->setTranslation('name', 'ar', 'القاهرة');
        $model->save();

        Queue::assertNothingPushed();
    }

    public function test_explicit_queue_translations_method(): void
    {
        Queue::fake();

        // Save with disabled auto translation first
        $model = new TestTranslatableModel;
        $model->disableAutoTranslation = true;
        $model->name = 'Alexandria';
        $model->save();

        Queue::assertNothingPushed();

        // Call explicit trigger
        $model->queueTranslations();

        Queue::assertPushed(TranslateModelJob::class, function ($job) use ($model) {
            return $job->modelClass === TestTranslatableModel::class
                && $job->modelId === $model->id
                && $job->fields === ['name', 'description']
                && $job->sourceLocale === 'en'
                && $job->toLocale === 'ar';
        });
    }

    public function test_unicode_and_whitespace_normalization_for_cache(): void
    {
        $values = ['name' => "Cairo \n "];
        $targetValues = ['name' => 'القاهرة'];

        // Mock Http to simulate translation result
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode($targetValues)],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        // Translate the spaced string
        $translated1 = Translation::driver('gemini')->from('en')->to('ar')->translate($values);
        $this->assertEquals($targetValues, $translated1);

        // Try the same string but cleaned ("Cairo"), it should be a cache hit (meaning HTTP isn't called again)
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([], 500),
        ]);

        $translated2 = Translation::driver('gemini')->from('en')->to('ar')->translate(['name' => 'Cairo']);
        $this->assertEquals($targetValues, $translated2);
    }

    public function test_circuit_breaker_transitions(): void
    {
        $provider = Translation::driver('gemini');
        $this->assertEquals(ProviderState::Healthy, $provider->health()->state);

        // Fake 3 failures (threshold)
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([], 500),
        ]);

        for ($i = 0; $i < 3; $i++) {
            try {
                $provider->translate(['name' => 'Cairo'], 'en', 'ar');
            } catch (ProviderUnavailableException) {
                // Expected
            }
        }

        // Circuit breaker should transition to OPEN (Offline)
        $this->assertEquals(ProviderState::Offline, $provider->health()->state);

        // Ensure calls are blocked immediately without hitting HTTP client
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['candidates' => []]),
        ]);

        $this->expectException(ProviderUnavailableException::class);
        $provider->translate(['name' => 'Cairo'], 'en', 'ar');
    }

    public function test_circuit_breaker_cooldown_to_half_open(): void
    {
        $provider = Translation::driver('gemini');

        // Force open state via cache
        Cache::put("translation:cb:state:{$provider->name()}", 'open');
        Cache::put("translation:cb:open_until:{$provider->name()}", now()->subSecond()->timestamp);

        // Should transition to half_open after cooldown
        $this->assertEquals(ProviderState::Degraded, $provider->health()->state);
    }

    public function test_fluent_facade_and_manager_fallback(): void
    {
        // Force Gemini to throw retriable exception
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([], 500),
        ]);

        // Fallback is configured to 'logging', which prepends [ar] to the source string
        $translated = Translation::from('en')->to('ar')->translate(['name' => 'Cairo']);

        $this->assertEquals(['name' => '[ar] Cairo'], $translated);
    }

    public function test_job_uniqueness_lock_generation(): void
    {
        $job = new TranslateModelJob(TestTranslatableModel::class, 1, ['name'], 'en', 'ar');
        $expectedKey = md5(TestTranslatableModel::class.':1:name:en:ar');
        $this->assertEquals($expectedKey, $job->uniqueId());
    }

    public function test_end_to_end_job_processing_with_event_dispatch(): void
    {
        Event::fake();

        // Create a model in DB
        $model = new TestTranslatableModel;
        $model->disableAutoTranslation = true;
        $model->name = 'Giza';
        $model->save();

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode(['name' => 'الجيزة'])],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        // Execute the job directly
        $job = new TranslateModelJob(TestTranslatableModel::class, $model->id, ['name'], 'en', 'ar');
        $job->handle();

        // Refresh model and verify Arabic translation
        $model->refresh();
        $this->assertEquals('الجيزة', $model->getTranslation('name', 'ar', false));

        // Verify events
        Event::assertDispatched(TranslationRequested::class);
        Event::assertDispatched(TranslationCompleted::class);
    }
}

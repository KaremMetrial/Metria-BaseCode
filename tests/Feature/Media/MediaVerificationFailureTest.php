<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use Modules\Auth\Domain\Models\User;
use Modules\Media\Domain\Contracts\VirusScanner;
use Modules\Media\Domain\DTOs\VirusScanResult;
use Modules\Media\Domain\Enums\MediaStatus;
use Modules\Media\Domain\Enums\MediaType;
use Modules\Media\Domain\Models\Media;
use Modules\Media\Domain\Models\MediaBlob;
use Modules\Media\Infrastructure\Jobs\ProcessMediaVariants;
use Modules\Media\Infrastructure\Jobs\VerifyMediaUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Answers the open question from the code review: what happens to a media
 * upload if the virus scanner is unreachable? VerifyMediaUpload already
 * fails closed — the media never reaches Active — but that contract had no
 * dedicated test before this one.
 */
class MediaVerificationFailureTest extends TestCase
{
    use RefreshDatabase;

    private function mediaWithBlob(string $filename = 'photo.jpg'): Media
    {
        Storage::fake('public');
        Storage::disk('public')->put("blobs/{$filename}", 'file bytes');

        DB::table('tenants')->updateOrInsert(
            ['id' => 'org-1'],
            ['name' => 'Tenant org-1', 'slug' => 'tenant-org-1', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]
        );

        $user = User::factory()->create(['tenant_id' => 'org-1']);

        $blob = MediaBlob::create([
            'tenant_id' => 'org-1',
            'sha256' => hash('sha256', 'file bytes'),
            'disk' => 'public',
            'path' => "blobs/{$filename}",
            'filename' => $filename,
            'original_filename' => $filename,
            'mime_type' => 'image/jpeg',
            'size' => 10,
            'virus_status' => 'pending',
        ]);

        return Media::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'org-1',
            'media_blob_id' => $blob->id,
            'media_type' => MediaType::Image,
            'purpose' => 'avatar',
            'is_public' => true,
            // Matches MediaUploadService's real precondition: the upload
            // pipeline transitions to Verifying before dispatching
            // VerifyMediaUpload, since MediaStateMachine only allows
            // Verifying -> {Processing, Quarantined}.
            'status' => MediaStatus::Verifying,
            'created_by' => $user->id,
        ]);
    }

    /** A scanner double simulating an unreachable ClamAV daemon (connection refused, timeout, ...). */
    private function bindUnreachableScanner(): void
    {
        $this->app->instance(VirusScanner::class, new class implements VirusScanner
        {
            public function scan(string $filePath): VirusScanResult
            {
                throw new \RuntimeException('Connection to ClamAV daemon refused.');
            }
        });
    }

    public function test_media_never_reaches_active_while_the_scanner_is_unreachable(): void
    {
        $this->bindUnreachableScanner();
        $media = $this->mediaWithBlob();
        $job = new VerifyMediaUpload($media->id);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $job->handle(app(\Modules\Media\Infrastructure\Services\MediaVerificationService::class));
                $this->fail('Expected the scanner failure to propagate.');
            } catch (\RuntimeException) {
                // expected — the job re-throws so a real queue worker retries it
            }

            $media->refresh();

            if ($attempt < 3) {
                // Still mid-retry: not marked Failed yet, and critically,
                // never Active — an unreachable scanner must never be
                // treated as an implicit pass.
                $this->assertNotSame(MediaStatus::Active, $media->status);
                $this->assertNotSame(MediaStatus::Failed, $media->status);
            }
        }

        // After exhausting retries (tries = 3), the media is parked in
        // Failed — fail-closed — never silently promoted to Active.
        $this->assertSame(MediaStatus::Failed, $media->status);
        $this->assertSame(3, $media->retry_count);
        $this->assertStringContainsString('Connection to ClamAV daemon refused.', (string) $media->processing_error);
    }

    public function test_media_recovers_to_active_once_the_scanner_is_reachable_again(): void
    {
        // The variant-generation pipeline is a separate concern (real image
        // decoding) — fake it out so this test stays focused on the
        // scanner-recovery contract instead of needing a real image fixture.
        Queue::fake([ProcessMediaVariants::class]);

        $this->bindUnreachableScanner();
        $media = $this->mediaWithBlob();
        $job = new VerifyMediaUpload($media->id);

        try {
            $job->handle(app(\Modules\Media\Infrastructure\Services\MediaVerificationService::class));
        } catch (\RuntimeException) {
            // expected on the first, still-unreachable attempt
        }

        $media->refresh();
        $this->assertNotSame(MediaStatus::Failed, $media->status);

        // Scanner comes back online before retries are exhausted.
        $this->app->instance(VirusScanner::class, new class implements VirusScanner
        {
            public function scan(string $filePath): VirusScanResult
            {
                return new VirusScanResult(status: 'safe', engine: 'ClamAV', message: 'OK');
            }
        });

        $job->handle(app(\Modules\Media\Infrastructure\Services\MediaVerificationService::class));

        $this->assertSame(MediaStatus::Processing, $media->fresh()->status);
    }
}

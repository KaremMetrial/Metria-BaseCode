<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use Modules\Shared\Application\Exceptions\DomainException;
use Modules\Auth\Domain\Models\User;
use Modules\Media\Domain\Enums\MediaStatus;
use Modules\Media\Domain\Enums\MediaType;
use Modules\Media\Domain\Models\Media;
use Modules\Media\Infrastructure\Services\MediaStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The transition matrix in MediaStateMachine is the single source of truth
 * for which status changes are legal — this test exercises every edge the
 * matrix defines rather than just the happy path already covered end-to-end
 * in MediaUploadTest.
 */
class MediaStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private function media(MediaStatus $status): Media
    {
        DB::table('tenants')->updateOrInsert(
            ['id' => 'org-1'],
            ['name' => 'Tenant org-1', 'slug' => 'tenant-org-1', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]
        );

        $user = User::factory()->create(['tenant_id' => 'org-1']);

        return Media::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'org-1',
            'media_type' => MediaType::Image,
            'purpose' => 'avatar',
            'is_public' => true,
            'status' => $status,
            'created_by' => $user->id,
        ]);
    }

    public static function legalTransitions(): array
    {
        return [
            'pending -> uploading' => [MediaStatus::Pending, MediaStatus::Uploading],
            'pending -> uploaded' => [MediaStatus::Pending, MediaStatus::Uploaded],
            'pending -> failed' => [MediaStatus::Pending, MediaStatus::Failed],
            'pending -> deleted' => [MediaStatus::Pending, MediaStatus::Deleted],
            'uploading -> uploaded' => [MediaStatus::Uploading, MediaStatus::Uploaded],
            'uploaded -> verifying' => [MediaStatus::Uploaded, MediaStatus::Verifying],
            'verifying -> processing' => [MediaStatus::Verifying, MediaStatus::Processing],
            'verifying -> quarantined' => [MediaStatus::Verifying, MediaStatus::Quarantined],
            'processing -> active' => [MediaStatus::Processing, MediaStatus::Active],
            'processing -> quarantined' => [MediaStatus::Processing, MediaStatus::Quarantined],
            'active -> deleted' => [MediaStatus::Active, MediaStatus::Deleted],
            'quarantined -> active (admin restore)' => [MediaStatus::Quarantined, MediaStatus::Active],
            'failed -> pending (retry)' => [MediaStatus::Failed, MediaStatus::Pending],
        ];
    }

    /** @dataProvider legalTransitions */
    public function test_legal_transition_is_applied(MediaStatus $from, MediaStatus $to): void
    {
        $media = $this->media($from);

        (new MediaStateMachine)->transition($media, $to);

        $this->assertSame($to, $media->fresh()->status);
    }

    public static function illegalTransitions(): array
    {
        return [
            'deleted is terminal' => [MediaStatus::Deleted, MediaStatus::Active],
            'active cannot skip back to pending' => [MediaStatus::Active, MediaStatus::Pending],
            'active cannot re-enter verification' => [MediaStatus::Active, MediaStatus::Verifying],
            'pending cannot jump straight to active' => [MediaStatus::Pending, MediaStatus::Active],
            'uploading cannot jump to processing' => [MediaStatus::Uploading, MediaStatus::Processing],
            'quarantined cannot jump to processing' => [MediaStatus::Quarantined, MediaStatus::Processing],
        ];
    }

    /** @dataProvider illegalTransitions */
    public function test_illegal_transition_is_rejected(MediaStatus $from, MediaStatus $to): void
    {
        $media = $this->media($from);

        $this->expectException(DomainException::class);

        try {
            (new MediaStateMachine)->transition($media, $to);
        } finally {
            // The record must stay exactly where it was — a rejected
            // transition must never leave a media row in limbo.
            $this->assertSame($from, $media->fresh()->status);
        }
    }

    public function test_transitioning_to_the_current_status_is_a_no_op(): void
    {
        $media = $this->media(MediaStatus::Active);
        $activatedAt = $media->activated_at;

        (new MediaStateMachine)->transition($media, MediaStatus::Active);

        // Idempotent: no exception, and no timestamp churn from re-applying
        // the same status.
        $this->assertSame(MediaStatus::Active, $media->fresh()->status);
        $this->assertEquals($activatedAt, $media->fresh()->activated_at);
    }

    public function test_entering_active_stamps_activation_and_completion_timestamps(): void
    {
        $media = $this->media(MediaStatus::Processing);

        (new MediaStateMachine)->transition($media, MediaStatus::Active);
        $media->refresh();

        $this->assertNotNull($media->activated_at);
        $this->assertNotNull($media->processing_finished_at);
    }

    public function test_entering_quarantined_stamps_quarantined_at(): void
    {
        $media = $this->media(MediaStatus::Verifying);

        (new MediaStateMachine)->transition($media, MediaStatus::Quarantined);

        $this->assertNotNull($media->fresh()->quarantined_at);
    }
}

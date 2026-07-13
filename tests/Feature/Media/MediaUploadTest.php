<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Domain\Auth\Models\User;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\MediaVariantType;
use App\Domain\Media\Http\Resources\MediaResource;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaBlob;
use App\Domain\Media\Models\MediaVariant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable tenancy for testing tenant isolation & deduplication
        config(['tenancy.enabled' => true]);

        // Fake public and local disks
        Storage::fake('public');
        Storage::fake('local');
    }

    private function createTenantUser(string $tenantId): User
    {
        // Ensure the tenant exists
        DB::table('tenants')->updateOrInsert(
            ['id' => $tenantId],
            [
                'name' => "Tenant {$tenantId}",
                'slug' => "tenant-{$tenantId}",
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $user = User::factory()->create([
            'tenant_id' => $tenantId,
        ]);
        $this->seed(RolesAndPermissionsSeeder::class);
        setPermissionsTeamId($tenantId);
        $user->assignRole('customer');

        return $user;
    }

    public function test_initiate_upload_creates_pending_media_record(): void
    {
        $user = $this->createTenantUser('org-1');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/media/presign', [
            'filename' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 5000,
            'is_public' => true,
            'purpose' => 'avatar',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'media_id',
                    'upload_url',
                    'multipart',
                    'path',
                ],
            ]);

        $mediaId = $response->json('data.media_id');
        $this->assertDatabaseHas('media', [
            'id' => $mediaId,
            'tenant_id' => 'org-1',
            'media_type' => MediaType::Image->value,
            'purpose' => 'avatar',
            'status' => MediaStatus::Pending->value,
            'is_public' => true,
        ]);
    }

    public function test_initiate_upload_validates_file_constraints(): void
    {
        $user = $this->createTenantUser('org-1');
        Sanctum::actingAs($user);

        // Size exceeds max limit (500MB is default limit)
        $response = $this->postJson('/api/v1/media/presign', [
            'filename' => 'huge_movie.mp4',
            'mime_type' => 'video/mp4',
            'size' => 600 * 1024 * 1024, // 600MB
            'is_public' => false,
            'purpose' => 'attachment',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'file_too_large');

        // Disallowed MIME type
        $response = $this->postJson('/api/v1/media/presign', [
            'filename' => 'malicious_script.php',
            'mime_type' => 'application/x-httpd-php',
            'size' => 1024,
            'is_public' => false,
            'purpose' => 'attachment',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'disallowed_mime_type');
    }

    public function test_confirm_upload_verifies_file_and_executes_pipeline_to_active(): void
    {
        $user = $this->createTenantUser('org-1');
        Sanctum::actingAs($user);

        // 1. Initiate upload
        $presignResponse = $this->postJson('/api/v1/media/presign', [
            'filename' => 'clean_photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 5000,
            'is_public' => true,
            'purpose' => 'avatar',
        ])->json();

        $mediaId = $presignResponse['data']['media_id'];
        $path = $presignResponse['data']['path'];

        // Create fake file in public disk
        $fileContent = UploadedFile::fake()->image('clean_photo.jpg', 800, 600)->get();
        Storage::disk('public')->put($path, $fileContent);

        // Calculate actual checksum
        $checksum = hash('sha256', $fileContent);

        // 2. Confirm upload
        $response = $this->postJson("/api/v1/media/{$mediaId}/confirm", [
            'checksum' => $checksum,
        ]);

        $response->assertStatus(200);

        // Check Media is now Active
        $this->assertDatabaseHas('media', [
            'id' => $mediaId,
            'status' => MediaStatus::Active->value,
            'checksum' => $checksum,
        ]);

        // Check MediaBlob is created
        $this->assertDatabaseHas('media_blobs', [
            'tenant_id' => 'org-1',
            'sha256' => $checksum,
            'virus_status' => 'safe',
        ]);

        // Check MediaVariants were created
        $this->assertDatabaseHas('media_variants', [
            'media_id' => $mediaId,
            'variant' => MediaVariantType::Thumbnail->value,
        ]);
        $this->assertDatabaseHas('media_variants', [
            'media_id' => $mediaId,
            'variant' => MediaVariantType::Medium->value,
        ]);
    }

    public function test_confirm_upload_fails_on_checksum_mismatch(): void
    {
        $user = $this->createTenantUser('org-1');
        Sanctum::actingAs($user);

        $presignResponse = $this->postJson('/api/v1/media/presign', [
            'filename' => 'clean_photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 5000,
            'is_public' => true,
            'purpose' => 'avatar',
        ])->json();

        $mediaId = $presignResponse['data']['media_id'];
        $path = $presignResponse['data']['path'];

        $fileContent = 'some random image bytes';
        Storage::disk('public')->put($path, $fileContent);

        // Wrong checksum
        $wrongChecksum = hash('sha256', 'different content');

        $response = $this->postJson("/api/v1/media/{$mediaId}/confirm", [
            'checksum' => $wrongChecksum,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'checksum_mismatch');

        $this->assertDatabaseHas('media', [
            'id' => $mediaId,
            'status' => MediaStatus::Failed->value,
            'processing_error' => 'Checksum verification failed.',
        ]);
    }

    public function test_virus_detection_quarantines_media(): void
    {
        $user = $this->createTenantUser('org-1');
        Sanctum::actingAs($user);

        // Filename containing 'infected' simulates virus finding
        $presignResponse = $this->postJson('/api/v1/media/presign', [
            'filename' => 'infected_photo.infected',
            'mime_type' => 'image/jpeg',
            'size' => 5000,
            'is_public' => true,
            'purpose' => 'avatar',
        ])->json();

        $mediaId = $presignResponse['data']['media_id'];
        $path = $presignResponse['data']['path'];

        $fileContent = 'malware signature simulation';
        Storage::disk('public')->put($path, $fileContent);
        $checksum = hash('sha256', $fileContent);

        $response = $this->postJson("/api/v1/media/{$mediaId}/confirm", [
            'checksum' => $checksum,
        ]);

        $response->assertStatus(200);

        // Assert Media is Quarantined
        $this->assertDatabaseHas('media', [
            'id' => $mediaId,
            'status' => MediaStatus::Quarantined->value,
            'processing_error' => 'Virus scan failed: File is infected.',
        ]);

        // Assert MediaBlob is flagged as infected
        $this->assertDatabaseHas('media_blobs', [
            'tenant_id' => 'org-1',
            'sha256' => $checksum,
            'virus_status' => 'infected',
        ]);

        // Assert no variants were generated
        $this->assertEquals(0, MediaVariant::where('media_id', $mediaId)->count());
    }

    public function test_nsfw_content_moderation_quarantines_media(): void
    {
        $user = $this->createTenantUser('org-1');
        Sanctum::actingAs($user);

        // Filename containing 'nsfw' simulates adult content flag
        $presignResponse = $this->postJson('/api/v1/media/presign', [
            'filename' => 'nsfw_photo.nsfw',
            'mime_type' => 'image/jpeg',
            'size' => 5000,
            'is_public' => true,
            'purpose' => 'avatar',
        ])->json();

        $mediaId = $presignResponse['data']['media_id'];
        $path = $presignResponse['data']['path'];

        $fileContent = 'nsfw simulation bytes';
        Storage::disk('public')->put($path, $fileContent);
        $checksum = hash('sha256', $fileContent);

        $response = $this->postJson("/api/v1/media/{$mediaId}/confirm", [
            'checksum' => $checksum,
        ]);

        $response->assertStatus(200);

        // Assert Media is Quarantined
        $this->assertDatabaseHas('media', [
            'id' => $mediaId,
            'status' => MediaStatus::Quarantined->value,
            'moderation_status' => 'flagged',
            'processing_error' => 'Content moderation failed: NSFW/18+ content detected.',
        ]);
    }

    public function test_tenant_scoped_deduplication_reuses_clean_blob(): void
    {
        $userA = $this->createTenantUser('tenant-x');
        $userB = $this->createTenantUser('tenant-x');

        $fileContent = 'reusable clean content';
        $checksum = hash('sha256', $fileContent);

        // 1. User A uploads and activates
        Sanctum::actingAs($userA);
        $presignA = $this->postJson('/api/v1/media/presign', [
            'filename' => 'doc1.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'is_public' => false,
        ])->json();

        Storage::disk('local')->put($presignA['data']['path'], $fileContent);
        $this->postJson("/api/v1/media/{$presignA['data']['media_id']}/confirm", ['checksum' => $checksum])->assertOk();

        // 2. User B uploads same file content in same tenant
        Sanctum::actingAs($userB);
        $presignB = $this->postJson('/api/v1/media/presign', [
            'filename' => 'doc2.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'is_public' => false,
        ])->json();

        Storage::disk('local')->put($presignB['data']['path'], $fileContent);
        $this->postJson("/api/v1/media/{$presignB['data']['media_id']}/confirm", ['checksum' => $checksum])->assertOk();

        // Assert both logical Medias exist but point to the exact same MediaBlob ID
        $mediaA = Media::findOrFail($presignA['data']['media_id']);
        $mediaB = Media::findOrFail($presignB['data']['media_id']);

        $this->assertNotNull($mediaA->media_blob_id);
        $this->assertEquals($mediaA->media_blob_id, $mediaB->media_blob_id);

        // Assert only one MediaBlob record is in the database
        $this->assertEquals(1, MediaBlob::where('tenant_id', 'tenant-x')->where('sha256', $checksum)->count());

        // Deduplication: User B's physical file path should have been deleted (since it was a duplicate)
        Storage::disk('local')->assertMissing($presignB['data']['path']);
        Storage::disk('local')->assertExists($presignA['data']['path']);
    }

    public function test_cross_tenant_upload_does_not_deduplicate_physically(): void
    {
        $userTenant1 = $this->createTenantUser('tenant-1');
        $userTenant2 = $this->createTenantUser('tenant-2');

        $fileContent = 'shared content across tenants';
        $checksum = hash('sha256', $fileContent);

        // 1. Tenant 1 uploads and activates
        Sanctum::actingAs($userTenant1);
        $presign1 = $this->postJson('/api/v1/media/presign', [
            'filename' => 'doc.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'is_public' => false,
        ])->json();

        Storage::disk('local')->put($presign1['data']['path'], $fileContent);
        $this->postJson("/api/v1/media/{$presign1['data']['media_id']}/confirm", ['checksum' => $checksum])->assertOk();

        // 2. Tenant 2 uploads same content
        Sanctum::actingAs($userTenant2);
        $presign2 = $this->postJson('/api/v1/media/presign', [
            'filename' => 'doc.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'is_public' => false,
        ])->json();

        Storage::disk('local')->put($presign2['data']['path'], $fileContent);
        $this->postJson("/api/v1/media/{$presign2['data']['media_id']}/confirm", ['checksum' => $checksum])->assertOk();

        $media1 = Media::withoutGlobalScopes()->findOrFail($presign1['data']['media_id']);
        $media2 = Media::withoutGlobalScopes()->findOrFail($presign2['data']['media_id']);

        // Assert they have different MediaBlobs
        $this->assertNotEquals($media1->media_blob_id, $media2->media_blob_id);

        // Assert two physical files exist
        Storage::disk('local')->assertExists($presign1['data']['path']);
        Storage::disk('local')->assertExists($presign2['data']['path']);
    }

    public function test_secure_download_url_generation(): void
    {
        $user = $this->createTenantUser('org-1');
        $otherUser = $this->createTenantUser('org-1');

        // 1. Upload a private file
        Sanctum::actingAs($user);
        $presign = $this->postJson('/api/v1/media/presign', [
            'filename' => 'private.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'is_public' => false,
        ])->json();

        Storage::disk('local')->put($presign['data']['path'], 'private content');
        $checksum = hash('sha256', 'private content');
        $this->postJson("/api/v1/media/{$presign['data']['media_id']}/confirm", ['checksum' => $checksum])->assertOk();

        // 2. Owner downloads
        $response = $this->getJson("/api/v1/media/{$presign['data']['media_id']}/download");
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'download_url',
                ],
            ]);

        // Check audit fields updated (1 from confirmation response serialization + 1 from download route request)
        $media = Media::findOrFail($presign['data']['media_id']);
        $this->assertEquals(2, $media->download_count);
        $this->assertNotNull($media->last_downloaded_at);

        // 3. Other unprivileged user tries to download
        Sanctum::actingAs($otherUser);
        $response = $this->getJson("/api/v1/media/{$presign['data']['media_id']}/download");
        $response->assertStatus(403);

        // 4. Admin tries to download
        $admin = $this->createTenantUser('org-1');
        Permission::findOrCreate('admin.super', 'web');
        $admin->givePermissionTo('admin.super');

        Sanctum::actingAs($admin);
        $response = $this->getJson("/api/v1/media/{$presign['data']['media_id']}/download");
        $response->assertStatus(200);
    }

    public function test_pending_media_resource_serialization_does_not_crash(): void
    {
        $user = $this->createTenantUser('org-1');
        $media = Media::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'org-1',
            'media_type' => MediaType::Image,
            'purpose' => 'avatar',
            'is_public' => true,
            'status' => MediaStatus::Pending,
            'custom_properties' => [
                'filename' => 'photo.jpg',
            ],
            'created_by' => $user->id,
        ]);

        $resource = new MediaResource($media);
        $array = $resource->toArray(request());

        $this->assertEquals($media->id, $array['id']);
        $this->assertEquals(0, $array['size']);
        $this->assertEquals('', $array['mime_type']);
        $this->assertEquals('', $array['download_url']);
    }
}

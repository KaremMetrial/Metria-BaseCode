# Implementation Plan: Advanced Enterprise Media Upload Domain

Design and implement a highly secure, scalable, and resilient media upload and processing domain (`app/Domain/Media`) incorporating direct-to-cloud uploads, an explicit `MediaStatus` state machine, tenant-scoped physical blob deduplication, virus scanning, content moderation, integrity checksum validation, and variant generation.

---

## Technical Specifications & Refinements

1. **Tenant-Scoped Deduplication**: Deduplication is scoped strictly per tenant: `['tenant_id', 'sha256']`.
2. **State Machine Service**: Status transitions are managed by a dedicated `MediaStateMachine` service instead of being inside the `Media` model. Enforces transitions using the `MediaStatus` enum.
3. **Immutable DTOs**: `VirusScanResult` and `ModerationResult` are modeled as immutable value objects.
4. **Enriched DB Schemas**:
   * `media_blobs` stores `storage_provider`, `bucket`, `region`, `etag`, `storage_class`, `encryption`, `kms_key`, `multipart_upload_id`, `last_accessed_at`, `access_count`.
   * `media` stores `processing_started_at`, `processing_finished_at`, `download_count`, `last_downloaded_at`, `published_at`, `restored_at`.
   * `media_variants` stores `mime_type`, `checksum`, `hash_algorithm`, `disk`, `storage_provider`, `is_generated`, `processing_time_ms`.
5. **Decoupled Orchestration Services**:
   * `MediaUploadService` - Handles uploads initialization & confirmation.
   * `MediaVerificationService` - Handles checksum checks, virus scanning, and content moderation.
   * `MediaProcessingService` - Handles metadata extraction, optimization, and variants.
   * `MediaDownloadService` - Handles signed URLs and CDN configurations.
6. **Variant Type Safety**: Introduce a typed `MediaVariantType` PHP Enum.

---

## Proposed Changes

### 1. Database Migrations
#### [NEW] [create_media_blobs_table.php](file:///home/metrial/Downloads/laravel-enterprise-base/database/migrations/2025_01_01_000060_create_media_blobs_table.php)
#### [NEW] [create_media_attachments_table.php](file:///home/metrial/Downloads/laravel-enterprise-base/database/migrations/2025_01_01_000061_create_media_attachments_table.php)
#### [NEW] [create_media_variants_table.php](file:///home/metrial/Downloads/laravel-enterprise-base/database/migrations/2025_01_01_000062_create_media_variants_table.php)

---

### 2. Domain Models & Enums
#### [NEW] [MediaStatus.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Enums/MediaStatus.php)
#### [NEW] [MediaVariantType.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Enums/MediaVariantType.php)
#### [NEW] [MediaBlob.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Models/MediaBlob.php)
#### [NEW] [Media.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Models/Media.php)
#### [NEW] [MediaVariant.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Models/MediaVariant.php)
#### [NEW] [MediaPolicy.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Policies/MediaPolicy.php)

---

### 3. State Machine & Value Objects
#### [NEW] [MediaStateMachine.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Services/MediaStateMachine.php)
#### [NEW] [VirusScanResult.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/DTOs/VirusScanResult.php)
#### [NEW] [ModerationResult.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/DTOs/ModerationResult.php)

---

### 4. Infrastructure Adapters (Virus Scanner & Content Moderation)
#### [NEW] [VirusScanner.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Contracts/VirusScanner.php)
#### [NEW] [ClamAvVirusScanner.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Services/ClamAvVirusScanner.php)
#### [NEW] [ContentModerator.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Contracts/ContentModerator.php)
#### [NEW] [RekognitionModerator.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Services/RekognitionModerator.php)

---

### 5. Services Layer
#### [NEW] [MediaUploadService.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Services/MediaUploadService.php)
#### [NEW] [MediaVerificationService.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Services/MediaVerificationService.php)
#### [NEW] [MediaProcessingService.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Services/MediaProcessingService.php)
#### [NEW] [MediaDownloadService.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Services/MediaDownloadService.php)

---

### 6. Background Jobs & Events
#### [NEW] [VerifyMediaUpload.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Jobs/VerifyMediaUpload.php)
#### [NEW] [ProcessMediaVariants.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Jobs/ProcessMediaVariants.php)
#### [NEW] [MediaEvents.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Events) (Collection of event classes)

---

### 7. API Controllers & Routes
#### [NEW] [MediaController.php](file:///home/metrial/Downloads/laravel-enterprise-base/app/Domain/Media/Http/Controllers/Api/V1/MediaController.php)
#### [MODIFY] [api_v1.php](file:///home/metrial/Downloads/laravel-enterprise-base/routes/api_v1.php)
#### [NEW] [media.php](file:///home/metrial/Downloads/laravel-enterprise-base/config/media.php)

---

## Verification Plan

### Automated Tests
#### [NEW] [MediaUploadTest.php](file:///home/metrial/Downloads/laravel-enterprise-base/tests/Feature/Media/MediaUploadTest.php)
* Tests covering all verification items, state transitions, checksum verifications, and deduplications.

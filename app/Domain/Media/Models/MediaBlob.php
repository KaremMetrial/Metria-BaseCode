<?php

declare(strict_types=1);

namespace App\Domain\Media\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents the physical file stored in object storage or local disk.
 * Supports soft deletes and tenant-scoped deduplication.
 */
class MediaBlob extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'media_blobs';

    protected $fillable = [
        'tenant_id',
        'sha256',
        'disk',
        'path',
        'filename',
        'original_filename',
        'mime_type',
        'size',
        'virus_status',
        'virus_scan_details',
        'storage_provider',
        'bucket',
        'region',
        'etag',
        'storage_class',
        'encryption',
        'kms_key',
        'multipart_upload_id',
        'uploaded_at',
        'verified_at',
        'last_accessed_at',
        'access_count',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'virus_scan_details' => 'array',
            'uploaded_at' => 'datetime',
            'verified_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'access_count' => 'integer',
        ];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Media::class, 'media_blob_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Media\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Core\Traits\HasUuid;
use App\Domain\Governance\Traits\Auditable;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Enums\MediaType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents the logical business attachment linked to a specific entity.
 * References a physical MediaBlob.
 */
class Media extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'media';

    protected $fillable = [
        'id',
        'tenant_id',
        'media_blob_id',
        'mediable_type',
        'mediable_id',
        'media_type',
        'purpose',
        'is_public',
        'status',
        'checksum',
        'hash_algorithm',
        'custom_properties',
        'moderation_status',
        'moderation_details',
        'processing_error',
        'retry_count',
        'expires_at',
        'processing_started_at',
        'processing_finished_at',
        'activated_at',
        'quarantined_at',
        'published_at',
        'restored_at',
        'last_downloaded_at',
        'download_count',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'media_type' => MediaType::class,
            'status' => MediaStatus::class,
            'is_public' => 'boolean',
            'custom_properties' => 'array',
            'moderation_details' => 'array',
            'retry_count' => 'integer',
            'expires_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'processing_finished_at' => 'datetime',
            'activated_at' => 'datetime',
            'quarantined_at' => 'datetime',
            'published_at' => 'datetime',
            'restored_at' => 'datetime',
            'last_downloaded_at' => 'datetime',
            'download_count' => 'integer',
        ];
    }

    public function blob(): BelongsTo
    {
        return $this->belongsTo(MediaBlob::class, 'media_blob_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class, 'media_id');
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}

<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Media Storage Settings
    |--------------------------------------------------------------------------
    */
    'default_disk' => env('MEDIA_DISK', 'public'),

    'private_disk' => env('MEDIA_PRIVATE_DISK', 'local'),

    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'video/mp4',
        'video/quicktime',
        'video/x-matroska',
        'application/pdf',
        'application/zip',
    ],

    'max_file_size_bytes' => env('MEDIA_MAX_FILE_SIZE', 500 * 1024 * 1024), // 500 MB

    /*
    |--------------------------------------------------------------------------
    | Malware Scanning (ClamAV)
    |--------------------------------------------------------------------------
    */
    'virus_scan_enabled' => env('MEDIA_VIRUS_SCAN_ENABLED', true),

    'clamav' => [
        'dsn' => env('CLAMAV_DSN', 'tcp://127.0.0.1:3310'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Moderation (Rekognition / NSFW Filtering)
    |--------------------------------------------------------------------------
    */
    'moderation_enabled' => env('MEDIA_MODERATION_ENABLED', true),

    'moderation' => [
        'min_confidence' => env('MEDIA_MODERATION_CONFIDENCE', 80.0),
        // Categories to block/flag
        'blocked_labels' => [
            'Explicit Nudity',
            'Nudity',
            'Graphic Violence',
            'Violence',
            'Self-Harm',
            'Hate Symbols',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Processing & Variants
    |--------------------------------------------------------------------------
    */
    'watermark' => [
        'enabled' => env('MEDIA_WATERMARK_ENABLED', false),
        'text' => env('MEDIA_WATERMARK_TEXT', 'Enterprise Monolith'),
        'image_path' => env('MEDIA_WATERMARK_IMAGE_PATH', null),
    ],

    'image_variants' => [
        'thumbnail' => [
            'width' => 150,
            'height' => 150,
            'fit' => 'crop',
        ],
        'medium' => [
            'width' => 800,
            'height' => 600,
            'fit' => 'max',
        ],
        'large' => [
            'width' => 1920,
            'height' => 1080,
            'fit' => 'max',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN / Cache settings
    |--------------------------------------------------------------------------
    */
    'cdn_url' => env('MEDIA_CDN_URL', null),

    'signed_url_ttl_seconds' => env('MEDIA_SIGNED_URL_TTL', 3600),
];

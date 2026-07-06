<?php

use App\Domain\Payment\Services\ApproveRefundHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Audit logging
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'enabled' => env('AUDIT_ENABLED', true),
        'retention_days' => env('AUDIT_RETENTION_DAYS', 365),
        // Attribute names never written to audit logs.
        'masked_attributes' => ['password', 'remember_token', 'secret', 'token', 'api_key'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Approval workflows (maker–checker)
    |--------------------------------------------------------------------------
    | Map an action name to an invokable handler class. A pending
    | ApprovalRequest stores the payload; on approval the handler is invoked
    | with that payload. Requester and approver must be different users.
    */
    'approvals' => [
        'enabled' => env('APPROVALS_ENABLED', true),
        'handlers' => [
            'payments.refund' => ApproveRefundHandler::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    */
    'idempotency' => [
        'ttl_hours' => env('IDEMPOTENCY_TTL_HOURS', 24),
        'header' => 'Idempotency-Key',
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbox
    |--------------------------------------------------------------------------
    */
    'outbox' => [
        'batch_size' => 100,
        'max_attempts' => 10,
    ],
];

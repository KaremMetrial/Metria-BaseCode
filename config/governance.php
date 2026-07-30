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

    // Idempotency config moved to modules/Shared/Infrastructure/config/core.php
    // ('core.idempotency.*') — owned by Shared since IdempotencyMiddleware and
    // the IdempotencyKey model both live there.

    // Outbox config moved to modules/Webhook/Infrastructure/config/webhook.php
    // ('webhook.outbox.*') — the only consumer (PublishOutboxMessages) lives
    // in the Webhook module.
];

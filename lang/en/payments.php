<?php

return [
    'refunded' => 'Payment refunded.',
    'refund_pending_approval' => 'Refund request submitted and pending approval.',
    'not_refundable' => 'This payment is not in a refundable state.',
    'refund_exceeds_amount' => 'Refund amount exceeds the refundable balance.',
    'gateway_creation_failed' => '[:gateway] payment creation failed.',
    'gateway_refund_failed' => '[:gateway] refund failed.',
    'gateway_auth_failed' => '[:gateway] authentication failed.',
    'missing_transaction_id' => 'Paymob refund requires the captured transaction id (stored from the webhook).',
    'missing_fawry_ref' => 'Fawry refund requires the Fawry reference number (stored from the webhook).',
];

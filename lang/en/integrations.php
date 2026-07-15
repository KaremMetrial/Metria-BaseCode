<?php

return [
    'connection_failed' => 'Could not reach [:service].',
    'service_error' => '[:service] responded with status code :status.',
    'circuit_open' => 'Circuit open for [:service] — provider temporarily disabled after repeated failures.',
    'sms_send_failed' => 'Failed to send SMS via [:provider].',
    'push_send_failed' => 'Failed to send push notification via [:provider].',
    'invalid_response_class' => 'Invalid response class returned from circuit breaker call.',
];


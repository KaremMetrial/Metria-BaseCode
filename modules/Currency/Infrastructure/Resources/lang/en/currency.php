<?php

return [
    'exchange_rate_missing' => 'No exchange rate registered for currency: :currency',
    'exchange_rate_stale' => 'Exchange rate for :currency is stale. Expired at: :expired_at',
    'override_locked' => 'Cannot override rate for :currency because a locked manual override covers this period.',
    'cannot_delete_historical' => 'Cannot delete currency :currency because it has referenced historical exchange rates. Retire it by setting is_active = false.',
    'cannot_delete_referenced' => 'Cannot delete currency :currency because it has referenced transaction/payment records. Retire it by setting is_active = false.',
    'invalid_api_payload' => 'CurrencyExchangeAPI payload missing or invalid numeric rate for :currency.',
    'all_providers_failed' => 'All exchange rate providers failed to fetch rate for :currency. Trace: :trace',
    'mock_connection_timeout' => 'Mock provider failed connection timeout.',
    'mock_rate_missing' => 'No mock rate configured for :currency.',
    'money_negative' => 'Money amount cannot be negative.',
    'money_negative_result' => 'Resulting money amount would be negative.',
    'currency_mismatch' => 'Currency mismatch: :current vs :other',
];



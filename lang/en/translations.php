<?php

return [
    'unknown_prompt_version' => 'Unknown translation prompt version: :version',
    'circuit_open' => 'Circuit breaker is OPEN for provider [:provider]. Calls blocked.',
    'gemini_missing_key' => 'Gemini API key is not configured.',
    'gemini_network_error' => 'Network error contacting Gemini: :error',
    'gemini_rate_limited' => 'Gemini rate limit exceeded.',
    'gemini_error_code' => 'Gemini API returned error code :status',
    'missing_content' => 'Response content is missing or not a string.',
    'invalid_json' => 'Response text could not be decoded as valid JSON.',
    'missing_key' => 'Missing translated key: :key',
    'value_not_string' => "Translated key ':key' value is not a string.",
    'value_empty' => "Translated key ':key' value is empty.",
    'value_not_utf8' => "Translated key ':key' value is not valid UTF-8.",
    'driver_interface_required' => 'Translation driver must implement TranslationProviderInterface.',
];

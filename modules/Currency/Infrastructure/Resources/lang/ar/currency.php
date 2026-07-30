<?php

return [
    'exchange_rate_missing' => 'لا يوجد سعر صرف مسجل للعملة: :currency',
    'exchange_rate_stale' => 'سعر الصرف للعملة :currency قديم ومنتهي الصلاحية. انتت صلاحيته في: :expired_at',
    'override_locked' => 'لا يمكن تجاوز سعر الصرف للعملة :currency بسبب وجود تجاوز يدوي مقفل يغطي هذه الفترة.',
    'cannot_delete_historical' => 'لا يمكن حذف العملة :currency لارتباطها بأسعار صرف تاريخية مرجعية. قم بتعطيلها بضبط is_active = false.',
    'cannot_delete_referenced' => 'لا يمكن حذف العملة :currency لارتباطها بسجلات معاملات أو مدفوعات مرجعية. قم بتعطيلها بضبط is_active = false.',
    'invalid_api_payload' => 'حمولة CurrencyExchangeAPI مفقودة أو معدل رقمي غير صالح للعملة :currency.',
    'all_providers_failed' => 'فشلت جميع مزودات أسعار الصرف في جلب السعر للعملة :currency. سجل التتبع: :trace',
    'mock_connection_timeout' => 'فشل مزود المحاكاة بسبب انتهاء مهلة الاتصال.',
    'mock_rate_missing' => 'لا يوجد سعر محاكاة مهيأ للعملة :currency.',
    'money_negative' => 'لا يمكن أن يكون المبلغ المالي سالبًا.',
    'money_negative_result' => 'المبلغ المالي الناتج سيكون سالبًا.',
    'currency_mismatch' => 'عدم تطابق العملة: :current مقابل :other',
];



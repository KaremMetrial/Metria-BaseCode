<?php

return [
    'unknown_prompt_version' => 'إصدار موجه الترجمة غير معروف: :version',
    'circuit_open' => 'الدائرة مفتوحة للمزود [:provider]. المكالمات محجوبة.',
    'gemini_missing_key' => 'مفتاح Gemini API غير مهيأ.',
    'gemini_network_error' => 'خطأ في الشبكة أثناء الاتصال بـ Gemini: :error',
    'gemini_rate_limited' => 'تم تجاوز حد الطلبات لـ Gemini.',
    'gemini_error_code' => 'أعادت واجهة Gemini برمجة خطأ بالرمز :status',
    'missing_content' => 'محتوى الاستجابة مفقود أو ليس سلسلة نصية.',
    'invalid_json' => 'تعذر فك ترميز نص الاستجابة كـ JSON صالح.',
    'missing_key' => 'مفتاح الترجمة المفقود: :key',
    'value_not_string' => "قيمة المفتاح المترجم ':key' ليست سلسلة نصية.",
    'value_empty' => "قيمة المفتاح المترجم ':key' فارغة.",
    'value_not_utf8' => "قيمة المفتاح المترجم ':key' ليست بتنسيق UTF-8 صالح.",
    'driver_interface_required' => 'يجب أن ينفذ مشغل الترجمة واجهة TranslationProviderInterface.',
];

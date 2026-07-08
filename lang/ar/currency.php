<?php

return [
    'exchange_rate_missing' => 'لا يوجد سعر صرف مسجل للعملة: :currency',
    'exchange_rate_stale' => 'سعر الصرف للعملة :currency قديم ومنتهي الصلاحية. انتت صلاحيته في: :expired_at',
    'override_locked' => 'لا يمكن تجاوز سعر الصرف للعملة :currency بسبب وجود تجاوز يدوي مقفل يغطي هذه الفترة.',
    'cannot_delete_historical' => 'لا يمكن حذف العملة :currency لارتباطها بأسعار صرف تاريخية مرجعية. قم بتعطيلها بضبط is_active = false.',
    'cannot_delete_referenced' => 'لا يمكن حذف العملة :currency لارتباطها بسجلات معاملات أو مدفوعات مرجعية. قم بتعطيلها بضبط is_active = false.',
];

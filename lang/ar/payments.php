<?php

return [
    'refunded' => 'تم استرداد المبلغ.',
    'refund_pending_approval' => 'تم إرسال طلب الاسترداد وهو بانتظار الموافقة.',
    'not_refundable' => 'هذه العملية ليست في حالة تسمح بالاسترداد.',
    'refund_exceeds_amount' => 'قيمة الاسترداد تتجاوز الرصيد القابل للاسترداد.',
    'gateway_creation_failed' => 'فشل إنشاء عملية الدفع عبر [:gateway].',
    'gateway_refund_failed' => 'فشل استرداد المبلغ عبر [:gateway].',
    'gateway_auth_failed' => 'فشل المصادقة مع بوابة [:gateway].',
    'missing_transaction_id' => 'استرداد المبلغ عبر Paymob يتطلب وجود معرف المعاملة (المحفوظ من الـ Webhook).',
    'missing_fawry_ref' => 'استرداد المبلغ عبر Fawry يتطلب وجود رقم مرجع Fawry (المحفوظ من الـ Webhook).',
];

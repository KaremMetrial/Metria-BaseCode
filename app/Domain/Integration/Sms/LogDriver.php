<?php

declare(strict_types=1);

namespace App\Domain\Integration\Sms;

use App\Domain\Integration\Contracts\SmsProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Local/dev driver: writes the SMS to the log instead of sending it. */
class LogDriver implements SmsProvider
{
    public function send(string $to, string $message): string
    {
        $id = 'log-'.Str::uuid();

        Log::info('sms.sent', ['id' => $id, 'to' => $to, 'message' => $message]);

        return $id;
    }
}

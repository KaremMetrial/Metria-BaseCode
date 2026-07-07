<?php

declare(strict_types=1);

namespace App\Domain\Auth\Listeners;

use App\Domain\Auth\Events\OtpGenerated;
use App\Domain\Integration\Sms\SmsManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendOtpNotification implements ShouldQueue
{
    public function __construct(private readonly SmsManager $sms) {}

    public function handle(OtpGenerated $event): void
    {
        $identifier = $event->identifier;

        $translatedMsg = __('auth.otp_message', ['code' => $event->code]);
        $message = ($translatedMsg === 'auth.otp_message' || str_contains($translatedMsg, 'auth.otp_message'))
            ? "Your verification code is: {$event->code}"
            : $translatedMsg;

        if (str_contains($identifier, '@')) {
            // Identifier is an email address
            $translatedSubject = __('auth.otp_subject');
            $subject = ($translatedSubject === 'auth.otp_subject' || str_contains($translatedSubject, 'auth.otp_subject'))
                ? 'Verification Code'
                : $translatedSubject;

            Mail::raw($message, function ($mail) use ($identifier, $subject) {
                $mail->to($identifier)
                    ->subject($subject);
            });
        } else {
            // Identifier is a phone number
            $this->sms->driver()->send($identifier, $message);
        }
    }
}

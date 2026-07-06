<?php

declare(strict_types=1);

namespace App\Domain\Integration\Contracts;

interface SmsProvider
{
    /**
     * Send an SMS and return the provider message id.
     *
     * @param  string  $to  E.164 phone number, e.g. +2010xxxxxxx
     */
    public function send(string $to, string $message): string;
}

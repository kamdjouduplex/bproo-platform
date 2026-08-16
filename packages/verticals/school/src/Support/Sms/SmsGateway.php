<?php

namespace School\Support\Sms;

interface SmsGateway
{
    /**
     * @return array{ok:bool, skipped?:bool, error?:string, provider?:string}
     */
    public function send(string $to, string $message, ?string $from = null): array;

    public function name(): string;

    public function configured(): bool;
}

<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/*
 * Writes the message to the log instead of sending it.
 *
 * This is the driver a developer runs against, and it is deliberately the
 * default: a misconfigured production server that silently writes login
 * codes to a log is a bad day, but a misconfigured one that silently sends
 * nothing and reports success is a worse one — this at least leaves the code
 * somewhere findable while the gateway credentials are sorted out.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): bool
    {
        Log::info('sms (not actually sent)', [
            'to' => $phone,
            'message' => $message,
        ]);

        return true;
    }
}

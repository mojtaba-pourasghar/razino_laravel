<?php

namespace App\Services\Sms;

/*
 * Sending one short message to one phone.
 *
 * An interface rather than a class because the provider is a deployment
 * decision, not a design one: the same server runs against a log file on a
 * laptop and against an Iranian SMS gateway in production, and nothing above
 * this line should change when it moves.
 */
interface SmsSender
{
    /**
     * @return bool whether the provider accepted the message. A false here
     *              means the player never got their code, so the caller must
     *              say so rather than asking them to check their phone.
     */
    public function send(string $phone, string $message): bool;
}

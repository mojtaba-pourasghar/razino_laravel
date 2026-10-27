<?php

namespace App\Providers;

use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use Illuminate\Support\ServiceProvider;

/*
 * Picks the SMS driver from configuration.
 *
 * Only the log driver ships. An Iranian gateway (Kavenegar, SMS.ir, Melipayamak
 * and the rest) needs an account, a sender line and a template approved by
 * the provider, none of which can be guessed from here — so rather than a
 * half-written client that looks finished, there is one interface and one
 * honest implementation. Writing the second one is a class with a single
 * method; see README.
 */
class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsSender::class, function () {
            return match (config('sms.driver', 'log')) {
                default => new LogSmsSender(),
            };
        });
    }
}

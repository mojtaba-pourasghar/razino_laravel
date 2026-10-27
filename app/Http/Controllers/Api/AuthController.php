<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginCode;
use App\Models\User;
use App\Services\Sms\SmsSender;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Signing in with a phone number and a code.
 *
 * There is no password anywhere in Razino. The players are eight to twelve
 * and the phone is a parent's, which is the arrangement the parental zone
 * already assumes — tying the account to that number means the grown-up who
 * approves a purchase is the one who can get into the account.
 *
 * Two deliberate behaviours worth not 'fixing' later:
 *
 *  · Asking for a code never says whether that phone has an account. The
 *    answer is the same either way, so this endpoint cannot be used to find
 *    out who plays.
 *  · An account is created on first successful verification, not when the
 *    code is requested. Otherwise anyone could fill the users table by
 *    typing numbers.
 */
class AuthController extends Controller
{
    /** Codes one phone may ask for, and how often one IP may ask at all. */
    private const PER_PHONE_PER_HOUR = 5;
    private const PER_IP_PER_HOUR = 20;

    public function requestCode(Request $request, SmsSender $sms): JsonResponse
    {
        $phone = Phone::normalise($request->input('phone'));
        if ($phone === null) {
            return response()->json([
                'error' => 'invalid_phone',
                'message' => 'شمارهٔ موبایل درست نیست.',
            ], 422);
        }

        foreach ([
            ['key' => 'otp-phone:'.$phone, 'max' => self::PER_PHONE_PER_HOUR],
            ['key' => 'otp-ip:'.$request->ip(), 'max' => self::PER_IP_PER_HOUR],
        ] as $limit) {
            if (RateLimiter::tooManyAttempts($limit['key'], $limit['max'])) {
                return response()->json([
                    'error' => 'too_many_requests',
                    'message' => 'درخواست‌های زیادی فرستاده شده. کمی بعد دوباره تلاش کن.',
                    'retry_after' => RateLimiter::availableIn($limit['key']),
                ], 429);
            }
        }

        // An outstanding code is not replaced before its own cooldown, so
        // tapping "send again" twice does not cost the player two of their
        // five hourly codes.
        $pending = LoginCode::pendingFor($phone);
        if ($pending && $pending->secondsLeft() > LoginCode::LIFETIME_SECONDS - 60) {
            return response()->json([
                'sent' => true,
                'expires_in' => $pending->secondsLeft(),
            ]);
        }

        foreach (['otp-phone:'.$phone, 'otp-ip:'.$request->ip()] as $key) {
            RateLimiter::hit($key, 3600);
        }

        [$model, $code] = LoginCode::issue($phone, $request->ip());

        $delivered = $sms->send($phone, "کد ورود رازینو: {$code}\nاین کد تا ۳ دقیقه معتبر است.");
        if (! $delivered) {
            // Saying "check your phone" when nothing was sent wastes three
            // minutes of a child's patience on a problem that is ours.
            $model->burn();

            return response()->json([
                'error' => 'sms_failed',
                'message' => 'پیامک فرستاده نشد. کمی بعد دوباره تلاش کن.',
            ], 502);
        }

        return response()->json([
            'sent' => true,
            'expires_in' => $model->secondsLeft(),
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $phone = Phone::normalise($request->input('phone'));
        $code = preg_replace('/\D/', '', (string) $request->input('code')) ?? '';

        if ($phone === null || strlen($code) !== 6) {
            return response()->json([
                'error' => 'invalid_request',
                'message' => 'شماره یا کد درست نیست.',
            ], 422);
        }

        $pending = LoginCode::pendingFor($phone);
        if ($pending === null || $pending->spent()) {
            return response()->json([
                'error' => 'code_expired',
                'message' => 'این کد دیگر معتبر نیست. کد تازه بگیر.',
            ], 422);
        }

        if (! $pending->matches($code)) {
            $pending->increment('attempts');

            return response()->json([
                'error' => 'code_wrong',
                'message' => 'کد درست نیست.',
                'attempts_left' => max(0, LoginCode::MAX_ATTEMPTS - $pending->attempts),
            ], 422);
        }

        $pending->burn();

        $user = User::firstOrCreate(
            ['phone' => $phone],
            ['name' => 'بازیکن', 'phone_verified_at' => now()],
        );
        if ($user->phone_verified_at === null) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        // One token per device. The old ones stay, so signing in on a tablet
        // does not sign the child out of the phone they were mid-chapter on.
        $token = $user->createToken('razino-'.substr(sha1($request->userAgent() ?? ''), 0, 8));

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => Phone::forHumans($phone),
            ],
        ]);
    }

    /** Who this token belongs to — the game calls it on launch. */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone ? Phone::forHumans($user->phone) : null,
        ]);
    }

    /** Signs this device out, leaving the player's other devices alone. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }
}

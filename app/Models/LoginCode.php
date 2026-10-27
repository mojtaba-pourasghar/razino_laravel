<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A one-time code sent to a phone.
 *
 * The code is hashed, never stored, so this table is not a list of working
 * logins. It is also counted: a code that has been guessed at too many times
 * is dead even if the right answer arrives next, because otherwise six
 * digits is only a million tries away from anyone's account.
 */
class LoginCode extends Model
{
    protected $fillable = [
        'phone', 'code_hash', 'attempts', 'expires_at', 'consumed_at', 'request_ip',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /** How long a code is worth typing. Long enough for a slow SMS. */
    public const LIFETIME_SECONDS = 180;

    /** Wrong guesses allowed before the code is burned. */
    public const MAX_ATTEMPTS = 5;

    /**
     * Issues a code for a phone and returns [model, plain code].
     *
     * Any code already outstanding for that phone is consumed first, so the
     * newest message is always the one that works — a player who taps
     * "send again" and then types the older code they can still see is a
     * support ticket nobody needs.
     */
    public static function issue(string $phone, ?string $ip = null): array
    {
        static::where('phone', $phone)->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        // Six digits, zero-padded, from a generator meant for secrets.
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $model = static::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addSeconds(self::LIFETIME_SECONDS),
            'request_ip' => $ip,
        ]);

        return [$model, $code];
    }

    /** The newest code for a phone that could still be used. */
    public static function pendingFor(string $phone): ?self
    {
        return static::where('phone', $phone)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    public function matches(string $code): bool
    {
        return Hash::check($code, $this->code_hash);
    }

    public function burn(): void
    {
        $this->consumed_at = now();
        $this->save();
    }

    public function spent(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }

    /** Seconds until this code dies, for the countdown the game shows. */
    public function secondsLeft(): int
    {
        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }
}

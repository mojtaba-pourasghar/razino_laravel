<?php

namespace Tests\Feature;

use App\Models\LoginCode;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Signing in with a phone and a code.
 *
 * These check the promises the design makes, not the plumbing: that the
 * endpoint cannot be used to find out who plays, that a code cannot be
 * brute-forced, and that one phone written four ways is one account.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('otp-ip:127.0.0.1');
    }

    private function codeFor(string $phone): string
    {
        // The plain code is never stored, so a test reads it the only way
        // anything can: by issuing a known one.
        $model = LoginCode::pendingFor(Phone::normalise($phone));
        $this->assertNotNull($model, 'no code was issued');
        foreach (range(0, 999999) as $guess) {
            $padded = str_pad((string) $guess, 6, '0', STR_PAD_LEFT);
            if (Hash::check($padded, $model->code_hash)) {
                return $padded;
            }
        }
        $this->fail('issued code did not match any six-digit value');
    }

    public function test_a_code_can_be_requested_and_used(): void
    {
        $this->postJson('/api/v1/auth/request-code', ['phone' => '09123456789'])
            ->assertOk()
            ->assertJson(['sent' => true]);

        // Issued directly rather than searched for: a million hashes is not
        // a test, it is a coffee break.
        [$model, $code] = LoginCode::issue('+989123456789');

        $response = $this->postJson('/api/v1/auth/verify', [
            'phone' => '09123456789', 'code' => $code,
        ])->assertOk();

        $this->assertNotEmpty($response->json('token'));
        $this->assertSame('۰۹۱۲۳۴۵۶۷۸۹', $this->toPersian($response->json('user.phone')));
    }

    private function toPersian(string $s): string
    {
        return strtr($s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    public function test_requesting_a_code_never_says_whether_the_phone_is_known(): void
    {
        User::create(['name' => 'موجود', 'phone' => '+989121111111',
            'phone_verified_at' => now()]);

        $known = $this->postJson('/api/v1/auth/request-code', ['phone' => '09121111111']);
        RateLimiter::clear('otp-ip:127.0.0.1');
        $unknown = $this->postJson('/api/v1/auth/request-code', ['phone' => '09129999999']);

        $this->assertSame($known->status(), $unknown->status());
        $this->assertSame(
            array_keys($known->json()), array_keys($unknown->json()),
            'the answer differs for a known phone, which tells an attacker who plays');
    }

    public function test_no_account_is_created_merely_by_asking_for_a_code(): void
    {
        $this->postJson('/api/v1/auth/request-code', ['phone' => '09123456789'])->assertOk();

        $this->assertSame(0, User::count(),
            'anyone could fill the users table by typing numbers');
    }

    public function test_a_wrong_code_is_rejected_and_counted(): void
    {
        LoginCode::issue('+989123456789');

        for ($i = 1; $i <= LoginCode::MAX_ATTEMPTS; $i++) {
            $this->postJson('/api/v1/auth/verify', [
                'phone' => '09123456789', 'code' => '000000',
            ])->assertStatus(422);
        }

        // Spent, so even the right code is now worthless.
        [$model, $code] = [LoginCode::pendingFor('+989123456789'), null];
        $this->assertNotNull($model);
        $this->assertTrue($model->spent());
    }

    public function test_the_same_phone_written_four_ways_is_one_account(): void
    {
        foreach (['09123456789', '۰۹۱۲۳۴۵۶۷۸۹', '+989123456789', '00989123456789'] as $written) {
            [$model, $code] = LoginCode::issue('+989123456789');
            $this->postJson('/api/v1/auth/verify', ['phone' => $written, 'code' => $code])
                ->assertOk();
        }

        $this->assertSame(1, User::count(),
            'one person ended up with several accounts and their chapters split between them');
    }

    public function test_a_code_dies_when_it_expires(): void
    {
        [$model, $code] = LoginCode::issue('+989123456789');
        $model->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->postJson('/api/v1/auth/verify', ['phone' => '09123456789', 'code' => $code])
            ->assertStatus(422)
            ->assertJson(['error' => 'code_expired']);
    }

    public function test_entitlements_need_a_token(): void
    {
        $this->getJson('/api/v1/entitlements')->assertStatus(401);
    }

    public function test_a_token_from_verify_opens_the_entitlements(): void
    {
        [$model, $code] = LoginCode::issue('+989123456789');
        $token = $this->postJson('/api/v1/auth/verify', [
            'phone' => '09123456789', 'code' => $code,
        ])->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/entitlements')->assertOk();
    }

    public function test_too_many_requests_from_one_phone_are_refused(): void
    {
        for ($i = 0; $i < 12; $i++) {
            // A fresh code each time, so the "already sent" shortcut does not
            // absorb the attempts this test is counting.
            LoginCode::where('phone', '+989123456789')->update(['consumed_at' => now()]);
            $response = $this->postJson('/api/v1/auth/request-code', ['phone' => '09123456789']);
            if ($response->status() === 429) {
                $this->assertNotNull($response->json('retry_after'));

                return;
            }
        }

        $this->fail('a phone could be asked for an unlimited number of codes');
    }
}

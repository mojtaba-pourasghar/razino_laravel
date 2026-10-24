<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Entitlement;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The endpoints the game calls, checked against what the game expects of
 * them rather than against the shape of the code.
 *
 * Two of these guard decisions the design makes rather than bugs: a
 * catalogue readable without a login, because a child opening the game for
 * the first time has no account; and an entitlement that survives deleting
 * a chapter to free storage, because what was paid for is not storage.
 */
class ContentApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeChapter(array $overrides = []): Chapter
    {
        return Chapter::create(array_merge([
            'chapter_id' => 'season_02',
            'title' => 'کتابخانهٔ هزار در',
            'language' => 'fa-IR',
            'access_type' => 'paid',
            'delivery' => 'download',
            'version' => 1,
            'minimum_game_version' => '0.1.0',
            'scene_count' => 16,
            'stage_count' => 8,
            'clue_count' => 10,
            'price_toman' => 29000,
            'is_published' => true,
        ], $overrides));
    }

    public function test_it_lists_only_published_chapters(): void
    {
        $this->makeChapter();
        $this->makeChapter(['chapter_id' => 'season_09', 'is_published' => false]);

        $response = $this->getJson('/api/v1/catalog');

        $response->assertOk();
        $this->assertCount(1, $response->json('chapters'));
        $this->assertSame('season_02', $response->json('chapters.0.chapter_id'));
    }

    public function test_the_catalogue_is_readable_without_a_login(): void
    {
        $this->makeChapter();

        $this->getJson('/api/v1/catalog')->assertOk();
    }

    public function test_it_tells_the_game_nothing_changed_when_nothing_has(): void
    {
        $this->makeChapter();
        $revision = $this->getJson('/api/v1/catalog')->json('revision');

        $this->withHeader('If-None-Match', '"'.$revision.'"')
            ->getJson('/api/v1/catalog')
            ->assertStatus(304);
    }

    public function test_an_unknown_chapter_is_refused_in_persian(): void
    {
        $response = $this->getJson('/api/v1/catalog/season_99');

        $response->assertNotFound();
        $this->assertSame('این فصل در فهرست محتوا نیست.', $response->json('message'));
    }

    public function test_an_unsigned_in_request_answers_401_with_no_accept_header(): void
    {
        // Godot's HTTPRequest sends no Accept header of its own. Without
        // the redirect being suppressed, Laravel took the call for a
        // browser visit and returned 500, which the game would have shown
        // a child as a broken server.
        $response = $this->get('/api/v1/entitlements');

        $response->assertStatus(401);
        $this->assertSame('unauthenticated', $response->json('error'));
    }

    public function test_a_free_chapter_needs_no_purchase(): void
    {
        $this->makeChapter(['chapter_id' => 'season_01', 'access_type' => 'free', 'price_toman' => 0]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/entitlements/season_01/check')
            ->assertOk()
            ->assertJson(['allowed' => true]);
    }

    public function test_a_paid_chapter_is_refused_until_it_is_owned(): void
    {
        $this->makeChapter();

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/entitlements/season_02/check');

        $response->assertForbidden();
        $this->assertFalse($response->json('allowed'));
        $this->assertSame(
            'برای دریافت این فصل، ابتدا باید آن را تهیه کنید.',
            $response->json('reason')
        );
    }

    public function test_a_paid_chapter_is_allowed_once_it_is_owned(): void
    {
        $this->makeChapter();
        $user = User::factory()->create();
        Entitlement::create([
            'user_id' => $user->id,
            'chapter_id' => 'season_02',
            'source' => 'purchase',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/entitlements/season_02/check')
            ->assertOk()
            ->assertJson(['allowed' => true, 'version' => 1]);
    }

    public function test_freeing_storage_does_not_take_away_what_was_paid_for(): void
    {
        // Deleting a chapter to make room must never revoke it (GDD §9.4).
        // The game enforces that locally; this is the copy that survives a
        // reinstall.
        $this->makeChapter();
        $user = User::factory()->create();
        Entitlement::create(['user_id' => $user->id, 'chapter_id' => 'season_02']);

        $this->assertContains(
            'season_02',
            $this->actingAs($user)->getJson('/api/v1/entitlements')->json('chapters')
        );
    }

    public function test_the_same_chapter_cannot_be_granted_twice(): void
    {
        // A double tap on "buy" must not create two rows, so the database
        // refuses it rather than the code remembering to.
        $this->makeChapter();
        $user = User::factory()->create();
        Entitlement::create(['user_id' => $user->id, 'chapter_id' => 'season_02']);

        $this->expectException(UniqueConstraintViolationException::class);
        Entitlement::create(['user_id' => $user->id, 'chapter_id' => 'season_02']);
    }
}

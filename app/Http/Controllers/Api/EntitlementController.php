<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Entitlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What a signed-in player is allowed to download.
 *
 * The game already refuses to fetch a paid chapter it has no entitlement
 * for, but that check lives on the player's own device and so decides
 * nothing. This is the one that counts.
 */
class EntitlementController extends Controller
{
    /** Every chapter this player owns, for restoring after a reinstall. */
    public function index(Request $request): JsonResponse
    {
        $owned = Entitlement::query()
            ->where('user_id', $request->user()->id)
            ->pluck('chapter_id')
            ->values();

        return response()->json([
            'chapters' => $owned,
            // Chapter one is free and bundled; it is listed so a restoring
            // player never sees an empty library.
            'free_chapters' => Chapter::query()
                ->where('is_published', true)
                ->where('access_type', 'free')
                ->pluck('chapter_id')
                ->values(),
        ]);
    }

    /**
     * Whether this player may download a chapter right now, and a reason in
     * Persian when they may not — the game shows it as it stands.
     */
    public function check(Request $request, string $chapterId): JsonResponse
    {
        $chapter = Chapter::query()
            ->where('chapter_id', $chapterId)
            ->where('is_published', true)
            ->first();

        if ($chapter === null) {
            return response()->json([
                'allowed' => false,
                'reason' => 'این فصل در فهرست محتوا نیست.',
            ], 404);
        }

        if ($chapter->isFree()) {
            return response()->json(['allowed' => true, 'version' => $chapter->version]);
        }

        $owns = Entitlement::query()
            ->where('user_id', $request->user()->id)
            ->where('chapter_id', $chapterId)
            ->exists();

        if (! $owns) {
            return response()->json([
                'allowed' => false,
                'reason' => 'برای دریافت این فصل، ابتدا باید آن را تهیه کنید.',
            ], 403);
        }

        return response()->json(['allowed' => true, 'version' => $chapter->version]);
    }
}

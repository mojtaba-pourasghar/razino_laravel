<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The chapter catalogue the game reads before it shows a shop or a chapter
 * list.
 *
 * Deliberately readable without a login. A child opening the game for the
 * first time has no account, and the list of what exists is not secret —
 * what is protected is the content itself, which needs an entitlement, and
 * the signature, which the game checks whatever this endpoint says.
 */
class CatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $chapters = Chapter::query()
            ->where('is_published', true)
            ->orderBy('chapter_id')
            ->get()
            ->map(fn (Chapter $c) => $c->toCatalogEntry())
            ->values();

        // The game keeps a copy of the catalogue and only refetches when
        // this changes, so a player on a slow connection is not made to
        // download the whole list to learn nothing changed.
        $revision = $chapters->isEmpty()
            ? '0'
            : substr(hash('sha256', $chapters->toJson()), 0, 16);

        $response = [
            'schema_version' => 1,
            'revision' => $revision,
            'generated_at' => now()->toIso8601String(),
            'chapters' => $chapters,
        ];

        if ($request->header('If-None-Match') === '"'.$revision.'"') {
            return response()->json(null, 304);
        }

        return response()->json($response)
            ->header('ETag', '"'.$revision.'"')
            ->header('Cache-Control', 'public, max-age=300');
    }

    /** One chapter, for the shop page and the update check. */
    public function show(string $chapterId): JsonResponse
    {
        $chapter = Chapter::query()
            ->where('chapter_id', $chapterId)
            ->where('is_published', true)
            ->first();

        if ($chapter === null) {
            return response()->json([
                'error' => 'chapter_not_found',
                'message' => 'این فصل در فهرست محتوا نیست.',
            ], 404);
        }

        return response()->json($chapter->toCatalogEntry());
    }
}

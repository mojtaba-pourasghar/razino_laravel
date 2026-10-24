<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One chapter of the game, as the catalogue describes it.
 *
 * The chapter's content — its scenes, stages, clues and the paths to its
 * paintings — is NOT in this table. It lives in a signed package the game
 * downloads and verifies for itself, so a compromised panel can change what
 * a chapter costs but never what it says. This row is the shop window and
 * the version pointer; the package is the goods.
 */
class Chapter extends Model
{
    use HasFactory;

    protected $fillable = [
        'chapter_id', 'title', 'subtitle', 'synopsis', 'language',
        'access_type', 'delivery', 'version', 'minimum_game_version',
        'scene_count', 'episode_count', 'stage_count', 'clue_count',
        'price_toman', 'is_published', 'package_sha256', 'package_bytes',
    ];

    protected $casts = [
        'version' => 'integer',
        'scene_count' => 'integer',
        'episode_count' => 'integer',
        'stage_count' => 'integer',
        'clue_count' => 'integer',
        'price_toman' => 'integer',
        'package_bytes' => 'integer',
        'is_published' => 'boolean',
    ];

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class, 'chapter_id', 'chapter_id');
    }

    /** Free chapters need no purchase; everything else does. */
    public function isFree(): bool
    {
        return $this->access_type === 'free';
    }

    /**
     * The shape the game's ContentManager reads. Kept deliberately close to
     * content/manifest/catalog.json so the same screens render a catalogue
     * whether it came from here or from the copy inside the APK.
     */
    public function toCatalogEntry(): array
    {
        return [
            'chapter_id' => $this->chapter_id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'synopsis' => $this->synopsis,
            'language' => $this->language,
            'access_type' => $this->access_type,
            'delivery' => $this->delivery,
            'version' => $this->version,
            'minimum_game_version' => $this->minimum_game_version,
            'scene_count' => $this->scene_count,
            'episode_count' => $this->episode_count,
            'stage_count' => $this->stage_count,
            'clue_count' => $this->clue_count,
            'price_toman' => $this->isFree() ? 0 : $this->price_toman,
        ];
    }
}

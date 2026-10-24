<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A player's right to a chapter, once it is paid for.
 *
 * An entitlement is never revoked for lack of storage: a player who deletes
 * a chapter to free space still owns it and can fetch it again (GDD §9.4).
 * The game enforces that locally too; this is the copy that survives a
 * reinstall.
 */
class Entitlement extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'chapter_id', 'source', 'purchase_ref', 'granted_at'];

    protected $casts = ['granted_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'chapter_id', 'chapter_id');
    }
}

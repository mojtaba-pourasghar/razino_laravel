<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            // The id the game knows a chapter by, e.g. "season_01". Unique
            // because every lookup from the game comes through it.
            $table->string('chapter_id')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('synopsis')->nullable();
            $table->string('language', 8)->default('fa');

            $table->enum('access_type', ['free', 'paid'])->default('paid');
            $table->enum('delivery', ['bundled', 'download'])->default('download');

            // Raised every time a new package is published. The game
            // compares it with what it has installed to decide on an update.
            $table->unsignedInteger('version')->default(1);
            $table->string('minimum_game_version', 16)->default('1.0.0');

            $table->unsignedInteger('scene_count')->default(0);
            $table->unsignedInteger('episode_count')->default(0);
            $table->unsignedInteger('stage_count')->default(0);
            $table->unsignedInteger('clue_count')->default(0);

            $table->unsignedInteger('price_toman')->default(0);
            $table->boolean('is_published')->default(false);

            // What was published, so a half-uploaded package is detectable
            // without downloading it.
            $table->string('package_sha256', 64)->nullable();
            $table->unsignedBigInteger('package_bytes')->nullable();

            $table->timestamps();

            // The catalogue endpoint asks for exactly this.
            $table->index(['is_published', 'chapter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapters');
    }
};

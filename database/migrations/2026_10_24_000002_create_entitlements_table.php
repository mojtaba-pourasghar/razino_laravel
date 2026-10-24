<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('chapter_id');

            // Where the right came from, so a refund or a support grant can
            // be told apart from a shop purchase later.
            $table->enum('source', ['purchase', 'gift', 'promo', 'support'])
                ->default('purchase');
            $table->string('purchase_ref')->nullable();
            $table->timestamp('granted_at')->useCurrent();
            $table->timestamps();

            // A player owns a chapter once. Enforced here rather than in
            // code, because a double-tap on "buy" must not create two rows.
            $table->unique(['user_id', 'chapter_id']);
            $table->index('chapter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlements');
    }
};

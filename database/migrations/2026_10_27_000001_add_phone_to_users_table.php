<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A Razino player signs in with a phone number and a code, never with a
 * password. The game is for children aged eight to twelve, and a password is
 * one more thing for them to lose; the phone is a parent's anyway, which is
 * the point — it is the same number the parental zone is tied to.
 *
 * The email column stays because Laravel's own scaffolding uses it, but it
 * is nullable now: almost no player will have one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Stored normalised to +98XXXXXXXXXX so the same phone typed
            // three different ways is one account, not three.
            $table->string('phone', 20)->nullable()->unique()->after('name');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'phone_verified_at']);
        });
    }
};

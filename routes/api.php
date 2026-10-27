<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\EntitlementController;
use Illuminate\Support\Facades\Route;

/*
 * The endpoints the game calls.
 *
 * Two of them are open, because a child opening the game for the first time
 * has no account and the list of what exists is not a secret. What is
 * protected is the content itself — and even that is protected twice: this
 * server decides who may download, and the game checks the package's
 * signature against a key this server never holds, so a breach here cannot
 * put words in a character's mouth.
 */

Route::prefix('v1')->group(function () {
    // Open: the shop window.
    Route::get('catalog', [CatalogController::class, 'index']);
    Route::get('catalog/{chapterId}', [CatalogController::class, 'show']);

    // Open: signing in. There is no password in Razino — a phone number and
    // a code, because the players are children and the phone is a parent's.
    // Asking for a code never reveals whether that phone has an account.
    Route::post('auth/request-code', [AuthController::class, 'requestCode']);
    Route::post('auth/verify', [AuthController::class, 'verify']);

    // Signed in: what this player owns.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('entitlements', [EntitlementController::class, 'index']);
        Route::get('entitlements/{chapterId}/check', [EntitlementController::class, 'check']);
    });
});

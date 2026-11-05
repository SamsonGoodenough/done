<?php

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MediaItemController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return UserResource::make($request->user());
})->middleware('auth:sanctum');

Route::post('/invitations', [InvitationController::class, 'store'])->middleware('auth:sanctum');
Route::get('/invitations/{token}', [InvitationController::class, 'show']);
Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->middleware('auth:sanctum');
Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->middleware('auth:sanctum');

// Cataloging from an external source is open to any authenticated member;
// only manual entry is role-gated (see StoreManualMediaItemRequest). The
// search endpoint carries its own rate limit, independent of manual entry,
// purely to stop a runaway client from burning external API quota — not as
// abuse defense against a hostile user. See
// docs/adr/0001-media-source-integration-architecture.md.
Route::get('/media-items/search', [MediaItemController::class, 'search'])->middleware(['auth:sanctum', 'throttle:30,1']);
Route::post('/media-items', [MediaItemController::class, 'store'])->middleware('auth:sanctum');
Route::post('/media-items/manual', [MediaItemController::class, 'storeManual'])->middleware('auth:sanctum');

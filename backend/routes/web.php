<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Registered at /api/... (rather than under routes/api.php) so these get the
// 'web' middleware group's session + CSRF handling unconditionally: these
// all start or end a session (login, logout, accepting an invite), which
// Sanctum's api-group EnsureFrontendRequestsAreStateful only sets up for
// requests it recognizes as "from the frontend" (matching Referer/Origin).
// See docs/planning/08-architecture-deployment.md. nginx forwards /api/* to
// php-fpm regardless of which route file it's registered in.
Route::post('/api/login', [AuthController::class, 'login']);
Route::post('/api/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/api/invitations/{token}/accept', [InvitationController::class, 'accept']);

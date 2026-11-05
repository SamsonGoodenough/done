<?php

use App\Http\Controllers\InvitationController;
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

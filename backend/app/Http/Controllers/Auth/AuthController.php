<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request): UserResource
    {
        $request->authenticate();

        $request->session()->regenerate();

        return UserResource::make(Auth::user());
    }

    public function logout(Request $request): JsonResponse
    {
        // Hardcoded to 'web' rather than the default guard: auth:sanctum on
        // this route (config/sanctum.php) resolves to 'web' for every caller
        // today since nothing issues bearer tokens yet. If that changes,
        // this needs to log out whichever guard actually authenticated the
        // request instead of assuming 'web'.
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(status: 204);
    }
}

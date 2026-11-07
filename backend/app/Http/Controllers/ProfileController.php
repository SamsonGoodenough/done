<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileVisibilityRequest;
use App\Http\Resources\UserResource;

class ProfileController extends Controller
{
    public function updateVisibility(UpdateProfileVisibilityRequest $request): UserResource
    {
        $user = $request->user();
        $user->profile_visibility = $request->profileVisibility();
        $user->save();

        return UserResource::make($user);
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_path' => $this->avatar_path,
            'profile_visibility' => $this->profile_visibility,
            'roles' => $this->getRoleNames(),
            'created_at' => $this->created_at,
        ];
    }
}

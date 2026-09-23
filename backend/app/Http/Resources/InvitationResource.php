<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'invited_by' => $this->whenLoaded('inviter', fn () => $this->inviter->name),
            'expires_at' => $this->expires_at,
            'accepted_at' => $this->accepted_at,
        ];
    }
}

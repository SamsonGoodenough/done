<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FriendshipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requester_id' => $this->requester_id,
            'addressee_id' => $this->addressee_id,
            'requester' => $this->whenLoaded('requester', fn () => MemberResource::make($this->requester)),
            'addressee' => $this->whenLoaded('addressee', fn () => MemberResource::make($this->addressee)),
            'status' => $this->status,
            'created_at' => $this->created_at,
            'responded_at' => $this->responded_at,
        ];
    }
}

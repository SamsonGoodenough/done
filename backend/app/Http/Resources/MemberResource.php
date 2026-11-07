<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Another member as seen by the viewer, deliberately leaner than
 * UserResource (no email, no roles), since this is served to any
 * authenticated member about any other member, not just about yourself.
 * `relationship_status` is a transient attribute the controller sets
 * before serializing (one of null, "pending_sent", "pending_received",
 * "friends"), not a database column.
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_path' => $this->avatar_path,
            'profile_visibility' => $this->profile_visibility,
            'relationship_status' => $this->relationship_status,
        ];
    }
}

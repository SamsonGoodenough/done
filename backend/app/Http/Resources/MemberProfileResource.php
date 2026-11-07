<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member's profile page: basic info and `relationship_status` are always
 * shown (existence, name, avatar, and visibility are never gated, see
 * GLOSSARY.md "Profile Visibility"), but `shelf`/`log_entries`/`friends`
 * are only included when `can_view_content` is true, set by the
 * controller via UserPolicy::viewPrivateContent. A private profile the
 * viewer can't see still returns a 200 with these three keys simply
 * absent, not a 403: the frontend renders the "this profile is private"
 * state from `can_view_content` being false.
 */
class MemberProfileResource extends JsonResource
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
            'can_view_content' => $this->can_view_content,
            'shelf' => $this->when($this->can_view_content, fn () => UserMediaResource::collection($this->shelf)),
            'log_entries' => $this->when($this->can_view_content, fn () => LogEntryResource::collection($this->logEntries)),
            'friends' => $this->when($this->can_view_content, fn () => MemberResource::collection($this->friendsList)),
        ];
    }
}

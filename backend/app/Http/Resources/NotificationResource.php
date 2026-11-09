<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single Notification. `payload` is the type-specific data (the `data`
 * column — see GLOSSARY.md "Notification"), named differently here because a
 * top-level `data` key in a resource's own array collides with Laravel's
 * default response wrapping and silently suppresses it. `actor` is resolved
 * from `payload.from_user_id` by the controller and attached as a virtual
 * relation, not a declared Eloquent relation, since the reference lives
 * inside the JSON payload rather than a foreign key column.
 *
 * Deliberately not MemberResource: that resource's `relationship_status` is
 * a transient attribute the controller has to set before serializing (see
 * MemberController), which NotificationController never does — reusing it
 * here would silently report `relationship_status: null` for every actor,
 * read as "not friends" rather than "not computed."
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'payload' => $this->data,
            'actor' => $this->whenLoaded('actor', fn () => $this->actor ? [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
                'avatar_path' => $this->actor->avatar_path,
            ] : null),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}

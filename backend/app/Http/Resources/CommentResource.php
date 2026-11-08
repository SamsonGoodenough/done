<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single Comment. A soft-deleted Comment keeps its id/parent_comment_id
 * (so the client can still place it in the thread) but reports its body as
 * null and `is_deleted: true` — the client renders it as "[deleted]" while
 * keeping its replies in place. See GLOSSARY.md "Comment".
 */
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log_entry_id' => $this->log_entry_id,
            'parent_comment_id' => $this->parent_comment_id,
            'user' => $this->whenLoaded('user', fn () => MemberResource::make($this->user)),
            'body' => $this->trashed() ? null : $this->body,
            'is_deleted' => $this->trashed(),
            'created_at' => $this->created_at,
        ];
    }
}

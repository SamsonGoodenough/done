<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Tagging a member onto an existing Log Entry after the fact — only the
 * entry's own owner can do this, same as any other edit to it. Self-tagging
 * is allowed, matching tagged_user_ids at creation time (see
 * StoreLogEntryRequest / LogEntryControllerTest::test_tagged_user_ids_attach_participants_including_the_author).
 */
class TagParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('logEntry'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function taggedUserId(): int
    {
        return $this->integer('user_id');
    }
}

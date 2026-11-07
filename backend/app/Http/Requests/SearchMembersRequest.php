<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Member search/discovery, open to any authenticated user, regardless of
 * any member's Profile Visibility. Existence, name, and avatar are always
 * searchable; only shelf/diary/reviews/friend-list content behind a
 * private profile is gated. See GLOSSARY.md "Profile Visibility" and
 * docs/planning/03-features.md "Discoverability".
 */
class SearchMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'query' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function searchQuery(): ?string
    {
        $query = $this->string('query')->trim()->value();

        return $query === '' ? null : $query;
    }
}

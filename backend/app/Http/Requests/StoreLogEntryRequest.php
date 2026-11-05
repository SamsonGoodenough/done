<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Logging a completion — open to any authenticated user for their own
 * shelf. Create-only this phase, no edit/delete. See
 * docs/planning/04-data-model.md and
 * docs/adr/0003-shelf-status-and-logging-are-decoupled.md.
 */
class StoreLogEntryRequest extends FormRequest
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
            'media_item_id' => ['required', Rule::exists('media_items', 'id')->whereNull('deleted_at')],
            'logged_date' => ['required', 'date', 'before_or_equal:today'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5', function ($attribute, $value, $fail) {
                if ($value !== null && fmod((float) $value * 2, 1.0) !== 0.0) {
                    $fail('The rating must be in 0.5 steps.');
                }
            }],
            'review' => ['nullable', 'string'],
            'contains_spoilers' => ['nullable', 'boolean'],
            'is_favourite' => ['nullable', 'boolean'],
            'is_rewatch' => ['nullable', 'boolean'],
            'tagged_user_ids' => ['nullable', 'array'],
            'tagged_user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function mediaItemId(): int
    {
        return $this->integer('media_item_id');
    }

    public function loggedDate(): string
    {
        return $this->string('logged_date')->value();
    }

    public function rating(): ?float
    {
        return $this->has('rating') && $this->input('rating') !== null
            ? (float) $this->input('rating')
            : null;
    }

    public function review(): ?string
    {
        return $this->input('review');
    }

    public function containsSpoilers(): bool
    {
        return $this->boolean('contains_spoilers');
    }

    public function isFavourite(): ?bool
    {
        return $this->has('is_favourite') ? $this->boolean('is_favourite') : null;
    }

    public function isRewatchOverride(): ?bool
    {
        return $this->has('is_rewatch') ? $this->boolean('is_rewatch') : null;
    }

    /**
     * @return array<int, int>
     */
    public function taggedUserIds(): array
    {
        return array_map('intval', $this->input('tagged_user_ids', []));
    }
}

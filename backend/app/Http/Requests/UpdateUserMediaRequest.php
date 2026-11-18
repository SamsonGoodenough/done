<?php

namespace App\Http\Requests;

use App\Enums\ShelfStatus;
use App\Models\UserMedia;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partial update of a Shelf Entry — status and/or favourite, either
 * optional. No ordering constraint between the non-Done statuses (see
 * docs/planning/03-features.md: "doesn't force that order"). Done is a
 * special case: it stays unreachable for an entry with no Log Entries
 * (same invariant as StoreUserMediaRequest), but once at least one Log
 * Entry exists underneath, moving the shelf status to Done is exactly the
 * "separate, explicit action" ADR 0003 prescribes. Without this, an item
 * shelved as Planned before being logged could never reach Done at all,
 * since logging never touches an existing entry's status.
 */
class UpdateUserMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('userMedia'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(ShelfStatus::class)],
            'is_favourite' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->has('status') || $this->input('status') !== ShelfStatus::Done->value) {
                return;
            }

            /** @var UserMedia $userMedia */
            $userMedia = $this->route('userMedia');

            if (! $userMedia->logEntries()->exists()) {
                $validator->errors()->add(
                    'status',
                    'Done is only reachable by logging, and this entry has no log entries yet.'
                );
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $changes = [];

        if ($this->has('status')) {
            $changes['status'] = ShelfStatus::from($this->string('status')->value());
        }

        if ($this->has('is_favourite')) {
            $changes['is_favourite'] = $this->boolean('is_favourite');
        }

        return $changes;
    }
}

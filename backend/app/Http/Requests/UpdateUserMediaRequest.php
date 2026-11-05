<?php

namespace App\Http\Requests;

use App\Enums\ShelfStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partial update of a Shelf Entry — status and/or favourite, either
 * optional. No ordering constraint between the non-Completed statuses (see
 * docs/planning/03-features.md: "doesn't force that order"); Completed is
 * excluded for the same reason as StoreUserMediaRequest.
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
            'status' => ['sometimes', Rule::in($this->allowedStatuses())],
            'is_favourite' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedStatuses(): array
    {
        return array_map(
            fn (ShelfStatus $status) => $status->value,
            array_filter(ShelfStatus::cases(), fn (ShelfStatus $status) => $status !== ShelfStatus::Completed)
        );
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

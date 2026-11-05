<?php

namespace App\Http\Requests;

use App\Enums\ShelfStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding to the shelf without logging — open to any authenticated user for
 * their own shelf. Completed is deliberately excluded: it's only reachable
 * via actually logging something, see
 * docs/adr/0003-shelf-status-and-logging-are-decoupled.md.
 */
class StoreUserMediaRequest extends FormRequest
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
            'status' => ['required', Rule::in($this->allowedStatuses())],
            'is_favourite' => ['nullable', 'boolean'],
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

    public function mediaItemId(): int
    {
        return $this->integer('media_item_id');
    }

    public function status(): ShelfStatus
    {
        return ShelfStatus::from($this->string('status')->value());
    }

    public function isFavourite(): bool
    {
        return $this->boolean('is_favourite');
    }
}

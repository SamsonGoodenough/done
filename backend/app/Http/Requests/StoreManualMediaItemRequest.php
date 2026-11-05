<?php

namespace App\Http\Requests;

use App\Enums\MediaType;
use App\Enums\UserPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Manager/Admin only. Deliberately minimal: only type + title are required,
 * everything else is optional and freeform — manual entry exists precisely
 * for obscure or incomplete-info items, so forcing fields a Manager doesn't
 * have would defeat the point. No duplicate detection: external_id is
 * always null for manual items, so there's nothing to dedup against, and
 * that's an accepted risk at this project's small trusted-group scale.
 */
class StoreManualMediaItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::CreateMediaItem->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(MediaType::class)],
            'title' => ['required', 'string', 'max:255'],
            'release_date' => ['nullable', 'date'],
            'poster_url' => ['nullable', 'string', 'max:2048'],
            'overview' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function type(): MediaType
    {
        return MediaType::from($this->string('type')->value());
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Open to any authenticated user — cataloging from a real external source
 * isn't a privileged action, only manual entry is. See
 * docs/adr/0001-media-source-integration-architecture.md.
 */
class SearchMediaItemsRequest extends FormRequest
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
            'type' => ['required', Rule::enum(MediaType::class)],
            'query' => ['required', 'string'],
        ];
    }

    public function type(): MediaType
    {
        return MediaType::from($this->string('type')->value());
    }

    public function searchQuery(): string
    {
        return $this->string('query')->value();
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cataloging a result from a real external source. Open to any authenticated
 * user — only manual entry (StoreManualMediaItemRequest) is role-gated. The
 * source itself is never trusted from the client: it's derived from `type`
 * via MediaSourceResolver, since each type maps to exactly one source.
 */
class StoreMediaItemRequest extends FormRequest
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
            'external_id' => ['required', 'string'],
        ];
    }

    public function type(): MediaType
    {
        return MediaType::from($this->string('type')->value());
    }

    public function externalId(): string
    {
        return $this->string('external_id')->value();
    }
}

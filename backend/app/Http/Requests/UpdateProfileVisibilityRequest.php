<?php

namespace App\Http\Requests;

use App\Enums\ProfileVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A user changing their own Profile Visibility, always open to the
 * authenticated user for their own setting, not role-gated. See
 * GLOSSARY.md "Profile Visibility" and docs/planning/06-roles-permissions.md.
 */
class UpdateProfileVisibilityRequest extends FormRequest
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
            'profile_visibility' => ['required', Rule::enum(ProfileVisibility::class)],
        ];
    }

    public function profileVisibility(): ProfileVisibility
    {
        return ProfileVisibility::from($this->string('profile_visibility')->value());
    }
}

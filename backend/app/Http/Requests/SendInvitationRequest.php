<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SendInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::SendInvitations->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $role = UserRole::tryFrom($this->string('role')->value());

                // Deliberately hasRole(Admin) rather than a granular
                // permission: this rule is "you must hold the Admin role",
                // not a delegable capability a Manager could ever be
                // granted separately, unlike send-invitations above.
                if ($role !== null && $role !== UserRole::Member && ! $this->user()->hasRole(UserRole::Admin->value)) {
                    $validator->errors()->add('role', 'Only an Admin can invite a Manager or Admin.');
                }

                if (User::where('email', $this->string('email')->value())->exists()) {
                    $validator->errors()->add('email', 'This person is already a member.');
                }

                if (Invitation::pending()->where('email', $this->string('email')->value())->exists()) {
                    $validator->errors()->add('email', 'This person already has a pending invitation.');
                }
            },
        ];
    }
}

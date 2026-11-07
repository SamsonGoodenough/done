<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sending a friend request, open to any authenticated user. A crossing
 * request (the addressee already has a pending request to the requester)
 * is resolved by FriendshipManager, not rejected here. See GLOSSARY.md
 * "Friendship".
 */
class StoreFriendshipRequest extends FormRequest
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
            'addressee_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->addresseeId() === $this->user()->id) {
                $validator->errors()->add('addressee_id', 'You cannot send a friend request to yourself.');
            }
        });
    }

    public function addresseeId(): int
    {
        return $this->integer('addressee_id');
    }
}

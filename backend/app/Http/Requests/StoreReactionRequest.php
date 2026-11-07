<?php

namespace App\Http\Requests;

use App\Enums\ReactionEmoji;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reacting to a Review — open to any member who can view it. See
 * docs/planning/03-features.md and GLOSSARY.md "Reaction".
 */
class StoreReactionRequest extends FormRequest
{
    /**
     * Checked here, not in the controller body: Laravel resolves a
     * FormRequest's authorize() before rules()/withValidator() run, so
     * putting the visibility check here (rather than after validation, in
     * the controller) stops an unauthorized stranger from getting a 422
     * that leaks whether the log entry exists or has a review, ahead of
     * ever being authorized to know either.
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewPrivateContent', $this->route('logEntry')->userMedia->user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'emoji' => ['required', Rule::enum(ReactionEmoji::class)],
        ];
    }

    /**
     * A Reaction only makes sense on a Log Entry that actually has a Review
     * — see GLOSSARY.md "Review".
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $logEntry = $this->route('logEntry');

            if ($logEntry && $logEntry->review === null) {
                $validator->errors()->add('log_entry_id', 'Reactions can only be left on a log entry that has a review.');
            }
        });
    }

    public function emoji(): ReactionEmoji
    {
        return ReactionEmoji::from($this->string('emoji')->value());
    }
}

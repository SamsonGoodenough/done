<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Commenting on a Review — open to any member who can view it. Create-only,
 * no edit. See docs/planning/03-features.md and GLOSSARY.md "Comment".
 */
class StoreCommentRequest extends FormRequest
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
            'body' => ['required', 'string'],
            'parent_comment_id' => [
                'nullable',
                'integer',
                Rule::exists('comments', 'id')->where('log_entry_id', $this->route('logEntry')?->id),
            ],
        ];
    }

    /**
     * A Comment only makes sense on a Log Entry that actually has a Review
     * — see GLOSSARY.md "Review".
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $logEntry = $this->route('logEntry');

            if ($logEntry && $logEntry->review === null) {
                $validator->errors()->add('log_entry_id', 'Comments can only be added to a log entry that has a review.');
            }
        });
    }

    public function parentCommentId(): ?int
    {
        return $this->input('parent_comment_id') !== null ? (int) $this->input('parent_comment_id') : null;
    }

    public function body(): string
    {
        return $this->string('body')->value();
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Partial update of a Log Entry's own content — date/rating/review/spoiler
 * flag/rewatch, any subset, optional. What the entry is *about* (media
 * item, episode, season) is immutable here; only its content can change.
 * `is_favourite` is accepted too even though it actually lives on the
 * Shelf Entry (UserMedia), not this model — same write-through LogEntryRecorder
 * already does at creation time, kept here so editing a log entry doesn't
 * need a second request to the shelf endpoint for the one field a user
 * would expect to edit alongside rating/review.
 */
class UpdateLogEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('logEntry'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logged_date' => ['sometimes', 'date', 'before_or_equal:today'],
            'rating' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:5', function ($attribute, $value, $fail) {
                if ($value !== null && fmod((float) $value * 2, 1.0) !== 0.0) {
                    $fail('The rating must be in 0.5 steps.');
                }
            }],
            'review' => ['sometimes', 'nullable', 'string'],
            'contains_spoilers' => ['sometimes', 'boolean'],
            'is_favourite' => ['sometimes', 'boolean'],
            'is_rewatch' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $changes = [];

        if ($this->has('logged_date')) {
            $changes['logged_date'] = $this->string('logged_date')->value();
        }

        if ($this->has('rating')) {
            $changes['rating'] = $this->input('rating') === null ? null : (float) $this->input('rating');
        }

        if ($this->has('review')) {
            $changes['review'] = $this->input('review');
        }

        if ($this->has('contains_spoilers')) {
            $changes['contains_spoilers'] = $this->boolean('contains_spoilers');
        }

        if ($this->has('is_favourite')) {
            $changes['is_favourite'] = $this->boolean('is_favourite');
        }

        if ($this->has('is_rewatch')) {
            $changes['is_rewatch'] = $this->boolean('is_rewatch');
        }

        return $changes;
    }
}

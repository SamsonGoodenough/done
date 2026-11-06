<?php

namespace App\Http\Requests;

use App\Enums\MediaType;
use App\Models\Episode;
use App\Models\MediaItem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Logging a completion — open to any authenticated user for their own
 * shelf. Create-only this phase, no edit/delete. See
 * docs/planning/04-data-model.md and
 * docs/adr/0003-shelf-status-and-logging-are-decoupled.md.
 */
class StoreLogEntryRequest extends FormRequest
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
            'episode_id' => ['nullable', 'integer', Rule::exists('episodes', 'id')->whereNull('deleted_at')],
            'season_number' => ['nullable', 'integer', 'min:0'],
            'logged_date' => ['required', 'date', 'before_or_equal:today'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5', function ($attribute, $value, $fail) {
                if ($value !== null && fmod((float) $value * 2, 1.0) !== 0.0) {
                    $fail('The rating must be in 0.5 steps.');
                }
            }],
            'review' => ['nullable', 'string'],
            'contains_spoilers' => ['nullable', 'boolean'],
            'is_favourite' => ['nullable', 'boolean'],
            'is_rewatch' => ['nullable', 'boolean'],
            'tagged_user_ids' => ['nullable', 'array'],
            'tagged_user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    /**
     * Cross-field checks that need more than one input at once: episode_id
     * and season_number are mutually exclusive, either one requires the
     * underlying media item to be a tv_show, and each must actually belong
     * to the show being logged. See GLOSSARY.md "Logging Target".
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $episodeId = $this->episodeId();
            $seasonNumber = $this->seasonNumber();

            if ($episodeId !== null && $seasonNumber !== null) {
                $validator->errors()->add('season_number', 'season_number cannot be set together with episode_id.');

                return;
            }

            if ($episodeId === null && $seasonNumber === null) {
                return;
            }

            $mediaItem = MediaItem::find($this->input('media_item_id'));

            // media_item_id's own exists rule already failed in this case.
            if ($mediaItem === null) {
                return;
            }

            if ($mediaItem->type !== MediaType::TvShow) {
                $validator->errors()->add(
                    $episodeId !== null ? 'episode_id' : 'season_number',
                    'Only a tv_show media item can be logged at episode or season granularity.'
                );

                return;
            }

            if ($episodeId !== null) {
                $episode = Episode::find($episodeId);

                // episode_id's own exists rule already failed in this case.
                if ($episode !== null && $episode->media_item_id !== $mediaItem->id) {
                    $validator->errors()->add('episode_id', 'This episode does not belong to the show being logged.');
                }

                return;
            }

            $seasonSynced = Episode::where('media_item_id', $mediaItem->id)
                ->where('season_number', $seasonNumber)
                ->exists();

            if (! $seasonSynced) {
                $validator->errors()->add('season_number', 'This season has not been synced for the show being logged.');
            }
        });
    }

    public function mediaItemId(): int
    {
        return $this->integer('media_item_id');
    }

    public function episodeId(): ?int
    {
        return $this->has('episode_id') && $this->input('episode_id') !== null
            ? (int) $this->input('episode_id')
            : null;
    }

    public function seasonNumber(): ?int
    {
        return $this->has('season_number') && $this->input('season_number') !== null
            ? (int) $this->input('season_number')
            : null;
    }

    public function loggedDate(): string
    {
        return $this->string('logged_date')->value();
    }

    public function rating(): ?float
    {
        return $this->has('rating') && $this->input('rating') !== null
            ? (float) $this->input('rating')
            : null;
    }

    public function review(): ?string
    {
        return $this->input('review');
    }

    public function containsSpoilers(): bool
    {
        return $this->boolean('contains_spoilers');
    }

    public function isFavourite(): ?bool
    {
        return $this->has('is_favourite') ? $this->boolean('is_favourite') : null;
    }

    public function isRewatchOverride(): ?bool
    {
        return $this->has('is_rewatch') ? $this->boolean('is_rewatch') : null;
    }

    /**
     * @return array<int, int>
     */
    public function taggedUserIds(): array
    {
        return array_map('intval', $this->input('tagged_user_ids', []));
    }
}

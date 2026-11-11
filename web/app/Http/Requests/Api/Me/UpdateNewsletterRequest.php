<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Me;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH /api/v1/me/newsletter (P6-06) : `subscribed` (bool), `interests` (slugs de `newsletter_interests`, [] = aucun).
 * Chaque champ absent est laissé tel quel.
 */
final class UpdateNewsletterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subscribed' => ['sometimes', 'boolean'],
            'interests' => ['sometimes', 'array', 'max:20'],
            'interests.*' => ['string', 'distinct', Rule::exists('newsletter_interests', 'slug')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'subscribed' => __('account.newsletter.subscribed'),
            'interests' => __('profile.progressive.groups.interests.title'),
            'interests.*' => __('profile.progressive.groups.interests.title'),
        ];
    }

    public function subscribed(): ?bool
    {
        return $this->has('subscribed') ? $this->boolean('subscribed') : null;
    }

    /**
     * @return list<string>|null
     */
    public function interests(): ?array
    {
        if (! $this->has('interests')) {
            return null;
        }

        return array_values(array_map('strval', (array) $this->validated('interests', [])));
    }
}

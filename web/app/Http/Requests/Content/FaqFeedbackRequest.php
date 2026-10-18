<?php

declare(strict_types=1);

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Vote « Cet article vous a-t-il aidé·e ? » (`POST /aide/{slug}/avis`, P4-05) : `helpful` = 1 (Oui) ou 0 (Non), seul
 * champ accepté. Ouvert à tout le monde (page publique) ; message d'erreur au vous (page institutionnelle, VOICE §3).
 */
final class FaqFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'helpful' => ['required', 'in:0,1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'helpful.required' => (string) __('pages/aide.feedback.invalid'),
            'helpful.in' => (string) __('pages/aide.feedback.invalid'),
        ];
    }

    public function helpful(): bool
    {
        return (string) $this->validated('helpful') === '1';
    }
}

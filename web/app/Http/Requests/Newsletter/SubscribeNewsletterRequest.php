<?php

declare(strict_types=1);

namespace App\Http\Requests\Newsletter;

use App\Models\NewsletterInterest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Inscription à la newsletter (`POST /newsletter`, P4-07) depuis le pied de page (`nl_form=footer`) ou la page
 * `/newsletter` (`nl_form=page`) : adresse e-mail, consentement explicite, centres d'intérêt facultatifs, pot de miel
 * `website` (jamais validé : rempli, la demande est ignorée en silence par SubscribeToNewsletter). Erreurs dans le sac
 * `newsletter` (le formulaire du pied de page cohabite avec ceux des pages), retour vers la page d'origine et son
 * ancre ; l'adresse de retour `back` n'est suivie que sur ce site (pas de redirection ouverte). Saisie renvoyée sous
 * `old('newsletter.*')`, jamais à plat : un champ `email` d'une autre page (connexion, inscription, variante page de ce
 * formulaire) ne se préremplit pas avec l'adresse tapée dans le pied de page.
 */
final class SubscribeNewsletterRequest extends FormRequest
{
    /** @var list<string> */
    public const FORMS = ['footer', 'page'];

    /** Clé de la saisie renvoyée après un refus (`old('newsletter.email')`), et du sac d'erreurs. */
    public const OLD_INPUT = 'newsletter';

    /** @var string */
    protected $errorBag = self::OLD_INPUT;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $interests = $this->input('interests');

        $this->merge([
            'email' => is_string($email) ? Str::lower(trim($email)) : $email,
            'interests' => is_array($interests) ? array_values(array_unique(array_filter($interests, 'is_string'))) : [],
            'nl_form' => in_array($this->input('nl_form'), self::FORMS, true) ? $this->input('nl_form') : 'footer',
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'consent' => ['accepted'],
            'interests' => ['array', 'max:20'],
            'interests.*' => ['string', Rule::exists(NewsletterInterest::class, 'slug')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.*' => (string) __('newsletter.public.email_invalid'),
            'consent.accepted' => (string) __('newsletter.public.consent_required'),
            'interests.*' => (string) __('newsletter.public.interests_invalid'),
        ];
    }

    /** Pot de miel rempli (robot) : même réponse qu'une inscription, sans rien enregistrer. */
    public function trapped(): bool
    {
        $website = $this->input('website');

        return is_string($website) && trim($website) !== '';
    }

    /**
     * @return list<string>
     */
    public function interests(): array
    {
        /** @var list<string> $interests */
        $interests = $this->validated('interests', []);

        return array_values($interests);
    }

    /** Page d'origine du formulaire, sur ce site uniquement, avec l'ancre du formulaire. */
    public function returnUrl(): string
    {
        $back = $this->input('back');
        $anchor = $this->input('nl_form') === 'page' ? '#inscription' : '#newsletter';
        $root = rtrim($this->root(), '/');

        if (is_string($back) && $back !== '') {
            $back = Str::before($back, '#');

            if (str_starts_with($back, '/') && ! str_starts_with($back, '//') && ! str_contains($back, '\\')) {
                return $root.$back.$anchor;
            }

            if ($back === $root || str_starts_with($back, $root.'/') || str_starts_with($back, $root.'?')) {
                return $back.$anchor;
            }
        }

        return route('newsletter.show').$anchor;
    }

    /**
     * Saisie à renvoyer au formulaire (sans le pot de miel), rangée sous sa propre clé.
     *
     * @return array<string, array<string, mixed>>
     */
    public function flashableInput(): array
    {
        return [self::OLD_INPUT => $this->only(['email', 'consent', 'interests', 'nl_form'])];
    }

    protected function getRedirectUrl(): string
    {
        return $this->returnUrl();
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            parent::failedValidation($validator);
        }

        throw new HttpResponseException(redirect()->to($this->getRedirectUrl())
            ->withInput($this->flashableInput())
            ->withErrors($validator, $this->errorBag));
    }
}

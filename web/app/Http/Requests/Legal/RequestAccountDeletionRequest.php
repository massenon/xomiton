<?php

declare(strict_types=1);

namespace App\Http\Requests\Legal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Demande de suppression sans connexion (`POST /supprimer-mon-compte`) : adresse e-mail et case de confirmation ;
 * pot de miel `website` (rempli : même réponse, rien n'est fait). Erreurs renvoyées à la page, ancre du formulaire.
 */
final class RequestAccountDeletionRequest extends FormRequest
{
    protected $redirectRoute = 'account-deletion.show';

    public function authorize(): bool
    {
        return true;
    }

    protected function getRedirectUrl(): string
    {
        return route('account-deletion.show').'#demande';
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $this->merge(['email' => is_string($email) ? Str::lower(trim($email)) : $email]);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'confirm' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.*' => (string) __('pages/deletion.form.email_invalid'),
            'confirm.accepted' => (string) __('pages/deletion.form.confirm_required'),
        ];
    }

    public function trapped(): bool
    {
        $website = $this->input('website');

        return is_string($website) && trim($website) !== '';
    }
}

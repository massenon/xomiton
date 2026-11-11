<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validation d'un formulaire d'administration par des règles **partagées avec l'API** (CaseRules, CaseFormRules,
 * catalogue de validation) : le formulaire est un tableau de propriétés publiques (`form.occurred_on`…) et les règles
 * sont écrites sur les noms de colonnes. Les messages d'erreur reviennent préfixés (`form.occurred_on`), là où la vue
 * les lit ; les règles qui lisent un champ voisin (CommuneInZone sur `zone_id`) voient le tableau sans préfixe.
 */
trait ValidatesForm
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, list<mixed>>  $rules
     * @param  array<string, string>  $attributes  nom de champ => libellé
     * @return array<string, mixed> données validées
     *
     * @throws ValidationException
     */
    protected function validateForm(string $prefix, array $data, array $rules, array $attributes = []): array
    {
        // Une saisie valide efface les erreurs d'un essai précédent (le validateur n'est pas celui de Livewire).
        $this->resetErrorBag();
        $validator = Validator::make($data, $rules, [], $attributes);

        if ($validator->fails()) {
            $messages = [];

            foreach ($validator->errors()->messages() as $key => $errors) {
                $messages[$prefix.'.'.$key] = $errors;
            }

            throw ValidationException::withMessages($messages);
        }

        /** @var array<string, mixed> */
        return $validator->validated();
    }
}

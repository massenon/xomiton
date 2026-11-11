<?php

declare(strict_types=1);

namespace App\Application\Platform\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Enregistre des paramètres du catalogue (P13-04) : chaque clé est validée par ses règles (type, bornes), écrite avec
 * son groupe, son type et sa visibilité, puis journalisée (`settings.updated` : clés, anciennes et nouvelles valeurs,
 * jamais celles d'un secret). Une clé hors catalogue est refusée ; un secret laissé vide reste inchangé.
 * Permission `settings.manage` (superadmin) pour l'écran Paramètres ; `$permission` laisse l'écran Synchronisation
 * (`sync.manage`) écrire ses seuls paramètres de synchro.
 */
final class UpdateSettings
{
    /**
     * @param  array<string, mixed>  $input  clé => valeur brute
     * @return list<string> clés réellement modifiées
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $by, array $input, string $permission = 'settings.manage'): array
    {
        if (! $by->can($permission)) {
            throw new AuthorizationException;
        }

        $definitions = SettingsCatalog::all();
        $rules = [];
        $attributes = [];

        foreach (array_keys($input) as $key) {
            $definition = $definitions[$key] ?? null;

            if ($definition === null) {
                throw ValidationException::withMessages([(string) $key => (string) __('admin_system.settings.unknown')]);
            }

            $rules[$key] = $definition->rules;

            if ($definition->itemRules !== []) {
                $rules[$key.'.*'] = $definition->itemRules;
            }

            $attributes[$key] = (string) __('admin_system.settings.fields.'.$key);
        }

        if (array_key_exists('password_min_length', $rules)) {
            $rules['password_min_length'][] = 'min:'.PasswordPolicy::floor();
        }

        $validator = Validator::make($input, $rules, [], $attributes);
        $validator->after(function ($validator) use ($input): void {
            if (array_key_exists('languages', $input) && is_array($input['languages']) && ! in_array('fr', $input['languages'], true)) {
                $validator->errors()->add('languages', (string) __('admin_system.settings.french_required'));
            }
        });
        $validated = $validator->validate();

        $changes = [];

        DB::transaction(function () use ($by, $validated, $definitions, &$changes): void {
            foreach ($validated as $key => $raw) {
                $definition = $definitions[$key];

                if ($definition->isSecret() && ($raw === null || $raw === '')) {
                    continue;
                }

                $value = self::cast($definition, $raw);
                $before = $definition->isSecret() ? null : SettingsCatalog::value($key);

                if (! $definition->isSecret() && $before === $value) {
                    continue;
                }

                $setting = Setting::query()->firstOrNew(['key' => $key]);
                $setting->forceFill([
                    'group' => $definition->group,
                    'type' => $definition->type,
                    'is_public' => $definition->public,
                    'updated_by' => $by->getKey(),
                ]);
                $setting->value = $value;
                $setting->save();

                $changes[$key] = $definition->isSecret() ? ['modifié'] : [$before, $value];
            }

            if ($changes !== []) {
                Audit::logWith('settings.updated', ['keys' => array_keys($changes), 'changes' => $changes], null, $by);
            }
        });

        Setting::flushCache();

        return array_keys($changes);
    }

    private static function cast(SettingDefinition $definition, mixed $raw): mixed
    {
        return match ($definition->type) {
            'bool' => (bool) $raw,
            'int' => (int) $raw,
            'json' => array_values(array_map('strval', (array) $raw)),
            default => $raw === null ? '' : trim((string) $raw),
        };
    }
}

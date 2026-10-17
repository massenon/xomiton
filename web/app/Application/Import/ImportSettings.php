<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Models\Setting;
use App\Models\ValidationRule;

/**
 * settings.json (un objet clé / valeur) → settings typés. Les objets imbriqués sont éclatés : `smtp`
 * → `smtp_host`, `smtp_port`… (mot de passe en secret) ; `rules_off`, `plausibility`, `dup_fields`,
 * `dup_enabled` alimentent validation_rules (dictionnaire) ; `gateway_chat` relève des salons pair·es
 * (peer_rooms) et est ignoré ; les clés `stock*` sont ignorées. Une clé déjà semée garde son groupe,
 * son type et sa visibilité ; une clé inconnue est créée dans le groupe `general` avec un type déduit.
 */
final class ImportSettings extends AbstractImporter
{
    /** @var array<string, array{0: string, 1: string}> champ du prototype → clé, type */
    private const SMTP_FIELDS = [
        'host' => ['smtp_host', Setting::TYPE_STRING],
        'port' => ['smtp_port', Setting::TYPE_INT],
        'secure' => ['smtp_encryption', Setting::TYPE_STRING],
        'encryption' => ['smtp_encryption', Setting::TYPE_STRING],
        'user' => ['smtp_username', Setting::TYPE_STRING],
        'username' => ['smtp_username', Setting::TYPE_STRING],
        'pass' => ['smtp_password', Setting::TYPE_SECRET],
        'password' => ['smtp_password', Setting::TYPE_SECRET],
        'from_email' => ['smtp_from_email', Setting::TYPE_STRING],
        'from' => ['smtp_from_email', Setting::TYPE_STRING],
        'from_name' => ['smtp_from_name', Setting::TYPE_STRING],
        'reply_to' => ['smtp_reply_to', Setting::TYPE_STRING],
    ];

    public static function collection(): string
    {
        return 'settings';
    }

    /**
     * Le fichier contient un seul objet (clé → valeur) : chaque clé devient une « ligne » importée.
     *
     * @return list<array<string, mixed>>
     */
    protected function rows(): array
    {
        $rows = [];

        foreach (parent::rows() as $object) {
            foreach ($object as $key => $value) {
                $rows[] = ['id' => (string) $key, 'key' => (string) $key, 'value' => $value];
            }
        }

        return $rows;
    }

    protected function importRow(array $row, int $index): void
    {
        $key = $this->required($row['key'] ?? null, 'key', 80);
        $value = $row['value'] ?? null;

        if (PrototypeData::isIgnored($key)) {
            $this->ctx->note(sprintf('paramètre « %s » ignoré (stocks du prototype hors périmètre)', $key));

            return;
        }

        switch ($key) {
            case 'smtp':
                $this->importSmtp(is_array($value) ? $value : []);

                return;
            case 'gateway_chat':
                $this->ctx->note('paramètre « gateway_chat » ignoré : les salons pair·es sont gérés dans peer_rooms');

                return;
            case 'rules_off':
                $this->importRulesOff(is_array($value) ? $value : []);

                return;
            case 'plausibility':
                $this->importRuleParams(ValidationRule::KEY_PLAUSIBILITY, is_array($value) ? $value : []);

                return;
            case 'dup_fields':
                $this->importRuleParams(ValidationRule::KEY_DUPLICATES, ['fields' => array_values((array) $value)]);

                return;
            case 'dup_enabled':
                $this->importRuleActive(ValidationRule::KEY_DUPLICATES, (bool) $value);

                return;
        }

        $this->upsert($key, $value, Setting::GROUP_GENERAL, Setting::inferType($value), false);
    }

    /** @param  array<string, mixed>  $smtp */
    private function importSmtp(array $smtp): void
    {
        foreach ($smtp as $field => $value) {
            if (! isset(self::SMTP_FIELDS[$field])) {
                $this->ctx->note(sprintf('paramètre smtp.%s sans équivalent, ignoré', (string) $field));

                continue;
            }

            [$key, $type] = self::SMTP_FIELDS[$field];

            if ($type === Setting::TYPE_INT) {
                $value = is_numeric($value) ? (int) $value : null;
            }

            $this->upsert($key, $value, Setting::GROUP_SMTP, $type, false);
        }
    }

    private function upsert(string $key, mixed $value, string $group, string $type, bool $isPublic): void
    {
        $setting = Setting::query()->firstOrNew(['key' => $key]);

        if (! $setting->exists) {
            $setting->fill(['group' => $group, 'type' => $type, 'is_public' => $isPublic]);
            $this->ctx->note(sprintf('paramètre « %s » inconnu du seeder : créé dans le groupe %s (type %s)', $key, $group, $type));
        }

        $setting->value = $value;
        $setting->save();

        $this->track($setting);
    }

    /** @param  list<mixed>  $keys */
    private function importRulesOff(array $keys): void
    {
        $off = array_map('strval', $keys);

        foreach (ValidationRule::query()->get() as $rule) {
            $rule->forceFill(['is_active' => $rule->is_locked || ! in_array($rule->key, $off, true)])->save();
        }

        $this->ctx->report->updated(self::collection());
    }

    /** @param  array<string, mixed>  $params */
    private function importRuleParams(string $key, array $params): void
    {
        $rule = ValidationRule::query()->withKey($key)->first();

        if ($rule === null) {
            $this->reject(sprintf('règle de validation « %s » absente (seeder non exécuté)', $key));
        }

        $rule->forceFill(['params' => array_merge($rule->params ?? [], $params)])->save();
        $this->ctx->report->updated(self::collection());
    }

    private function importRuleActive(string $key, bool $active): void
    {
        $rule = ValidationRule::query()->withKey($key)->first();

        if ($rule === null) {
            $this->reject(sprintf('règle de validation « %s » absente (seeder non exécuté)', $key));
        }

        $rule->forceFill(['is_active' => $rule->is_locked || $active])->save();
        $this->ctx->report->updated(self::collection());
    }
}

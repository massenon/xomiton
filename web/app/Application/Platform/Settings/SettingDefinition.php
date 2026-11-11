<?php

declare(strict_types=1);

namespace App\Application\Platform\Settings;

/**
 * Définition d'une clé de paramètre (config/xomiton.php → platform_settings) : groupe, type stocké, valeur par défaut,
 * exposition publique et règles de validation.
 */
final readonly class SettingDefinition
{
    /**
     * @param  list<string>  $rules
     * @param  list<string>  $itemRules
     */
    public function __construct(
        public string $key,
        public string $group,
        public string $type,
        public mixed $default,
        public bool $public,
        public array $rules,
        public array $itemRules = [],
    ) {}

    public function isSecret(): bool
    {
        return $this->type === 'secret';
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Statut facultatif du profil progressif (Q10 c) : allié·e ou personne ressource, sans aucun droit associé
 * (`profiles.ally_status`, chiffré). Absent = « aucun des deux ».
 */
enum AllyStatus: string
{
    case Ally = 'allie';
    case Resource = 'ressource';

    public function label(): string
    {
        return __('enums/identity.ally_status.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

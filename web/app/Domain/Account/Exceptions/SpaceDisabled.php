<?php

declare(strict_types=1);

namespace App\Domain\Account\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Espace de l'application mobile coupé par l'interrupteur serveur (P15-22, MobileSpaces) : 403 `space_disabled` sur les
 * routes `admin/*` et `pair/*` appelées avec le jeton de l'application. L'application relit `GET me` (l'espace n'y figure
 * plus) et retire l'espace du sélecteur. Le site web n'est jamais touché.
 */
final class SpaceDisabled extends DomainException
{
    public function __construct()
    {
        parent::__construct('account.spaces.disabled');
    }

    public function errorCode(): string
    {
        return 'space_disabled';
    }

    public function status(): int
    {
        return 403;
    }
}

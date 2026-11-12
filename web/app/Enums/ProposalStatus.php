<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Statut d'une ressource proposée par un·e membre (P18-12 ; `resource_proposals.status`).
 */
enum ProposalStatus: string
{
    case Proposed = 'proposee';
    case Accepted = 'acceptee';
    case Refused = 'refusee';

    public function label(): string
    {
        return (string) __('enums/content.proposal_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Proposed => 'info',
            self::Accepted => 'success',
            self::Refused => 'secondary',
        };
    }
}

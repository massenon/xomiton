<?php

declare(strict_types=1);

namespace App\Domain\Newsletter\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Refus métier des écrans Templates e-mails et Newsletter (P14-01, P14-02) : slug de template système mal saisi, campagne
 * déjà partie, segment vide, import sans attestation… Le message vient de lang/fr/admin_newsletter.php (`errors.<raison>`), 422.
 */
final class CommunicationRefused extends DomainException
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public function __construct(private readonly string $reason, array $replace = [])
    {
        parent::__construct('admin_newsletter.errors.'.$reason, $replace);
    }

    public function errorCode(): string
    {
        return $this->reason;
    }
}

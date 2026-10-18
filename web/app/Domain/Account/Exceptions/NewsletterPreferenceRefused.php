<?php

declare(strict_types=1);

namespace App\Domain\Account\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * PATCH me/newsletter refusé (P6-06), 422 : adresse en rebond (`newsletter_bounced` : jamais réactivée, Q66), compte
 * sans e-mail (`newsletter_email_missing`), centres d'intérêt sans abonnement (`newsletter_not_subscribed`).
 */
final class NewsletterPreferenceRefused extends DomainException
{
    private function __construct(private readonly string $stableCode, string $langKey, private readonly ?string $field = null)
    {
        parent::__construct($langKey);
    }

    public static function bounced(): self
    {
        return new self('newsletter_bounced', 'account.newsletter.bounced', 'subscribed');
    }

    public static function emailMissing(): self
    {
        return new self('newsletter_email_missing', 'account.newsletter.email_missing', 'subscribed');
    }

    public static function notSubscribed(): self
    {
        return new self('newsletter_not_subscribed', 'account.newsletter.not_subscribed', 'interests');
    }

    public function errorCode(): string
    {
        return $this->stableCode;
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->field === null ? [] : [$this->field => [$this->userMessage()]];
    }
}

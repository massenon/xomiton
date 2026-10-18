<?php

declare(strict_types=1);

namespace App\Http\Requests\Newsletter;

use App\Application\Newsletter\NewsletterLinks;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Clic suivi d'une campagne (`GET /newsletter/clic/{recipient}/{signature}?url=`, P4-07) : la signature HMAC couvre le
 * destinataire et l'URL cible (http ou https). Signature absente, fausse ou URL modifiée : 403 en français, jamais de
 * redirection (pas de redirection ouverte).
 */
final class TrackedClickRequest extends FormRequest
{
    public function authorize(NewsletterLinks $links): bool
    {
        return $links->verifyClick($this->recipientId(), $this->targetUrl(), (string) $this->route('signature'));
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function recipientId(): int
    {
        return (int) $this->route('recipient');
    }

    public function targetUrl(): string
    {
        $url = $this->query('url');

        return is_string($url) ? trim($url) : '';
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException((string) __('newsletter.public.invalid_tracking_link'));
    }
}

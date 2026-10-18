<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Newsletter\Support;

use App\Application\Newsletter\ConfirmSubscription;
use App\Domain\Newsletter\SubscriptionPageState;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Response;

/**
 * Pages publiques portées par le jeton d'un·e abonné·e (confirmation, désinscription ; P4-07) : recherche du jeton
 * (32 hexadécimaux, sinon inconnu) et page d'état commune `public.newsletter.status`, jamais indexée ni mise en cache,
 * sans Referer vers un autre site (le jeton est dans l'adresse).
 */
final class SubscriptionPage
{
    /** @var array<string, string> état → clé du titre (lang/fr/newsletter.php `public`) */
    private const TITLES = [
        'confirm' => 'confirm_title',
        'confirmed' => 'confirmed_title',
        'expired' => 'expired_title',
        'unsubscribe' => 'unsubscribe_title',
        'unsubscribed' => 'unsubscribed_title',
        'invalid' => 'invalid_link_title',
    ];

    private function __construct() {}

    public static function title(SubscriptionPageState $state): string
    {
        return (string) __('newsletter.public.'.self::TITLES[$state->value]);
    }

    public static function find(string $token): ?NewsletterSubscriber
    {
        return strlen($token) === NewsletterSubscriber::TOKEN_LENGTH && ctype_xdigit($token)
            ? NewsletterSubscriber::query()->whereToken(strtolower($token))->first()
            : null;
    }

    public static function render(SubscriptionPageState $state, ?NewsletterSubscriber $subscriber, string $token, ?int $campaignId = null): Response
    {
        return response()->view('public.newsletter.status', [
            'state' => $state,
            'email' => $state === SubscriptionPageState::Invalid ? null : $subscriber?->email,
            'token' => $token,
            'campaignId' => $campaignId,
            'ttlHours' => ConfirmSubscription::ttlHours(),
            'title' => self::title($state),
            'seo' => ['title' => self::title($state), 'index' => false],
        ], $state->httpStatus())->withHeaders([
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}

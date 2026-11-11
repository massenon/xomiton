<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Newsletter;

use App\Application\Newsletter\ConfirmSubscription;
use App\Domain\Newsletter\SubscriptionPageState;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Newsletter\Support\SubscriptionPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Confirmation du double opt-in (`/newsletter/confirmer/{jeton}`, P4-07, noindex, exclue par robots.txt) : le GET
 * n'affiche que le bouton « Confirmer mon inscription », le POST (CSRF) active l'abonnement puis revient au GET.
 * Jeton inconnu : 404 « Lien invalide ou expiré » ; lien de plus de 48 h : 410.
 */
final class NewsletterConfirmationController extends Controller
{
    public function show(string $token, ConfirmSubscription $confirm): Response
    {
        $subscriber = SubscriptionPage::find($token);

        return SubscriptionPage::render($confirm->state($subscriber), $subscriber, $token);
    }

    public function store(string $token, ConfirmSubscription $confirm): RedirectResponse|Response
    {
        $subscriber = SubscriptionPage::find($token);
        $state = $confirm($subscriber);

        if ($state === SubscriptionPageState::Confirmed) {
            return redirect()->route('newsletter.confirm', ['token' => $token], 303);
        }

        return SubscriptionPage::render($state, $subscriber, $token);
    }
}

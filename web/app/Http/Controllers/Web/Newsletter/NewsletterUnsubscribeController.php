<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Newsletter;

use App\Application\Newsletter\UnsubscribeFromNewsletter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Newsletter\Support\SubscriptionPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Désinscription en un clic (`/newsletter/desinscription/{jeton}[?c=campagne]`, P4-07, RG-12, noindex, exclue par
 * robots.txt) : le GET affiche l'adresse et le motif facultatif, le POST désinscrit. POST sans jeton CSRF : c'est
 * l'adresse `List-Unsubscribe` que les messageries appellent en « one-click » (RFC 8058, corps
 * `List-Unsubscribe=One-Click`, sans cookie) ; le jeton de 128 bits de l'adresse suffit. One-click : 200 sans
 * redirection ; navigateur : 303 vers la page « Vous êtes désinscrit·e ».
 */
final class NewsletterUnsubscribeController extends Controller
{
    public function show(Request $request, string $token, UnsubscribeFromNewsletter $unsubscribe): Response
    {
        $subscriber = SubscriptionPage::find($token);

        return SubscriptionPage::render($unsubscribe->state($subscriber), $subscriber, $token, self::campaignId($request));
    }

    public function store(Request $request, string $token, UnsubscribeFromNewsletter $unsubscribe): RedirectResponse|Response
    {
        $subscriber = SubscriptionPage::find($token);
        $reason = $request->input('reason');
        $state = $unsubscribe($subscriber, is_string($reason) ? $reason : null, self::campaignId($request));

        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return response(SubscriptionPage::title($state), $state->httpStatus(), [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store, private',
            ]);
        }

        if ($subscriber === null) {
            return SubscriptionPage::render($state, null, $token);
        }

        return redirect()->route('newsletter.unsubscribe', ['token' => $token], 303);
    }

    private static function campaignId(Request $request): ?int
    {
        $id = filter_var($request->input('c'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }
}

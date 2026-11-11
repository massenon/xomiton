<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Newsletter;

use App\Application\Newsletter\SubscribeToNewsletter;
use App\Enums\SubscriberSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Newsletter\SubscribeNewsletterRequest;
use App\Models\NewsletterInterest;
use App\Support\NamedRateLimit;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Newsletter publique (P4-07, vous) : page `/newsletter` (indexable, formulaire avec centres d'intérêt), inscription
 * `POST /newsletter` (pied de page ou page) et page de remerciement `/newsletter/merci` (noindex).
 * Anti-énumération (DEVELOPMENT_STANDARDS.md §7) : toute demande valide reçoit la même redirection vers la même page de
 * remerciement, après la même durée (SubscribeToNewsletter), que l'adresse soit inconnue, en attente, déjà abonnée ou
 * que le pot de miel soit rempli. Limiteur nommé `newsletter` (1 / 20 s par session, sinon par IP ; 10 / h par IP ;
 * en empreinte), consommé après validation.
 */
final class NewsletterController extends Controller
{
    /** Clé de session (flash) du message « trop rapide » du formulaire. */
    public const FLASH = 'newsletter_flash';

    public function show(): Response
    {
        return response()->view('public.newsletter.show', [
            'interests' => NewsletterInterest::query()->orderBy('id')->pluck('label', 'slug')->all(),
            'seo' => [
                'title' => __('seo.pages.newsletter.title'),
                'description' => __('seo.pages.newsletter.description'),
                'breadcrumb' => [[__('newsletter.public.page_title'), route('newsletter.show')]],
            ],
        ]);
    }

    public function store(SubscribeNewsletterRequest $request, SubscribeToNewsletter $subscribe): RedirectResponse
    {
        if (($retryAfter = NamedRateLimit::attempt('newsletter', $request)) !== null) {
            // Plafond par session (20 s) : « quelques secondes » ; plafond par IP (heure) : le délai en minutes.
            $message = $retryAfter > 60
                ? (string) __('newsletter.public.too_many', ['minutes' => (int) ceil($retryAfter / 60)])
                : (string) __('newsletter.public.too_fast');

            if ($request->expectsJson()) {
                throw new ThrottleRequestsException($message, null, ['Retry-After' => $retryAfter]);
            }

            return redirect()->to($request->returnUrl(), 303)
                ->withInput($request->flashableInput())
                ->with(self::FLASH, ['ok' => false, 'message' => $message, 'form' => $request->input('nl_form')])
                ->withHeaders(['Retry-After' => (string) $retryAfter]);
        }

        $email = (string) $request->validated('email');
        $user = $request->user();

        $subscribe(
            email: $email,
            interests: $request->interests(),
            source: SubscriberSource::Footer,
            userId: $user !== null && mb_strtolower((string) $user->email) === $email ? (int) $user->getKey() : null,
            trapped: $request->trapped(),
        );

        return redirect()->route('newsletter.thanks', status: 303);
    }

    public function thanks(): Response
    {
        return response()->view('public.newsletter.thanks', [
            'seo' => ['title' => __('newsletter.public.subscribed_title'), 'index' => false],
        ])->withHeaders(['X-Robots-Tag' => 'noindex, nofollow']);
    }
}

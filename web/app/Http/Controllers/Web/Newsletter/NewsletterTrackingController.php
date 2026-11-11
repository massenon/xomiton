<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Newsletter;

use App\Application\Newsletter\NewsletterLinks;
use App\Application\Newsletter\TrackCampaignEngagement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Newsletter\TrackedClickRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Suivi des campagnes (P4-07, ADR-14 ; prototype `a=open` / `a=click`), sans session ni cookie :
 *  - pixel `GET /newsletter/ouverture/{recipient}/{signature}` : GIF 1×1 transparent dans tous les cas (aucun oracle),
 *    ouverture enregistrée seulement si la signature est valide ;
 *  - clic `GET /newsletter/clic/{recipient}/{signature}?url=` : signature HMAC vérifiée par TrackedClickRequest (403
 *    sinon), clic enregistré (un clic vaut ouverture) puis 302 vers la cible signée.
 */
final class NewsletterTrackingController extends Controller
{
    /** GIF 1×1 transparent (prototype newsletter.php). */
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /** @var array<string, string> */
    private const PRIVATE_HEADERS = ['Cache-Control' => 'no-store, private', 'X-Robots-Tag' => 'noindex, nofollow', 'Referrer-Policy' => 'no-referrer'];

    public function open(int $recipient, string $signature, NewsletterLinks $links, TrackCampaignEngagement $tracking): Response
    {
        if ($links->verifyOpen($recipient, $signature)) {
            $tracking->open($recipient);
        }

        return response((string) base64_decode(self::PIXEL, true), 200, ['Content-Type' => 'image/gif'] + self::PRIVATE_HEADERS);
    }

    public function click(TrackedClickRequest $request, TrackCampaignEngagement $tracking): RedirectResponse
    {
        $tracking->click($request->recipientId(), $request->targetUrl());

        return redirect()->away($request->targetUrl(), 302, self::PRIVATE_HEADERS);
    }
}

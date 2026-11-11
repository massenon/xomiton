<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Feed;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GET /p/{ulid} (P7-02, Q31 b) : lien de partage d'une publication (WhatsApp, Telegram, Facebook, copier, FR-SOC-03).
 * Visiteur·se : page neutre (aucun contenu, aucun pseudonyme, noindex), **sans lecture en base** — la réponse est la même
 * que la publication existe ou non, soit visible ou non (pas d'énumération). Personne connectée : redirection vers le
 * détail `/fil/{ulid}`, qui applique la visibilité de la publication.
 */
final class SharedPostController extends Controller
{
    public function __invoke(Request $request, string $ulid): Response|RedirectResponse
    {
        $detail = route('fil.show', ['post' => $ulid], false);

        if ($request->user() !== null) {
            return redirect()->to($detail);
        }

        return response()->view('public.partage', [
            'seo' => [
                'title' => __('pages/partage.title'),
                'description' => __('pages/partage.description'),
                'index' => false,
            ],
            'loginUrl' => route('connexion', ['next' => $detail]),
            'registerUrl' => route('inscription'),
        ])->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }
}

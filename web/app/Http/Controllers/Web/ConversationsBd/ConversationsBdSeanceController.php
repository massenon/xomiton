<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\ConversationsBd;

use App\Application\Content\ConversationsBd\ConversationsBdCatalog;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Mode séance d'une conversation en BD `/conversations-bd/{slug}/seance` (P20-10, PRD P0-5) pour les pair·es
 * animateur·rices : plein écran, une case à la fois, d'abord toutes les bulles sauf les pensées, puis « Révéler les
 * pensées », questions d'animation à la fin ; clavier et télécommande de présentation (flèches, Page suivante / précédente,
 * Espace, Entrée, P, F). Sans JavaScript : toutes les cases, pensées comprises, puis les questions. Publiée seulement
 * (sinon 404 neutre) ; jamais indexée (la page de lecture est la page canonique).
 */
final class ConversationsBdSeanceController extends Controller
{
    public function __invoke(string $slug, ConversationsBdCatalog $catalog): View
    {
        $conversation = $catalog->find($slug);
        abort_if($conversation === null, 404);

        return view('public.conversations-bd.seance', [
            'conversation' => $conversation,
            'panels' => $catalog->panels($conversation),
            'names' => $catalog->names(),
            'seo' => [
                'title' => __('pages/conversations_bd.seance.meta_title', ['title' => $conversation->title]),
                'description' => (string) $conversation->summary,
                'index' => false,
            ],
        ]);
    }
}

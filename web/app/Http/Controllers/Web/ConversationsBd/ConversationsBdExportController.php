<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\ConversationsBd;

use App\Application\Content\ConversationsBd\ConversationPdf;
use App\Application\Content\ConversationsBd\ConversationsBdCatalog;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Export PDF public d'une conversation en BD `/conversations-bd/{slug}/pdf` (P20-09, PRD P0-8) : conversations
 * **publiées** seulement (sinon 404 neutre, comme la page) ; A4, cases et bulles en vectoriel, texte sélectionnable, message
 * clé, questions d'animation, source et adresse (ConversationPdf). Lien depuis la page publique et le mode séance.
 * Jamais indexé (X-Robots-Tag) : la page HTML est la page canonique. Les images PNG sont produites dans le navigateur
 * (public/bd-export.js), sans appel au serveur.
 */
final class ConversationsBdExportController extends Controller
{
    public function __invoke(string $slug, ConversationsBdCatalog $catalog, ConversationPdf $pdf): Response
    {
        $conversation = $catalog->find($slug);
        abort_if($conversation === null, 404);

        return response($pdf->render($conversation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.ConversationPdf::filename($conversation).'"',
            'Cache-Control' => 'public, max-age=600',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}

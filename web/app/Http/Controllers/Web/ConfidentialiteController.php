<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Auth\ConsentVersion;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Charte, confidentialité et conditions `/confidentialite` (P4-08, prototype privacy-and-terms.php) : publique et
 * indexable, gabarit public pour tout le monde (le prototype forçait déjà le public), vous. Quatre sections ancrées
 * `#charte`, `#confidentialite`, `#conditions`, `#signalement` (liens du pied de page, de l'inscription et du
 * formulaire de la newsletter), sous-navigation collante. Textes : lang/fr/pages/confidentialite.php ; « Dernière mise à
 * jour » : `xomiton.content.legal_updated_at` ; version affichée = celle tracée avec les consentements (ConsentVersion).
 */
final class ConfidentialiteController extends Controller
{
    /** @var list<array{0: string, 1: string}> ancre => icône Bootstrap, dans l'ordre du prototype */
    public const SECTIONS = [
        ['charte', 'bi-heart'],
        ['confidentialite', 'bi-shield-lock'],
        ['conditions', 'bi-file-text'],
        ['signalement', 'bi-flag'],
    ];

    public function __invoke(): View
    {
        $updatedAt = (string) config('xomiton.content.legal_updated_at', '2026-10-18');

        return view('public.confidentialite.index', [
            'sections' => self::SECTIONS,
            'updatedAt' => $updatedAt,
            'version' => ConsentVersion::current(),
            'seo' => [
                'title' => __('seo.pages.confidentialite.title'),
                'description' => __('seo.pages.confidentialite.description'),
                'modified' => $updatedAt,
                'breadcrumb' => [[__('pages/confidentialite.title'), route('confidentialite')]],
            ],
        ]);
    }
}

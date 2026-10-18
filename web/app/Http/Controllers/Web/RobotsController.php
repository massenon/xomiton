<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\Response;

/**
 * `/robots.txt` servi dynamiquement (P4-11, SEO_CONTENT_GUIDE_INSTRUCTION.md §1) : en production, l'exploration est
 * autorisée sauf sur l'administration, l'API, le stockage, les pages de newsletter, l'OTP (`/code-de-verification`,
 * `/otp` réservé) et la recherche interne, et le sitemap est annoncé. Hors production (local, testing, recette) ou si le paramètre
 * `seo.allow_indexing` est à faux (config xomiton.seo.allow_indexing / SEO_ALLOW_INDEXING par défaut), tout est interdit.
 */
final class RobotsController extends Controller
{
    /** @var list<string> Chemins jamais explorés. */
    public const DISALLOWED = [
        '/admin',
        '/api',
        '/storage',
        '/newsletter/confirmer',
        '/newsletter/desinscription',
        '/otp',
        '/code-de-verification',
        '/recherche',
    ];

    public function __invoke(): Response
    {
        $lines = ['User-agent: *'];

        if (self::indexable()) {
            foreach (self::DISALLOWED as $path) {
                $lines[] = 'Disallow: '.$path;
            }

            $lines[] = '';
            $lines[] = 'Sitemap: '.rtrim((string) config('app.url'), '/').'/sitemap.xml';
        } else {
            $lines[] = 'Disallow: /';
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Le site accepte-t-il d'être indexé ? Production seulement, et paramètre `seo.allow_indexing` vrai.
     */
    public static function indexable(): bool
    {
        if (! app()->environment('production')) {
            return false;
        }

        return Settings::bool('seo.allow_indexing', (bool) config('xomiton.seo.allow_indexing', true));
    }
}

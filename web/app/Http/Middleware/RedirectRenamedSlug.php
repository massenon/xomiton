<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Resource;
use App\Models\SlugRedirect;
use App\Models\Thematique;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirection 301 d'un ancien slug (P12-03) : sur `/thematiques/{slug}`, `/ressources/{slug}` et `/actualites/{slug}`,
 * quand la page répond 404 et que le slug figure dans `slug_redirects`, le visiteur est renvoyé vers la fiche actuelle
 * **publiée** (jamais vers un brouillon). Paramètre : `thematique` ou `resource` (`->middleware(RedirectRenamedSlug::class.':resource')`).
 * Une fiche qui répond 200 n'est jamais touchée ; sans historique, le 404 reste un 404.
 */
final class RedirectRenamedSlug
{
    public function handle(Request $request, Closure $next, string $type = SlugRedirect::TYPE_RESOURCE): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 404) {
            return $response;
        }

        $slug = (string) $request->route('slug');
        $target = $slug === '' ? null : $this->target($type, $slug);

        return $target === null ? $response : redirect()->to($target, 301);
    }

    private function target(string $type, string $slug): ?string
    {
        $subjectId = SlugRedirect::query()->where('subject_type', $type)->where('old_slug', $slug)->value('subject_id');

        if ($subjectId === null) {
            return null;
        }

        if ($type === SlugRedirect::TYPE_THEMATIQUE) {
            $thematique = Thematique::query()->published()->whereKey($subjectId)->first(['id', 'slug']);

            return $thematique === null ? null : route('thematiques.show', $thematique->slug);
        }

        $resource = Resource::query()->published()->whereKey($subjectId)->first(['id', 'slug']);

        return $resource === null ? null : route('ressources.show', $resource->slug);
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Models\Resource;

/**
 * Bibliothèque hors connexion des ressources (P4-03, ADR-26 ; prototype ressources.php l.99-125) : interrupteur « Hors
 * ligne » par ressource, marquage local par appareil (composant Alpine `xmOfflineLibrary`, resources/js/public/
 * ressources.js), et ressources marquées par l'équipe (`is_offline_available`, badge « Disponible hors connexion »,
 * précachées pour tout le monde par P5-10). Contrat avec le service worker (P5-10) : attributs `data-offline-url` (page)
 * et `data-offline-file` (fichier : `/lire` pour un média, `/telecharger` sinon) des interrupteurs, liste `marked` des
 * options, événement `window` `xm:offline-resources` ({action, slug, item, items, marked}), clé localStorage
 * `xm_offline_res_v1` (objets indexés par slug), en-tête `Purpose: prefetch` sur ses requêtes de mise en cache (non
 * comptées, CountableVisit). Activée par défaut depuis P5-10 (`xomiton.offline.library_enabled`, XOMITON_OFFLINE_LIBRARY) ;
 * désactivée : ni interrupteur ni badge, aucune promesse de lecture sans réseau. Sans service worker dans le navigateur,
 * le composant masque l'interrupteur (`supported`).
 */
final class OfflineLibrary
{
    public const STORAGE_KEY = 'xm_offline_res_v1';

    public const EVENT = 'xm:offline-resources';

    /** Ressources marquées listées en mode « enregistrées seulement » (quelques fiches de crise en pratique). */
    private const MARKED_MAX = 50;

    public static function enabled(): bool
    {
        return (bool) config('xomiton.offline.library_enabled', true);
    }

    /** Fichier à mettre en cache : la source du lecteur pour un média, le téléchargement stable sinon, ou rien. */
    public static function fileUrl(Resource $resource): ?string
    {
        return match (true) {
            $resource->isPlayable() => route('ressources.stream', $resource->slug),
            $resource->isDownloadable() => route('ressources.download', $resource->slug),
            default => null,
        };
    }

    /**
     * Ressources du catalogue marquées par l'équipe, les plus récentes : visibles en mode « enregistrées seulement »
     * même quand leur carte n'est pas chargée, et comptées dans le compteur.
     *
     * @return list<array{slug: string, url: string, file: string|null, title: string}>
     */
    public static function marked(): array
    {
        return Resource::query()->catalog()->offlineAvailable()
            ->orderByDesc('published_at')
            ->orderBy('id')
            ->limit(self::MARKED_MAX)
            ->get(['id', 'slug', 'title', 'kind', 'file_path'])
            ->map(static fn (Resource $resource): array => [
                'slug' => (string) $resource->slug,
                'url' => route('ressources.show', $resource->slug),
                'file' => self::fileUrl($resource),
                'title' => (string) $resource->title,
            ])
            ->values()
            ->all();
    }

    /**
     * Options du composant Alpine (aucun texte en dur dans le JavaScript).
     *
     * @param  list<array{slug: string, url: string, file: string|null, title: string}>  $marked
     * @return array<string, mixed>
     */
    public static function options(array $marked = []): array
    {
        return [
            'key' => self::STORAGE_KEY,
            'event' => self::EVENT,
            'marked' => $marked,
            'texts' => [
                'saved' => (string) __('microcopy.offline.saved_offline'),
                'removed' => (string) __('microcopy.offline.removed_offline'),
                // Complément masqué du bouton « Retirer » de la liste ; le composant remplace :title côté client.
                'removeHint' => (string) __('pages/ressources.offline_remove_hint', ['title' => ':title']),
            ],
            // Formes brutes du pluriel : le composant remplace :count côté client.
            'countLabels' => [
                'zero' => trans_choice('pages/ressources.offline_count', 0),
                'one' => trans_choice('pages/ressources.offline_count', 1, ['count' => ':count']),
                'many' => trans_choice('pages/ressources.offline_count', 2, ['count' => ':count']),
            ],
        ];
    }
}

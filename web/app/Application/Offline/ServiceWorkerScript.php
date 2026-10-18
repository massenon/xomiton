<?php

declare(strict_types=1);

namespace App\Application\Offline;

use App\Application\Content\OfflineLibrary;
use App\Application\Shared\KnowledgeBaseAnswerer;
use App\Models\Resource;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Script du service worker servi à la racine (P5-10, ADR-26) : le fichier construit par Vite (vite-plugin-pwa,
 * `public/build/sw.js`, coquille précachée) précédé de `self.__XM_SW__` : version, pages gardées sans réseau, page de
 * repli, délai réseau, ressources marquées par l'équipe (`is_offline_available`, précachées pour tout le monde, avec leur
 * révision `rev` : seules les nouvelles ou modifiées sont rechargées à l'installation), textes et numéros d'urgence
 * (réglage `emergency_numbers`) de la page minimale hors connexion. Tout changement de ces données change les octets du
 * script : le navigateur installe la nouvelle version, qui recharge ses pages. La tâche admin « Mise à jour du cache hors
 * connexion » (P13-02) appelle {@see bump()} pour forcer ce rechargement sans nouveau build. Liste des ressources
 * marquées en cache applicatif `marked_cache_seconds` (clé liée à la révision : un bump la relit).
 */
final class ServiceWorkerScript
{
    /** Paramètre (table settings) incrémenté par P13-02. */
    public const REVISION_SETTING = 'offline.sw_revision';

    private const MARKED_CACHE_KEY = 'offline.sw.marked.';

    /** Script complet, ou null tant que le build n'a pas produit le service worker (développement, tests). */
    public function render(): ?string
    {
        // Relatif à public/ (build Vite) ; un chemin absolu est pris tel quel (tests, build déposé ailleurs).
        $configured = (string) config('xomiton.offline.service_worker.script', 'build/sw.js');
        $path = str_starts_with($configured, DIRECTORY_SEPARATOR) ? $configured : public_path($configured);

        if (! is_file($path)) {
            return null;
        }

        $script = (string) file_get_contents($path);
        $config = json_encode($this->config(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR);

        return 'self.__XM_SW__='.$config.";\n".$script;
    }

    /**
     * Données injectées dans le script (lues par resources/js/sw/sw.js).
     *
     * @return array{version: string, pages: list<string>, fallback: string, timeout: int, marked: list<array{url: string, file: string|null, rev: string}>, texts: array{title: string, text: string, numbers: string}, numbers: list<array{label: string, number: string}>}
     */
    public function config(): array
    {
        $pages = array_values(array_filter(
            array_map(static fn (mixed $page): string => is_string($page) ? $page : '', (array) config('xomiton.offline.service_worker.pages', [])),
            static fn (string $page): bool => str_starts_with($page, '/'),
        ));

        return [
            'version' => $this->version(),
            'pages' => $pages,
            'fallback' => (string) config('xomiton.offline.service_worker.fallback', '/urgences'),
            'timeout' => (int) config('xomiton.offline.service_worker.timeout_ms', 6000),
            'marked' => $this->marked(),
            'texts' => [
                'title' => (string) __('offline.sw.title'),
                'text' => (string) __('offline.sw.text'),
                'numbers' => (string) __('offline.sw.numbers'),
            ],
            'numbers' => $this->numbers(),
        ];
    }

    public function version(): string
    {
        return (string) config('xomiton.offline.service_worker.version', '1').'.'.$this->revision();
    }

    /** Nouvelle révision : chaque navigateur installe le service worker à sa prochaine vérification et recharge le cache. */
    public function bump(?int $updatedBy = null): int
    {
        $revision = $this->revision() + 1;
        Settings::set(self::REVISION_SETTING, $revision, $updatedBy);

        return $revision;
    }

    private function revision(): int
    {
        try {
            return Settings::int(self::REVISION_SETTING);
        } catch (Throwable) {
            // Base indisponible (maintenance, incident) : le script reste servi, sans révision.
            return 0;
        }
    }

    /**
     * Ressources marquées par l'équipe, en chemins (une adresse absolue d'APP_URL pourrait ne pas être l'origine servie),
     * avec leur révision (date de mise à jour) : le service worker ne recharge que les nouvelles ou les modifiées.
     *
     * @return list<array{url: string, file: string|null, rev: string}>
     */
    private function marked(): array
    {
        if (! OfflineLibrary::enabled()) {
            return [];
        }

        try {
            /** @var list<array{url: string, file: string|null, rev: string}> */
            return Cache::remember(
                self::MARKED_CACHE_KEY.$this->revision(),
                max(1, (int) config('xomiton.offline.service_worker.marked_cache_seconds', 300)),
                fn (): array => $this->loadMarked(),
            );
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{url: string, file: string|null, rev: string}>
     */
    private function loadMarked(): array
    {
        $marked = OfflineLibrary::marked();
        $revisions = Resource::query()->whereIn('slug', array_column($marked, 'slug'))->pluck('updated_at', 'slug');

        return array_map(static fn (array $entry): array => [
            'url' => self::path($entry['url']) ?? '',
            'file' => $entry['file'] === null ? null : self::path($entry['file']),
            'rev' => (string) ($revisions->get($entry['slug'])?->getTimestamp() ?? ''),
        ], $marked);
    }

    /**
     * Numéros d'urgence de la page minimale (même source que l'assistant et la page /urgences hors annuaire).
     *
     * @return list<array{label: string, number: string}>
     */
    private function numbers(): array
    {
        try {
            return KnowledgeBaseAnswerer::emergencyNumbers();
        } catch (Throwable) {
            return [];
        }
    }

    private static function path(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : null;
    }
}

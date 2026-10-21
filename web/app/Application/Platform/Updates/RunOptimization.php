<?php

declare(strict_types=1);

namespace App\Application\Platform\Updates;

use App\Application\Offline\ServiceWorkerScript;
use App\Application\Platform\Backup\BackupService;
use App\Application\Retention\PurgeAuditLogs;
use App\Application\Retention\PurgeMailLogs;
use App\Application\Seo\SitemapBuilder;
use App\Application\Shared\Referentials;
use App\Domain\Platform\Exceptions\BackupException;
use App\Domain\Platform\Exceptions\VersionRefused;
use App\Enums\BackupKind;
use App\Models\Setting;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;

/**
 * Tâches d'optimisation de l'écran Mises à jour (P13-02, prototype `mises-a-jour.php`), permission `updates.manage` :
 * `cache` (caches vidés puis référentiels et plan du site recalculés), `logs` (journal d'audit et journaux d'envoi au-delà de
 * leur rétention), `backup` (sauvegarde manuelle, comme l'écran Sauvegardes), `sitemap` (plan du site) et `sw` (« Mise à jour
 * du cache hors connexion » : incrémente la révision du service worker, ServiceWorkerScript::bump(), les navigateurs
 * installent la nouvelle version et rechargent leurs pages gardées). La compression des images se fait à l'envoi
 * (MediaUploader) : aucune tâche de recompression en masse. Date, résultat et auteur·rice de la dernière exécution de
 * chaque tâche sont gardés dans le paramètre `optim_state`.
 */
final class RunOptimization
{
    public const TASKS = ['cache', 'sw', 'sitemap', 'logs', 'backup'];

    public function __construct(
        private readonly ServiceWorkerScript $worker,
        private readonly BackupService $backups,
        private readonly Referentials $referentials,
        private readonly SitemapBuilder $sitemap,
        private readonly PurgeAuditLogs $auditLogs,
        private readonly PurgeMailLogs $mailLogs,
    ) {}

    /**
     * @return string résultat en une phrase (affiché et conservé)
     *
     * @throws AuthorizationException
     * @throws VersionRefused tâche inconnue
     * @throws BackupException tâche `backup`
     */
    public function __invoke(User $by, string $task): string
    {
        if (! $by->can('updates.manage')) {
            throw new AuthorizationException;
        }

        if (! in_array($task, self::TASKS, true)) {
            throw VersionRefused::unknownTask();
        }

        $result = match ($task) {
            'cache' => $this->cache(),
            'sw' => $this->serviceWorker($by),
            'sitemap' => $this->rebuildSitemap(),
            'logs' => $this->logs(),
            'backup' => $this->backup($by),
        };

        $state = Settings::array('optim_state');
        $state[$task] = ['at' => now()->toIso8601String(), 'result' => $result, 'by' => (string) $by->pseudo];
        Settings::set('optim_state', $state, (int) $by->getKey());
        Audit::log('optimization.'.$task, null, $result, $by);

        return $result;
    }

    /**
     * @return array<string, array{at: string, result: string, by: string}>
     */
    public static function state(): array
    {
        $state = Settings::array('optim_state');

        return array_filter($state, static fn (mixed $entry, mixed $key): bool => is_string($key) && is_array($entry) && isset($entry['at'], $entry['result']), ARRAY_FILTER_USE_BOTH);
    }

    private function cache(): string
    {
        Artisan::call('optimize:clear');
        Setting::flushCache();
        $this->referentials->invalidate();
        $this->referentials->zones();
        $this->sitemap->xml();

        return (string) __('admin_system.updates.optimization.results.cache');
    }

    private function serviceWorker(User $by): string
    {
        $revision = $this->worker->bump((int) $by->getKey());

        return (string) __('admin_system.updates.optimization.results.sw', ['version' => $this->worker->version(), 'revision' => $revision]);
    }

    private function rebuildSitemap(): string
    {
        SitemapBuilder::flush();

        return (string) __('admin_system.updates.optimization.results.sitemap', ['count' => count($this->sitemap->urls())]);
    }

    private function logs(): string
    {
        $audit = $this->auditLogs->run();
        $mail = $this->mailLogs->run();

        return (string) __('admin_system.updates.optimization.results.logs', ['audit' => $audit, 'mail' => $mail]);
    }

    private function backup(User $by): string
    {
        $backup = $this->backups->create(BackupKind::Manual, $by);

        return (string) __('admin_system.updates.optimization.results.backup', ['name' => $backup->name, 'size' => $backup->humanSize()]);
    }
}

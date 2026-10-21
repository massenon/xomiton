<?php

declare(strict_types=1);

namespace App\Application\Platform\Updates;

use App\Application\Platform\Settings\UpdateSettings;
use App\Domain\Platform\Exceptions\VersionRefused;
use App\Models\AppVersion;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Versions de la plateforme (P13-02, `app_versions`) : publier maintenant (la version doit dépasser la version actuelle),
 * planifier (date du jour ou postérieure, fuseau d'affichage), déployer une version planifiée, annuler une planification
 * (la ligne reste dans l'historique), version minimale de l'application mobile (jamais au-dessus de la version actuelle).
 * Permission `updates.manage` (superadmin). La version actuelle reflète le paramètre `platform_version` (+ `last_update`)
 * lu par le tableau de bord et `GET /api/v1/app/version`. Journal : `update.published`, `update.planned`,
 * `update.deployed`, `update.cancelled`, `update.minimum_set`.
 */
final class ManageVersions
{
    public function __construct(private readonly UpdateSettings $settings) {}

    public static function currentVersion(): string
    {
        $deployed = AppVersion::current();

        return $deployed?->version ?? Settings::string('platform_version', (string) config('xomiton.version', '1.0.0'));
    }

    /**
     * @throws AuthorizationException
     * @throws VersionRefused
     */
    public function publish(User $by, string $version, string $kind, string $notes): AppVersion
    {
        $this->authorize($by);
        $this->assertFree($version);
        $this->assertNewer($version);

        return DB::transaction(function () use ($by, $version, $kind, $notes): AppVersion {
            $row = AppVersion::query()->create([
                'version' => $version,
                'kind' => $kind,
                'notes' => trim($notes),
                'status' => AppVersion::STATUS_DEPLOYED,
                'deployed_at' => now(),
                'created_by' => $by->getKey(),
            ]);
            $this->reflect($row, $by);
            Audit::log('update.published', $row, 'version='.$version.' ; type='.$kind, $by);

            return $row;
        });
    }

    /**
     * @param  Carbon  $scheduledFor  début de la journée choisie, fuseau d'affichage
     *
     * @throws AuthorizationException
     * @throws VersionRefused
     */
    public function plan(User $by, string $version, string $kind, string $notes, Carbon $scheduledFor): AppVersion
    {
        $this->authorize($by);
        $this->assertFree($version);

        $timezone = (string) config('xomiton.display_timezone', 'UTC');

        if ($scheduledFor->copy()->setTimezone($timezone)->startOfDay()->lt(now($timezone)->startOfDay())) {
            throw VersionRefused::pastDate();
        }

        $row = AppVersion::query()->create([
            'version' => $version,
            'kind' => $kind,
            'notes' => trim($notes),
            'status' => AppVersion::STATUS_PLANNED,
            'scheduled_for' => $scheduledFor->copy()->utc(),
            'created_by' => $by->getKey(),
        ]);
        Audit::log('update.planned', $row, 'version='.$version.' ; type='.$kind.' ; pour='.$scheduledFor->toDateString(), $by);

        return $row;
    }

    /**
     * @throws AuthorizationException
     * @throws VersionRefused
     */
    public function deploy(User $by, AppVersion $version): AppVersion
    {
        $this->authorize($by);

        return DB::transaction(function () use ($by, $version): AppVersion {
            /** @var AppVersion $row */
            $row = AppVersion::query()->whereKey($version->getKey())->lockForUpdate()->firstOrFail();

            if (! $row->isPlanned()) {
                throw VersionRefused::notPlanned();
            }

            $this->assertNewer($row->version);
            $row->markDeployed();
            $this->reflect($row, $by);
            Audit::log('update.deployed', $row, 'version='.$row->version, $by);

            return $row;
        });
    }

    /**
     * @throws AuthorizationException
     * @throws VersionRefused
     */
    public function cancel(User $by, AppVersion $version): AppVersion
    {
        $this->authorize($by);

        if (! $version->isPlanned()) {
            throw VersionRefused::notPlanned();
        }

        $version->forceFill(['status' => AppVersion::STATUS_CANCELLED])->save();
        Audit::log('update.cancelled', $version, 'version='.$version->version, $by);

        return $version;
    }

    /**
     * Version minimale de l'application mobile (`app_min_version`, servie par GET app/version).
     *
     * @throws AuthorizationException
     * @throws VersionRefused
     * @throws \Illuminate\Validation\ValidationException
     */
    public function setMinimum(User $by, string $minimum): void
    {
        $this->authorize($by);

        if (version_compare($minimum, self::currentVersion(), '>')) {
            throw VersionRefused::minimumAboveCurrent(self::currentVersion());
        }

        $changed = ($this->settings)($by, ['app_min_version' => $minimum], 'updates.manage');

        if ($changed !== []) {
            Audit::log('update.minimum_set', null, 'minimum='.$minimum, $by);
        }
    }

    private function authorize(User $by): void
    {
        if (! $by->can('updates.manage')) {
            throw new AuthorizationException;
        }
    }

    private function assertFree(string $version): void
    {
        if (AppVersion::query()->where('version', $version)->whereNot('status', AppVersion::STATUS_CANCELLED)->exists()) {
            throw VersionRefused::exists($version);
        }

        // Une planification annulée libère son numéro : l'ancienne ligne est supprimée pour garder l'unicité de la colonne.
        AppVersion::query()->where('version', $version)->where('status', AppVersion::STATUS_CANCELLED)->delete();
    }

    private function assertNewer(string $version): void
    {
        $current = self::currentVersion();

        if (version_compare($version, $current, '<=')) {
            throw VersionRefused::notNewer($current);
        }
    }

    private function reflect(AppVersion $row, User $by): void
    {
        Settings::set('platform_version', $row->version, (int) $by->getKey());
        Settings::set('last_update', ($row->deployed_at ?? now())->toDateString(), (int) $by->getKey());
    }
}

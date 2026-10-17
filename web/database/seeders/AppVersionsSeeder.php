<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AppVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Versions de la plateforme du lot 11, reprises de prototype_social/data/updates.json (3 versions : deux déployées,
 * une planifiée). Idempotent : réalignement sur le numéro de version, statut et dates conservés une fois déployée.
 */
class AppVersionsSeeder extends Seeder
{
    /** @var list<array{version: string, date: string, type: string, notes: string, status: string}> data/updates.json */
    private const VERSIONS = [
        [
            'version' => '1.4.2',
            'date' => '2026-09-28',
            'type' => 'Correctif',
            'notes' => 'Optimisation de la synchronisation hors ligne ; correctif géolocalisation SOS.',
            'status' => 'déployée',
        ],
        [
            'version' => '1.4.0',
            'date' => '2026-08-30',
            'type' => 'Fonctionnalité',
            'notes' => 'Nouveau moteur de rapports (Excel, PDF) avec désagrégation.',
            'status' => 'déployée',
        ],
        [
            'version' => '1.5.0',
            'date' => '2026-11-15',
            'type' => 'Fonctionnalité',
            'notes' => 'Passerelles Telegram et Tinder ; mode sombre amélioré.',
            'status' => 'planifiée',
        ],
    ];

    public function run(): void
    {
        foreach (self::VERSIONS as $row) {
            $version = AppVersion::query()->firstOrNew(['version' => $row['version']]);
            $status = AppVersion::statusFromPrototype($row['status']);
            $date = Carbon::parse($row['date'], 'Africa/Porto-Novo')->setTime(8, 0);

            $version->fill([
                'kind' => AppVersion::kindFromPrototype($row['type']),
                'notes' => $row['notes'],
            ]);

            // Une version déjà déployée par l'administration n'est jamais replanifiée par le seed.
            if (! $version->exists || ! $version->isDeployed()) {
                $version->fill([
                    'status' => $status,
                    'scheduled_for' => $status === AppVersion::STATUS_PLANNED ? $date : null,
                    'deployed_at' => $status === AppVersion::STATUS_DEPLOYED ? $date : null,
                ]);
            }

            $version->save();
        }
    }
}

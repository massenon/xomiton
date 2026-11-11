<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Application\Platform\DisableMaintenance;
use App\Application\Platform\EnableMaintenance;
use App\Application\Platform\MaintenancePage;
use App\Application\Platform\MaintenanceStatus;
use App\Domain\Platform\Exceptions\MaintenanceAlreadyActive;
use App\Http\Middleware\EnsureNotInMaintenance;
use App\Models\MaintenanceWindow;
use Illuminate\Console\Command;

/**
 * Commande de secours du mode maintenance (P13-06, garde-fou anti-verrouillage) : depuis le terminal cPanel, SSH ou
 * une tâche cron, sans passer par l'écran d'administration. Mêmes cas d'usage que l'écran (journal d'audit, acteur
 * « système »), jamais `artisan down` (la liste blanche RG-01 doit rester servie). `on` sur une maintenance déjà active
 * ne change rien (échec explicite, comme l'écran). Heures dans le fuseau d'affichage (xomiton.display_timezone).
 *   php artisan xomiton:maintenance on --minutes=60 --message="…" --reason="…"
 *   php artisan xomiton:maintenance off --reason="…"
 *   php artisan xomiton:maintenance status
 */
class MaintenanceCommand extends Command
{
    protected $signature = 'xomiton:maintenance
        {action : on, off ou status}
        {--message= : Message public affiché sur la page de maintenance (280 caractères au plus)}
        {--minutes= : Durée estimée, affichée au public (la plateforme ne rouvre qu’avec « off »)}
        {--reason= : Motif interne, écrit dans le journal d’audit}';

    protected $description = 'Active, lève ou décrit le mode maintenance applicatif (RG-14, liste blanche RG-01 toujours servie)';

    public function handle(EnableMaintenance $enable, DisableMaintenance $disable): int
    {
        $action = strtolower(trim((string) $this->argument('action')));

        return match ($action) {
            'on' => $this->enable($enable),
            'off' => $this->disable($disable),
            'status' => $this->status(),
            default => $this->unknown($action),
        };
    }

    private function enable(EnableMaintenance $enable): int
    {
        $minutes = $this->option('minutes');

        if ($minutes !== null && (! ctype_digit((string) $minutes) || (int) $minutes < 1)) {
            $this->error((string) __('maintenance.command.invalid_minutes'));

            return self::INVALID;
        }

        try {
            $enable(null, $this->stringOption('message'), $minutes === null ? null : now()->addMinutes((int) $minutes), $this->stringOption('reason'));
        } catch (MaintenanceAlreadyActive) {
            $this->warn((string) __('maintenance.command.already_active'));
            $this->status();

            return self::FAILURE;
        }

        $this->info((string) __('maintenance.command.enabled'));

        return $this->status();
    }

    private function disable(DisableMaintenance $disable): int
    {
        $closed = $disable(null, $this->stringOption('reason'));
        $this->info((string) __('maintenance.command.disabled', ['count' => $closed]));

        return self::SUCCESS;
    }

    private function status(): int
    {
        $snapshot = MaintenanceStatus::snapshot();
        $format = MaintenancePage::formatter();

        $this->line((string) __($snapshot->active ? 'maintenance.command.active' : 'maintenance.command.inactive'));

        if ($snapshot->active) {
            $this->line((string) __('maintenance.command.message', ['message' => $snapshot->message]));

            if ($snapshot->startsAt !== null) {
                $this->line((string) __('maintenance.command.starts', ['date' => $format->tableDateTime($snapshot->startsAt)]));
            }

            $this->line($snapshot->endsAt === null
                ? (string) __('maintenance.command.ends_unknown')
                : (string) __('maintenance.command.ends', ['date' => $format->tableDateTime($snapshot->endsAt)]));
        }

        $upcoming = MaintenanceWindow::query()->upcoming()->limit(10)->get();
        $this->line((string) __('maintenance.command.upcoming', ['count' => $upcoming->count()]));

        foreach ($upcoming as $window) {
            $this->line((string) __('maintenance.command.upcoming_line', [
                'from' => $format->tableDateTime($window->starts_at),
                'to' => $format->tableDateTime($window->ends_at),
                'hours' => (int) $window->announce_hours,
            ]));
        }

        $this->line((string) __('maintenance.command.whitelist', ['paths' => implode(', ', EnsureNotInMaintenance::ALLOWED_PATHS)]));

        return self::SUCCESS;
    }

    private function unknown(string $action): int
    {
        $this->error((string) __('maintenance.command.unknown_action', ['action' => $action]));

        return self::INVALID;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}

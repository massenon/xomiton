<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Maintenance;

use App\Application\Platform\CancelMaintenanceWindow;
use App\Application\Platform\DisableMaintenance;
use App\Application\Platform\EnableMaintenance;
use App\Application\Platform\MaintenanceOverview;
use App\Application\Platform\MaintenancePage;
use App\Application\Platform\MaintenanceSnapshot;
use App\Application\Platform\MaintenanceStatus;
use App\Application\Platform\ScheduleMaintenance;
use App\Application\Platform\UpdateMaintenanceMessage;
use App\Domain\Platform\Exceptions\MaintenanceException;
use App\Http\Middleware\EnsureNotInMaintenance;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\MaintenanceWindow;
use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Écran « Mode maintenance » `/admin/maintenance` (P13-06, RG-14, Q28) : état en tête (inactif · annoncé · en cours ·
 * terminé, fin prévue, compte à rebours), activation immédiate dans une modale rouge (motif interne obligatoire, durée
 * estimée, message public, case de confirmation), désactivation en un clic, planification de fenêtres (chevauchement
 * refusé, annonce préalable), message public avec aperçu en direct de la page publique (même contenu que P13-07),
 * liste blanche RG-01 en lecture seule (constantes du middleware, jamais une propriété modifiable), garde-fous
 * anti-verrouillage et historique depuis audit_logs. Permission `platform.maintenance` (admin, superadmin).
 * Dates saisies et affichées dans le fuseau d'affichage (MaintenancePage::timezone, Bénin), stockées en UTC.
 * Activer ou désactiver recharge l'écran : le bandeau du gabarit et la pastille du menu suivent l'état.
 */
final class Index extends Component
{
    use InteractsWithUi;

    /** Durées estimées proposées (minutes) ; `unknown` : aucune heure affichée au public. */
    public const DURATIONS = ['15', '30', '60', '120', '240', 'unknown'];

    /** Délais d'annonce préalable proposés (heures). */
    public const ANNOUNCE_HOURS = ['0', '1', '6', '24', '48', '72', '168'];

    private const DATE_FORMAT = 'Y-m-d\TH:i';

    // --- Activation immédiate (modale) et message public ---
    public string $reason = '';

    public string $duration = '60';

    public string $message = '';

    public bool $acknowledge = false;

    // --- Planification ---
    public string $startsAt = '';

    public string $endsAt = '';

    public string $announceHours = '24';

    public string $windowMessage = '';

    public string $windowReason = '';

    // --- Aperçu en direct ---
    #[Locked]
    public string $previewTheme = 'light';

    public function mount(): void
    {
        $this->authorize('toggleMaintenance', Setting::class);

        $this->message = MaintenanceStatus::snapshot()->message;
        $tomorrow = now(MaintenancePage::timezone())->addDay();
        $this->startsAt = $tomorrow->copy()->setTime(22, 0)->format(self::DATE_FORMAT);
        $this->endsAt = $tomorrow->copy()->setTime(23, 59)->format(self::DATE_FORMAT);
    }

    public function enable(EnableMaintenance $enable): void
    {
        $this->authorize('toggleMaintenance', Setting::class);

        try {
            $this->validate([
                'reason' => ['required', 'string', 'max:'.EnableMaintenance::REASON_MAX_LENGTH],
                'duration' => ['required', Rule::in(self::DURATIONS)],
                'message' => ['nullable', 'string', 'max:'.MaintenanceStatus::MESSAGE_MAX_LENGTH],
                'acknowledge' => ['accepted'],
            ]);
        } catch (ValidationException $exception) {
            // La modale place le focus sur le premier champ invalide (le clavier ne retombe pas sur <body>).
            $this->dispatch('xm-maint-invalid');

            throw $exception;
        }

        $endsAt = $this->duration === 'unknown' ? null : now()->addMinutes((int) $this->duration);

        // Refus (déjà active, compte qui perdrait l'accès) : erreur `enable`, affichée dans la modale seulement.
        if (! $this->attempt(fn () => $enable($this->user(), $this->message, $endsAt, $this->reason), 'enable')) {
            $this->dispatch('xm-maint-invalid');

            return;
        }

        $this->flashAndReload('maintenance.admin.toasts.enabled', 'warning');
    }

    public function disable(DisableMaintenance $disable): void
    {
        $this->authorize('toggleMaintenance', Setting::class);
        $disable($this->user());

        $this->flashAndReload('maintenance.admin.toasts.disabled', 'success');
    }

    public function saveMessage(UpdateMaintenanceMessage $update): void
    {
        $this->authorize('toggleMaintenance', Setting::class);
        $this->validate(['message' => ['nullable', 'string', 'max:'.MaintenanceStatus::MESSAGE_MAX_LENGTH]]);

        $update($this->user(), $this->message);
        $this->message = MaintenanceStatus::snapshot()->message;
        $this->toast((string) __('maintenance.admin.toasts.message_saved'));
    }

    public function schedule(ScheduleMaintenance $schedule): void
    {
        $this->authorize('toggleMaintenance', Setting::class);
        $this->validate([
            'startsAt' => ['required', 'date_format:'.self::DATE_FORMAT],
            'endsAt' => ['required', 'date_format:'.self::DATE_FORMAT],
            'announceHours' => ['required', Rule::in(self::ANNOUNCE_HOURS)],
            'windowMessage' => ['nullable', 'string', 'max:'.MaintenanceStatus::MESSAGE_MAX_LENGTH],
            'windowReason' => ['required', 'string', 'max:'.EnableMaintenance::REASON_MAX_LENGTH],
        ]);

        $startsAt = $this->parseDate($this->startsAt, 'startsAt');
        $endsAt = $this->parseDate($this->endsAt, 'endsAt');

        if ($startsAt === null || $endsAt === null) {
            return;
        }

        $window = null;
        $done = $this->attempt(function () use ($schedule, $startsAt, $endsAt, &$window): void {
            $window = $schedule($this->user(), $startsAt, $endsAt, $this->windowMessage, (int) $this->announceHours, $this->windowReason);
        });

        if (! $done || ! $window instanceof MaintenanceWindow) {
            return;
        }

        $this->reset('windowMessage', 'windowReason');
        unset($this->overview);
        $this->toast((string) __('maintenance.admin.toasts.scheduled', [
            'from' => MaintenancePage::when($window->starts_at),
            'to' => MaintenancePage::when($window->ends_at),
        ]));
    }

    public function cancelWindow(int $windowId, CancelMaintenanceWindow $cancel): void
    {
        $this->authorize('toggleMaintenance', Setting::class);
        $window = MaintenanceWindow::query()->findOrFail($windowId);

        if (! $this->attempt(fn () => $cancel($this->user(), $window))) {
            return;
        }

        unset($this->overview);
        $this->toast((string) __('maintenance.admin.toasts.cancelled', ['date' => MaintenancePage::when($window->starts_at)]), 'warning');
    }

    public function setPreviewTheme(string $theme): void
    {
        $this->previewTheme = $theme === 'dark' ? 'dark' : 'light';
    }

    #[Computed]
    public function overview(): MaintenanceOverview
    {
        return MaintenanceOverview::make();
    }

    /**
     * Liste blanche RG-01 affichée en lecture seule : lue dans les constantes du middleware, jamais une propriété
     * publique (aucune requête Livewire ne peut la modifier).
     *
     * @return array<string, list<array{label: string, paths: list<string>}>>
     */
    #[Computed]
    public function whitelist(): array
    {
        $paths = EnsureNotInMaintenance::ALLOWED_PATHS;
        $pick = static fn (string ...$prefixes): array => array_values(array_filter($paths, static function (string $path) use ($prefixes): bool {
            foreach ($prefixes as $prefix) {
                if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                    return true;
                }
            }

            return false;
        }));
        $item = static fn (string $key, array $found): array => ['label' => (string) __('maintenance.admin.whitelist.items.'.$key), 'paths' => $found];

        return [
            'crisis' => [
                $item('sos', $pick('sos', 'api/v1/sos')),
                $item('report', $pick('signaler', 'api/v1/signalements')),
                $item('urgences', $pick('urgences', 'api/v1/urgences')),
                $item('referentials', $pick('api/v1/zones', 'api/v1/violence-types', 'api/v1/needs')),
                $item('acknowledge', $pick('alertes')),
                $item('newsletter', $pick('newsletter', 'api/v1/newsletter')),
            ],
            'access' => [
                $item('login', $pick('connexion', 'code-de-verification', 'deconnexion', 'api/v1/auth/login', 'api/v1/auth/otp', 'api/v1/auth/logout')),
                $item('password', $pick('mot-de-passe-oublie', 'nouveau-mot-de-passe', 'api/v1/auth/password')),
            ],
            'system' => [
                $item('status', $pick('up', 'api/v1/health', 'api/v1/status')),
                $item('sync', $pick('api/v1/sync', 'api/v1/heartbeat')),
                $item('cron', []),
            ],
        ];
    }

    public function render(): View
    {
        $overview = $this->overview;
        $snapshot = $overview->snapshot;

        // Aperçu en direct : page publique avec le message en cours de saisie et, hors maintenance, la durée estimée.
        $previewEnds = $snapshot->active ? $snapshot->endsAt : ($this->duration === 'unknown' ? null : now()->addMinutes((int) $this->duration));
        $previewSnapshot = new MaintenanceSnapshot(
            true,
            $snapshot->window,
            EnableMaintenance::clean($this->message, MaintenanceStatus::MESSAGE_MAX_LENGTH) ?? (string) __('maintenance.default_message'),
            $snapshot->active ? $snapshot->startsAt : now(),
            $previewEnds,
        );

        return view('livewire.admin.maintenance.index', [
            'overview' => $overview,
            'snapshot' => $snapshot,
            'preview' => MaintenancePage::data($previewSnapshot, preview: true) + ['theme' => $this->previewTheme],
            'previewCss' => MaintenancePage::css('content'),
            'whitelist' => $this->whitelist,
            'durations' => array_combine(self::DURATIONS, array_map(static fn (string $d): string => (string) __('maintenance.admin.durations.'.$d), self::DURATIONS)),
            'announceOptions' => array_combine(self::ANNOUNCE_HOURS, array_map(static fn (string $h): string => (string) __('maintenance.admin.announce.'.$h), self::ANNOUNCE_HOURS)),
            'savedMessage' => trim(Settings::string(MaintenanceStatus::SETTING_MESSAGE, '')),
            'timezoneHint' => self::timezoneHint(),
        ])->layout('components.layouts.admin', [
            'title' => __('maintenance.admin.title'),
            'adminPage' => 'maintenance',
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'reason.required' => (string) __('maintenance.validation.reason_required'),
            'windowReason.required' => (string) __('maintenance.validation.reason_required'),
            'acknowledge.accepted' => (string) __('maintenance.validation.acknowledge_required'),
            'message.max' => (string) __('maintenance.validation.message_max', ['max' => MaintenanceStatus::MESSAGE_MAX_LENGTH]),
            'windowMessage.max' => (string) __('maintenance.validation.message_max', ['max' => MaintenanceStatus::MESSAGE_MAX_LENGTH]),
            'startsAt.required' => (string) __('maintenance.validation.date_required'),
            'endsAt.required' => (string) __('maintenance.validation.date_required'),
            'startsAt.date_format' => (string) __('maintenance.validation.date_invalid'),
            'endsAt.date_format' => (string) __('maintenance.validation.date_invalid'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'reason' => (string) __('maintenance.admin.fields.reason'),
            'duration' => (string) __('maintenance.admin.fields.duration'),
            'message' => (string) __('maintenance.admin.fields.message'),
            'startsAt' => (string) __('maintenance.admin.fields.starts_at'),
            'endsAt' => (string) __('maintenance.admin.fields.ends_at'),
            'announceHours' => (string) __('maintenance.admin.fields.announce_hours'),
            'windowMessage' => (string) __('maintenance.admin.fields.window_message'),
            'windowReason' => (string) __('maintenance.admin.fields.window_reason'),
        ];
    }

    /** Exécute un cas d'usage ; un refus métier devient l'erreur du champ concerné (sinon `$fallback`). */
    private function attempt(callable $action, string $fallback = 'maintenance'): bool
    {
        try {
            $action();

            return true;
        } catch (MaintenanceException $exception) {
            $field = match ($exception->field()) {
                'starts_at' => 'startsAt',
                'ends_at' => 'endsAt',
                default => $fallback,
            };
            $this->addError($field, $exception->userMessage());

            return false;
        }
    }

    private function parseDate(string $value, string $field): ?Carbon
    {
        try {
            $date = Carbon::createFromFormat(self::DATE_FORMAT, $value, MaintenancePage::timezone());
        } catch (InvalidFormatException) {
            $date = null;
        }

        if (! $date instanceof Carbon) {
            $this->addError($field, (string) __('maintenance.validation.date_invalid'));

            return null;
        }

        return $date;
    }

    /** « Heure de Porto-Novo (UTC+01:00), comme sur la page publique. » */
    private static function timezoneHint(): string
    {
        $timezone = MaintenancePage::timezone();
        $zone = str_replace('_', ' ', str_contains($timezone, '/') ? substr($timezone, (int) strrpos($timezone, '/') + 1) : $timezone);

        return (string) __('maintenance.admin.fields.timezone_hint', ['zone' => $zone, 'offset' => now($timezone)->format('P')]);
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * Activation et désactivation : rechargement complet (bandeau du gabarit, pastille du menu, sessions) ; le message
     * est rejoué en toast SweetAlert2 par le gabarit (InteractsWithUi::flashToast, P10-03).
     */
    private function flashAndReload(string $key, string $type): void
    {
        $this->flashToast((string) __($key), $type);
        $this->redirectRoute('admin.maintenance');
    }
}

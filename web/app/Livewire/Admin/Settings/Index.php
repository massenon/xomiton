<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Settings;

use App\Application\Alerts\OnCallRoster;
use App\Application\Platform\Backup\BackupService;
use App\Application\Platform\Settings\PasswordPolicy;
use App\Application\Platform\Settings\SettingsCatalog;
use App\Application\Platform\Settings\UpdateSettings;
use App\Application\Platform\Updates\ManageVersions;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Backup;
use App\Models\Setting;
use App\Models\User;
use App\Support\LocaleFormatter;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Paramètres `/admin/parametres` (P13-04, prototype admin/parametres.php) : clés **typées** du catalogue
 * (config/xomiton.php → platform_settings), une validation par clé (UpdateSettings), valeur par défaut tant que la base ne
 * porte pas la clé, secrets chiffrés (mot de passe SMTP : jamais renvoyé à l'écran, laissé vide = inchangé). Groupes :
 * général (nom, e-mail de contact, fuseau d'affichage, langues, drapeaux), sécurité (politique de mot de passe qui ne peut
 * que **durcir** le plancher de Q30, durée d'inactivité), conservation (journal d'audit), messagerie SMTP (superadmin).
 * Lecture `settings.view` (admin, superadmin) ; écriture `settings.manage` (superadmin). Lecture seule : inscriptions
 * (écran dédié), garde, identité visuelle (jetons), informations système.
 */
final class Index extends Component
{
    use InteractsWithUi;

    public const GROUPS = [
        'general' => ['site_name', 'contact_email', 'timezone', 'languages', 'anonymous_reports', 'gps_sos', 'offline_sync'],
        'security' => ['password_min_length', 'password_require_symbol', 'password_expiry_days', 'session_minutes'],
        'retention' => ['audit_log_days'],
        'smtp' => ['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_from_email', 'smtp_from_name', 'smtp_reply_to'],
    ];

    /** Fuseaux proposés (prototype) et langues annoncées. */
    public const TIMEZONES = ['Africa/Porto-Novo', 'Africa/Lagos', 'Africa/Abidjan', 'UTC'];

    public const LANGUAGES = ['fr', 'en', 'fon', 'yo'];

    #[SafeUrl(as: 'onglet', except: 'general')]
    public string $tab = 'general';

    /**
     * Valeurs des champs, par clé de paramètre. Sans type natif par champ : un champ numérique vidé arrive en `null`.
     *
     * @var array<string, mixed>
     */
    public array $form = [];

    /** Mot de passe SMTP saisi : jamais pré-rempli, jamais renvoyé. */
    public string $smtpPassword = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Setting::class);
        $this->load();
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, $this->tabs()) ? $tab : 'general';
    }

    public function save(string $group, UpdateSettings $update): void
    {
        $this->authorize('manage', Setting::class);

        if (! array_key_exists($group, self::GROUPS) || ($group === 'smtp' && ! auth()->user()?->can('updateSmtp', Setting::class))) {
            abort(403);
        }

        $this->resetErrorBag();
        $input = [];

        foreach (self::GROUPS[$group] as $key) {
            $input[$key] = $this->form[$key] ?? null;
        }

        if ($group === 'smtp' && $this->smtpPassword !== '') {
            $input['smtp_password'] = $this->smtpPassword;
        }

        try {
            $changed = $update(auth()->user(), $input);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $this->addError($key === 'smtp_password' ? 'smtpPassword' : 'form.'.str_replace('.*', '', $key), (string) $messages[0]);
            }

            $this->dispatch('xm-focus-first-error');

            return;
        }

        $this->smtpPassword = '';
        $this->load();
        unset($this->system);
        $this->toast((string) __($changed === [] ? 'admin_system.settings.toasts.unchanged' : 'admin_system.settings.toasts.saved_'.$group), $changed === [] ? 'info' : 'success');
    }

    /**
     * @return array<string, array{label: string, icon: string}>
     */
    public function tabs(): array
    {
        $tabs = [
            'general' => ['label' => (string) __('admin_system.settings.tabs.general'), 'icon' => 'sliders'],
            'security' => ['label' => (string) __('admin_system.settings.tabs.security'), 'icon' => 'shield-lock'],
            'retention' => ['label' => (string) __('admin_system.settings.tabs.retention'), 'icon' => 'hourglass-split'],
        ];

        if (auth()->user()?->can('updateSmtp', Setting::class)) {
            $tabs['smtp'] = ['label' => (string) __('admin_system.settings.tabs.smtp'), 'icon' => 'envelope-at'];
        }

        $tabs['system'] = ['label' => (string) __('admin_system.settings.tabs.system'), 'icon' => 'info-circle'];

        return $tabs;
    }

    /**
     * Informations système (lecture seule).
     *
     * @return array<string, string>
     */
    #[Computed]
    public function system(): array
    {
        $format = LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC'));
        $last = Backup::query()->completed()->latestFirst()->first();
        $updated = Settings::string('last_update');

        return [
            'version' => 'v'.ManageVersions::currentVersion(),
            'updated' => $updated !== '' ? $format->longDate($updated) : '—',
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'database' => (string) DB::connection()->getDriverName(),
            'cache' => (string) config('cache.default'),
            'queue' => (string) config('queue.default'),
            'session' => (string) config('session.driver').' · '.(int) config('session.lifetime').' min',
            'timezone' => (string) config('xomiton.display_timezone', 'UTC'),
            'zip' => BackupService::zipAvailable() ? (string) __('admin_system.settings.system.yes') : (string) __('admin_system.settings.system.no'),
            'backup' => $last ? $format->tableDateTime($last->created_at).' ('.$last->name.')' : (string) __('admin_system.settings.system.none'),
        ];
    }

    /**
     * Administrateur·rices de garde en ce moment (lecture seule, P1-17).
     *
     * @return list<string>
     */
    #[Computed]
    public function onCall(): array
    {
        return app(OnCallRoster::class)->adminsOnCall()->map(fn (User $user): string => (string) $user->pseudo)->values()->all();
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.admin.settings.index', [
            'canManage' => (bool) $user?->can('manage', Setting::class),
            'tabList' => $this->tabs(),
            'timezones' => self::TIMEZONES,
            'languages' => self::LANGUAGES,
            'smtpSet' => SettingsCatalog::secretIsSet('smtp_password'),
            'registrationsOpen' => Settings::bool('registrations_open', true),
            'maintenance' => Settings::bool('maintenance', false),
            'retention' => (array) config('xomiton.retention', []),
            'floor' => PasswordPolicy::floor(),
            'tokens' => ['--xm-blue-500' => 'blue', '--xm-red-500' => 'red', '--xm-violet-700' => 'violet', '--xm-amber-500' => 'amber', '--xm-green-700' => 'green'],
        ])->layout('components.layouts.admin', [
            'title' => __('admin_system.settings.title'),
            'adminPage' => 'parametres',
        ]);
    }

    private function load(): void
    {
        $form = [];

        foreach (self::GROUPS as $keys) {
            foreach ($keys as $key) {
                $form[$key] = SettingsCatalog::value($key);
            }
        }

        $this->form = $form;
    }
}

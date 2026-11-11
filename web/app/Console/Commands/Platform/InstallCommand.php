<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Console\Commands\Alerts\EscalateAlertsCommand;
use App\Enums\MemberType;
use App\Enums\UserStatus;
use App\Models\Profile;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserSetting;
use App\Rules\StrongPassword;
use App\Support\Audit;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Installation de production : `php artisan xomiton:install` (GUIDE_DEPLOIEMENT.md §7).
 *
 * 1. Sème les seeders de base uniquement (DatabaseSeeder::REFERENCE_SEEDERS : rôles, permissions, réglages,
 *    référentiels, modèles d'e-mails…), tous idempotents. JAMAIS les comptes de démonstration, quel que soit
 *    l'environnement. `--check` ne fait que vérifier, sans rien écrire.
 * 2. Affiche un diagnostic de configuration (informatif : le code de sortie ne dépend que de l'installation).
 * 3. Crée le PREMIER super-administrateur (`--admin-email=` ou saisie interactive). Le mot de passe est saisi de
 *    manière masquée ou généré aléatoirement et affiché une seule fois ; il n'est jamais accepté en argument, jamais
 *    journalisé. Refus s'il existe déjà un super-admin, sauf `--force`. Les comptes admin exigent ensuite le code
 *    de vérification par e-mail à chaque connexion (AuthenticateUser::ALWAYS_OTP_ROLES) : SMTP requis.
 */
class InstallCommand extends Command
{
    public const ADMIN_ROLE = 'superadmin';

    protected $signature = 'xomiton:install
        {--check : Vérifie seulement (aucune écriture : ni seeders, ni compte)}
        {--no-admin : Ne propose ni ne crée de super-administrateur}
        {--admin-email= : Crée le premier super-administrateur avec cet e-mail}
        {--admin-name= : Nom affiché du super-administrateur (défaut : Administrateur Xomiton)}
        {--admin-pseudo= : Pseudo du super-administrateur (défaut : dérivé de l’e-mail)}
        {--generate-password : Génère un mot de passe aléatoire, affiché une seule fois (défaut hors saisie interactive)}
        {--force : Autorise un super-administrateur de plus alors qu’il en existe déjà un}';

    protected $description = 'Installe la base de production : seeders de base, diagnostic, premier super-administrateur (jamais de comptes de démonstration)';

    /** Variables XOMITON_LEGAL_* attendues, par clé de config('xomiton.legal'). */
    private const LEGAL_ENV = [
        'publisher_name' => 'XOMITON_LEGAL_PUBLISHER_NAME',
        'legal_form' => 'XOMITON_LEGAL_FORM',
        'registration' => 'XOMITON_LEGAL_REGISTRATION',
        'address' => 'XOMITON_LEGAL_ADDRESS',
        'director' => 'XOMITON_LEGAL_DIRECTOR',
        'editor_phone' => 'XOMITON_LEGAL_PHONE',
        'host_name' => 'XOMITON_LEGAL_HOST_NAME',
        'host_address' => 'XOMITON_LEGAL_HOST_ADDRESS',
        'host_country' => 'XOMITON_LEGAL_HOST_COUNTRY',
        'dpo_name' => 'XOMITON_LEGAL_DPO_NAME',
        'dpo_email' => 'XOMITON_LEGAL_DPO_EMAIL',
        'jurisdiction' => 'XOMITON_LEGAL_JURISDICTION',
    ];

    public function handle(): int
    {
        if (! $this->databaseReady()) {
            return self::FAILURE;
        }

        $checkOnly = (bool) $this->option('check');

        $this->components->info($checkOnly ? 'Vérification de l’installation (aucune écriture).' : 'Données de base');

        $ready = $checkOnly ? $this->verifyBase() : $this->seedBase();

        $this->diagnostics();

        if (! $ready) {
            return self::FAILURE;
        }

        if ($checkOnly || $this->option('no-admin')) {
            $this->reportAdmins();

            return self::SUCCESS;
        }

        return $this->handleAdmin();
    }

    private function databaseReady(): bool
    {
        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            $this->components->error('Base injoignable : '.mb_substr($e->getMessage(), 0, 160));

            return false;
        }

        if (! Schema::hasTable('migrations') || ! Schema::hasTable('roles') || ! Schema::hasTable('users')) {
            $this->components->error('Tables absentes : lancez d’abord « php artisan migrate --force ».');

            return false;
        }

        return true;
    }

    /** Seeders de base seulement ; chacun est idempotent (updateOrCreate / firstOrCreate). */
    private function seedBase(): bool
    {
        try {
            foreach (DatabaseSeeder::REFERENCE_SEEDERS as $class) {
                /** @var Seeder $seeder */
                $seeder = $this->laravel->make($class);
                $seeder->setContainer($this->laravel)->setCommand($this)->__invoke();
                $this->line('  [OK]   '.class_basename($class));
            }
        } catch (Throwable $e) {
            $this->components->error('Un seeder de base a échoué : '.mb_substr($e->getMessage(), 0, 200));

            return false;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->verifyBase();
    }

    private function verifyBase(): bool
    {
        $roles = Role::query()->where('guard_name', RolesAndPermissionsSeeder::GUARD)->count();
        $settings = Setting::query()->count();
        $hasSuper = Role::query()->where('name', self::ADMIN_ROLE)->where('guard_name', RolesAndPermissionsSeeder::GUARD)->exists();
        $ok = $hasSuper && $settings > 0;

        $this->line(sprintf('  [%s] %d rôle(s), %d réglage(s)%s', $ok ? 'OK' : 'FAIL', $roles, $settings, $hasSuper ? '' : ' ; rôle superadmin absent : relancez sans --check'));

        return $ok;
    }

    private function diagnostics(): void
    {
        $this->newLine();
        $this->components->info('Diagnostic');

        $url = (string) config('app.url');
        $env = app()->environment();
        $this->diag($env === 'production', 'APP_ENV = '.$env, 'APP_ENV = '.$env.' (production attendu)');
        $this->diag(str_starts_with($url, 'https://'), 'APP_URL = '.$url, 'APP_URL = '.($url === '' ? '(vide)' : $url).' (https attendu)');
        $this->diag(config('app.debug') === false, 'APP_DEBUG = false', 'APP_DEBUG = true (à couper en production)');

        $connection = (string) config('database.default');
        $this->diag(true, sprintf('Base : %s (%s)', $connection, (string) config("database.connections.$connection.database")), '');

        $queue = (string) config('queue.default');
        $this->diag($queue === 'database', 'File d’attente : '.$queue, 'File d’attente : '.$queue.' (database attendu, traité par cron)');
        $cache = (string) config('cache.default');
        $this->diag(true, 'Cache : '.$cache, '');

        $missing = [];
        foreach (self::LEGAL_ENV as $key => $env) {
            if (trim((string) config('xomiton.legal.'.$key, '')) === '') {
                $missing[] = $env;
            }
        }
        $this->diag($missing === [], 'Informations légales XOMITON_LEGAL_* complètes', count($missing).' variable(s) XOMITON_LEGAL_* manquante(s) : '.implode(', ', $missing));

        $fcm = (string) config('xomiton.notifications.push.fcm.driver');
        $this->diag($fcm !== 'log', 'FCM : '.$fcm, 'FCM désactivé (pilote « log » : aucun push mobile)');
        $sms = (string) config('xomiton.sms.driver');
        $this->diag($sms !== 'log', 'SMS : '.$sms, 'SMS désactivé (pilote « log » : pas de SMS de secours SOS)');
        $gemini = (bool) config('xomiton.assistant.llm_enabled');
        $this->diag(true, 'Gemini : '.($gemini ? 'ACTIVÉ (avis juridique requis)' : 'désactivé (attendu)'), '');

        try {
            $marker = Cache::get(EscalateAlertsCommand::SUCCESS_MARKER);
        } catch (Throwable) {
            $marker = null;
        }
        $this->diag(is_string($marker), 'Cron : planificateur actif', 'Cron : aucun passage constaté de schedule:run (ligne cron à installer, GUIDE_DEPLOIEMENT.md §9)');
    }

    private function diag(bool $ok, string $good, string $problem): void
    {
        $this->line($ok ? '  [OK]   '.$good : '  [WARN] '.$problem);
    }

    private function reportAdmins(): void
    {
        $count = $this->superAdminCount();
        $this->newLine();
        $this->line($count > 0
            ? sprintf('Super-administrateur·rice(s) existant·e(s) : %d.', $count)
            : 'Aucun super-administrateur : relancez avec --admin-email=<adresse>.');
    }

    private function superAdminCount(): int
    {
        return User::role(self::ADMIN_ROLE)->count();
    }

    private function handleAdmin(): int
    {
        $this->newLine();
        $existing = $this->superAdminCount();
        $email = trim((string) $this->option('admin-email'));

        if ($existing > 0 && ! $this->option('force')) {
            if ($email !== '') {
                $this->components->error('Un super-administrateur existe déjà : aucun compte créé. Ajoutez --force pour en créer un de plus (ou créez-le depuis Administration › Utilisateurs).');

                return self::FAILURE;
            }

            $this->reportAdmins();

            return self::SUCCESS;
        }

        if ($email === '') {
            if (! $this->input->isInteractive()) {
                $this->reportAdmins();

                return self::SUCCESS;
            }

            if (! $this->confirm('Créer maintenant le premier super-administrateur ?', true)) {
                $this->reportAdmins();

                return self::SUCCESS;
            }

            $email = trim((string) $this->ask('Adresse e-mail (réelle : elle recevra les codes de vérification)'));
        }

        $email = Str::lower($email);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || User::withTrashed()->where('email', $email)->exists()) {
            $this->components->error('Adresse e-mail invalide ou déjà utilisée : aucun compte créé.');

            return self::FAILURE;
        }

        $password = $this->obtainPassword($generated);

        if ($password === null) {
            return self::FAILURE;
        }

        $user = $this->createAdmin($email, $password);

        $this->components->info('Super-administrateur créé : '.$user->email.' (pseudo « '.$user->pseudo.' »).');

        if ($generated) {
            $this->newLine();
            $this->line('Mot de passe généré (affiché UNE SEULE FOIS, notez-le dans un coffre de mots de passe, puis changez-le à la première connexion) :');
            $this->line('  '.$password);
        }

        $this->line('La connexion exige un code de vérification envoyé par e-mail : le SMTP doit fonctionner avant la première connexion.');

        return self::SUCCESS;
    }

    /**
     * Saisie masquée (interactif) ou mot de passe aléatoire. Jamais lu depuis un argument.
     */
    private function obtainPassword(?bool &$generated): ?string
    {
        $generated = false;

        if ($this->option('generate-password') || ! $this->input->isInteractive()) {
            $generated = true;

            return $this->generatePassword();
        }

        if ($this->confirm('Générer un mot de passe aléatoire (sinon vous le saisissez, masqué) ?', false)) {
            $generated = true;

            return $this->generatePassword();
        }

        $password = (string) $this->secret('Mot de passe (10 caractères minimum, majuscule, minuscule, chiffre)');
        $confirmation = (string) $this->secret('Confirmez le mot de passe');

        if ($password !== $confirmation) {
            $this->components->error('Les deux saisies diffèrent : aucun compte créé.');

            return null;
        }

        $validator = Validator::make(['password' => $password], ['password' => ['required', new StrongPassword]]);

        if ($validator->fails()) {
            $this->components->error((string) $validator->errors()->first('password'));

            return null;
        }

        return $password;
    }

    private function generatePassword(): string
    {
        $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!#$%*+-=?@'];
        $all = implode('', $sets);
        $chars = array_map(static fn (string $set): string => $set[random_int(0, strlen($set) - 1)], $sets);

        while (count($chars) < 24) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    private function createAdmin(string $email, string $password): User
    {
        return DB::transaction(function () use ($email, $password): User {
            $user = User::create([
                'pseudo' => $this->uniquePseudo($email),
                'name' => trim((string) $this->option('admin-name')) ?: 'Administrateur Xomiton',
                'email' => $email,
                'password' => $password,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]);

            $user->syncRoles(['membre', self::ADMIN_ROLE]);

            Profile::firstOrCreate(['user_id' => $user->id], ['member_type' => MemberType::Member]);
            UserSetting::firstOrCreate(['user_id' => $user->id], [
                'theme' => 'auto',
                'language' => 'fr',
                'notification_prefs' => UserSetting::defaultNotificationPrefs(),
                'notification_digest' => UserSetting::DIGEST_WEEKLY,
            ]);

            // Aucun mot de passe dans le détail : seulement l'identité du compte et la façon dont il a été créé.
            Audit::logWithoutRequest('install.first_admin', $user, 'email='.$email.' ; roles=membre,'.self::ADMIN_ROLE, 'système');

            return $user;
        });
    }

    private function uniquePseudo(string $email): string
    {
        $wanted = trim((string) $this->option('admin-pseudo'));
        $base = Str::of($wanted !== '' ? $wanted : Str::before($email, '@'))->lower()->replaceMatches('/[^a-z0-9_]+/', '_')->trim('_')->limit(30, '')->toString();
        $base = $base === '' ? 'admin' : $base;
        $pseudo = $base;

        while (User::withTrashed()->where('pseudo', $pseudo)->exists()) {
            $pseudo = $base.'_'.random_int(100, 9999);
        }

        return $pseudo;
    }
}

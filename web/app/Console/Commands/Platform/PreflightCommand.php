<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Application\Mail\SmtpSettings;
use App\Console\Commands\Alerts\EscalateAlertsCommand;
use App\Console\Commands\Alerts\RetryNotificationsCommand;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Test de fumée de déploiement (P17-02, RUNBOOK §1) : `php artisan xomiton:preflight`. Code de sortie 1 au moindre
 * échec (FAIL), 0 sinon ; `--strict` compte aussi les avertissements (WARN). `--skip-cron` rétrograde le contrôle du
 * cron en avertissement, pour le tout premier passage avant l'installation des lignes cron.
 * Aucune écriture hors un fichier temporaire dans chaque dossier inscriptible.
 */
class PreflightCommand extends Command
{
    /** Âge maximal (secondes) d'un marqueur de réussite du cron : 3 min, NFR-03. */
    public const CRON_MAX_AGE = 180;

    /** Extensions sans lesquelles l'application ne tient pas. */
    private const REQUIRED_EXTENSIONS = ['ctype', 'curl', 'fileinfo', 'intl', 'json', 'mbstring', 'openssl', 'pdo_mysql', 'sodium', 'tokenizer', 'xml'];

    protected $signature = 'xomiton:preflight
        {--strict : Les avertissements font aussi échouer la commande}
        {--skip-cron : Cron pas encore installé : son contrôle devient un avertissement}';

    protected $description = 'Test de fumée de production : environnement, clé, base, stockage, cron, fournisseurs, SMTP, HTTPS';

    /** @var array{ok: int, warn: int, fail: int} */
    private array $counts = ['ok' => 0, 'warn' => 0, 'fail' => 0];

    public function handle(): int
    {
        $this->environment();
        $this->extensions();
        $this->encryption();
        $this->database();
        $this->storage();
        $this->cron();
        $this->providers();
        $this->mail();
        $this->https();
        $this->operations();

        $this->newLine();
        $this->line(sprintf('%d OK, %d avertissement(s), %d échec(s).', $this->counts['ok'], $this->counts['warn'], $this->counts['fail']));

        $failed = $this->counts['fail'] > 0 || ($this->option('strict') && $this->counts['warn'] > 0);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function environment(): void
    {
        $this->check(app()->environment('production'), 'APP_ENV=production', 'APP_ENV vaut « '.app()->environment().' »');
        $this->check(config('app.debug') === false, 'APP_DEBUG=false', 'APP_DEBUG doit être false (fuite de pile et de variables)');
        $this->check(version_compare(PHP_VERSION, '8.3.0', '>='), 'PHP '.PHP_VERSION, 'PHP '.PHP_VERSION.' : 8.3 requis (MultiPHP, chemin ea-php83 pour le cron)');
        $this->check(! filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN), 'display_errors Off', 'display_errors est actif (php.ini / MultiPHP)', warn: true);
        $this->check($this->memoryLimitMb() >= 256, 'memory_limit ≥ 256M', 'memory_limit = '.ini_get('memory_limit').' (256M conseillé)', warn: true);
        $this->check(file_exists(base_path('bootstrap/cache/config.php')), 'config:cache actif', 'Caches absents : php artisan optimize', warn: true);
    }

    private function extensions(): void
    {
        $missing = array_values(array_filter(self::REQUIRED_EXTENSIONS, static fn (string $e): bool => ! extension_loaded($e)));
        $this->check($missing === [], 'Extensions PHP requises', 'Extensions manquantes : '.implode(', ', $missing));
        $this->check(extension_loaded('zip') && class_exists(\ZipArchive::class), 'zip (archives chiffrées ADR-22)', 'zip absente : sauvegarde JSON de repli impossible', warn: true);
        $this->check(function_exists('imagewebp') || extension_loaded('imagick'), 'gd/imagick (WebP)', 'ni gd avec WebP ni imagick : images non converties', warn: true);
        $this->check(extension_loaded('gmp') || extension_loaded('bcmath'), 'gmp ou bcmath (Web Push VAPID)', 'gmp/bcmath absents : requis par minishlink/web-push', warn: true);
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        $blocked = array_values(array_intersect(['proc_open', 'exec'], $disabled));
        $this->check($blocked === [], 'proc_open / exec disponibles (mysqldump)', 'Désactivées : '.implode(', ', $blocked).' (repli : archive JSON)', warn: true);
    }

    private function encryption(): void
    {
        $key = (string) config('app.key');
        $this->check($key !== '', 'APP_KEY définie', 'APP_KEY vide : php artisan key:generate (une seule fois)');

        if ($key === '') {
            return;
        }

        try {
            $ok = decrypt(encrypt('xomiton')) === 'xomiton';
        } catch (DecryptException|Throwable) {
            $ok = false;
        }

        $this->check($ok, 'Chiffrement aller-retour', 'APP_KEY invalide : chiffrement impossible');
    }

    private function database(): void
    {
        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            $this->check(false, '', 'Base injoignable : '.mb_substr($e->getMessage(), 0, 120));

            return;
        }

        $this->check(true, 'Base de données joignable ('.DB::getDriverName().')', '');
        $this->check(Schema::hasTable('migrations') && Schema::hasTable('jobs'), 'Migrations appliquées', 'Tables absentes : php artisan migrate --force');
    }

    private function storage(): void
    {
        foreach (['storage/app', 'storage/logs', 'storage/framework/cache', 'storage/framework/views', 'bootstrap/cache'] as $dir) {
            $path = base_path($dir);
            $probe = $path.'/.preflight-'.bin2hex(random_bytes(4));
            $writable = is_dir($path) && @file_put_contents($probe, 'x') !== false;
            @unlink($probe);
            $this->check($writable, $dir.' inscriptible', $dir.' non inscriptible (chmod 775, propriétaire du compte cPanel)');
        }

        $this->check(is_file(public_path('build/manifest.json')), 'public/build présent', 'public/build/manifest.json absent : npm run build en local puis envoi');
        $this->check(is_link(public_path('storage')) || is_dir(public_path('storage')), 'public/storage (storage:link)', 'Lien absent : php artisan storage:link (ou disque servi par route)', warn: true);
    }

    private function cron(): void
    {
        $skip = (bool) $this->option('skip-cron');

        foreach (['alerts:escalate' => EscalateAlertsCommand::SUCCESS_MARKER, 'notifications:retry' => RetryNotificationsCommand::SUCCESS_MARKER] as $name => $marker) {
            $value = Cache::get($marker);
            $age = is_string($value) ? CarbonImmutable::parse($value)->diffInSeconds(now(), true) : null;
            $this->check($age !== null && $age <= self::CRON_MAX_AGE, 'Cron : '.$name.' réussi il y a moins de 3 min', $age === null
                ? 'Cron : aucun passage de '.$name.' (ligne schedule:run absente ou cache non partagé)'
                : 'Cron : '.$name.' date de '.(int) $age.' s', warn: $skip);
        }

        $this->check(config('queue.default') === 'database', 'QUEUE_CONNECTION=database', 'QUEUE_CONNECTION = '.config('queue.default').' (D7 : database)', warn: true);
        $this->check(trim((string) config('xomiton.heartbeat.url', '')) !== '', 'HEARTBEAT_URL définie', 'HEARTBEAT_URL vide : aucune supervision du cron (NFR-03)', warn: true);
    }

    private function providers(): void
    {
        foreach ([
            'Web Push' => config('xomiton.notifications.push.webpush.driver'),
            'FCM' => config('xomiton.notifications.push.fcm.driver'),
            'SMS' => config('xomiton.sms.provider'),
            'Passerelles sociales' => config('xomiton.gateways.driver'),
        ] as $label => $driver) {
            $this->check($driver !== 'log', $label.' : pilote '.$driver, $label.' : pilote « log » (rien n’est envoyé)', warn: true);
        }

        $credentials = config('xomiton.notifications.push.fcm.credentials');

        if (config('xomiton.notifications.push.fcm.driver') === 'firebase') {
            $inside = is_string($credentials) && str_starts_with((string) realpath($credentials), (string) realpath(base_path('public')));
            $this->check(is_string($credentials) && is_file($credentials) && ! $inside, 'FIREBASE_CREDENTIALS lisible hors public/', 'FIREBASE_CREDENTIALS absent ou sous public/');
        }
    }

    private function mail(): void
    {
        $configured = SmtpSettings::isConfigured() || (config('mail.default') === 'smtp' && ! in_array((string) config('mail.mailers.smtp.host'), ['', '127.0.0.1', 'localhost'], true));
        $this->check($configured, 'SMTP configuré', 'SMTP non configuré : les e-mails restent dans mail_outbox', warn: true);
    }

    private function https(): void
    {
        $url = (string) config('app.url');
        $host = (string) parse_url($url, PHP_URL_HOST);
        $this->check(str_starts_with($url, 'https://'), 'APP_URL en https', 'APP_URL « '.$url.' » n’est pas en https');
        $this->check((bool) config('session.secure'), 'Cookie de session Secure', 'SESSION_SECURE_COOKIE=false');
        $this->check($host !== '' && in_array($host, array_map(static fn (string $d): string => explode(':', $d)[0], (array) config('sanctum.stateful')), true), 'SANCTUM_STATEFUL_DOMAINS contient '.$host, 'SANCTUM_STATEFUL_DOMAINS ne contient pas '.$host.' (SOS flottant sans compte)');
    }

    private function operations(): void
    {
        $this->check(config('xomiton.backup.disk') !== 'local', 'BACKUP_DISK externe', 'BACKUP_DISK=local : copie hors serveur à prévoir (mysqldump cPanel vers stockage externe)', warn: true);
        $this->check(trim((string) config('xomiton.backup.password', '')) !== '', 'BACKUP_ARCHIVE_PASSWORD défini', 'BACKUP_ARCHIVE_PASSWORD vide : archives non chiffrées', warn: true);

        try {
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            $outbox = Schema::hasTable('mail_outbox') ? DB::table('mail_outbox')->count() : 0;
        } catch (Throwable) {
            return;
        }

        $this->check($failed === 0, 'failed_jobs vide', $failed.' job(s) en échec (php artisan queue:failed)', warn: true);
        $this->check($outbox === 0, 'mail_outbox vide', $outbox.' e-mail(s) en attente dans mail_outbox', warn: true);
    }

    private function check(bool $passed, string $ok, string $problem, bool $warn = false): void
    {
        if ($passed) {
            $this->counts['ok']++;
            $this->line('  [OK]   '.$ok);

            return;
        }

        $level = $warn ? 'warn' : 'fail';
        $this->counts[$level]++;
        $this->line('  ['.($warn ? 'WARN' : 'FAIL').'] '.$problem);
    }

    private function memoryLimitMb(): int
    {
        $limit = trim((string) ini_get('memory_limit'));

        if ($limit === '-1') {
            return PHP_INT_MAX;
        }

        $value = (int) $limit;

        return match (strtolower(substr($limit, -1))) {
            'g' => $value * 1024,
            'k' => intdiv($value, 1024),
            'm' => $value,
            default => intdiv($value, 1048576),
        };
    }
}

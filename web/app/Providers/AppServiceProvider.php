<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Alerts\Listeners\NotifyAlertRouted;
use App\Application\Alerts\Listeners\NotifySosTriggered;
use App\Application\Moderation\Listeners\NotifyModerators;
use App\Application\Newsletter\Listeners\LogCampaignSent;
use App\Application\Sync\Listeners\NotifyAdminsOfSyncConflict;
use App\Domain\Alerts\Events\AlertCreated;
use App\Domain\Alerts\Events\SosTriggered;
use App\Domain\Moderation\Events\ContentReported;
use App\Domain\Newsletter\Events\CampaignSent;
use App\Domain\Notifications\Contracts\FcmSender;
use App\Domain\Notifications\Contracts\PushSender;
use App\Domain\Notifications\Contracts\SmsSender;
use App\Domain\Sync\Events\SyncConflictDetected;
use App\Infrastructure\Notifications\NotificationDrivers;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Commune;
use App\Models\Need;
use App\Models\Population;
use App\Models\User;
use App\Models\ViolenceType;
use App\Models\Zone;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\TemplateMailChannel;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Channels\XomitonDatabaseChannel;
use App\Observers\ReferentialObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Événements de domaine (P3-08) et leurs écouteurs (notifications de P3-02 et journal d'audit). Les écouteurs vivent
     * dans app/Application/<Module>/Listeners, hors de app/Listeners : la découverte automatique de Laravel (active pour
     * App\Listeners\Auth\*) les enregistrerait une seconde fois. PollVoted et PeerValidated n'ont pas d'écouteur ici :
     * le vote n'est jamais journalisé nominativement (compteurs et PollEnded : P7), et la validation d'un·e pair·e porte
     * déjà sa notification et son audit dans le cas d'usage.
     *
     * @var array<class-string, list<class-string>>
     */
    public const DOMAIN_LISTENERS = [
        AlertCreated::class => [NotifyAlertRouted::class],
        SosTriggered::class => [NotifySosTriggered::class],
        ContentReported::class => [NotifyModerators::class],
        SyncConflictDetected::class => [NotifyAdminsOfSyncConflict::class],
        CampaignSent::class => [LogCampaignSent::class],
    ];

    /**
     * Modèles de référentiels (P3-06) dont toute écriture invalide le cache App\Application\Shared\Referentials.
     *
     * @var list<class-string<\Illuminate\Database\Eloquent\Model>>
     */
    public const REFERENTIAL_MODELS = [Zone::class, Commune::class, ViolenceType::class, Need::class, Population::class, Category::class];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerNotificationDrivers();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerAuthorization();
        $this->registerRateLimiters();
        $this->registerNotificationChannels();
        $this->registerDomainEvents();
        $this->registerReferentialObservers();
    }

    /**
     * Lot notifications (P3-02, ADR-25, Q7) : pilotes des canaux externes résolus depuis config/xomiton.php
     * (`log` tant que les paquets Web Push / FCM et le fournisseur SMS ne sont pas installés).
     */
    private function registerNotificationDrivers(): void
    {
        $this->app->singleton(PushSender::class, static fn (Application $app): PushSender => NotificationDrivers::webPush($app));
        $this->app->singleton(FcmSender::class, static fn (Application $app): FcmSender => NotificationDrivers::fcm($app));
        $this->app->singleton(SmsSender::class, static fn (Application $app): SmsSender => NotificationDrivers::sms($app));

        // Canal `database` : renseigne notifications.category et is_sensitive à l'insertion (DATABASE_DICTIONARY §9).
        $this->app->bind(DatabaseChannel::class, XomitonDatabaseChannel::class);
    }

    /**
     * Canaux Xomiton : `mail` passe par les templates en base quand toMail() renvoie une TemplateMailData (sinon canal
     * Laravel) ; `webpush`, `fcm` et `sms` sont des canaux nommés, utilisables par `via()`.
     */
    private function registerNotificationChannels(): void
    {
        Notification::extend('mail', static fn (Application $app): TemplateMailChannel => new TemplateMailChannel($app->make(MailChannel::class)));
        Notification::extend('webpush', static fn (Application $app): WebPushChannel => $app->make(WebPushChannel::class));
        Notification::extend('fcm', static fn (Application $app): FcmChannel => $app->make(FcmChannel::class));
        Notification::extend('sms', static fn (Application $app): SmsChannel => $app->make(SmsChannel::class));
    }

    private function registerDomainEvents(): void
    {
        foreach (self::DOMAIN_LISTENERS as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }

    private function registerReferentialObservers(): void
    {
        foreach (self::REFERENTIAL_MODELS as $model) {
            $model::observe(ReferentialObserver::class);
        }
    }

    /**
     * Lot « securite-http » (P2-08) : limiteurs nommés, utilisés avec le middleware throttle:<nom>
     * (DEVELOPMENT_STANDARDS.md §7 « Force brute », config/xomiton.php « rate_limits »). Les seuils ne sont jamais codés ici
     * et sont lus à chaque requête (un test ou un réglage peut les changer sans redémarrer).
     * Réponse 429 en français (lang/fr/auth.php), identique pour tout le monde : aucune énumération de compte.
     */
    private function registerRateLimiters(): void
    {
        $tooMany = static function (Request $request, array $headers): never {
            $seconds = (int) ($headers['Retry-After'] ?? 60);

            throw new ThrottleRequestsException(__('auth.throttle', ['seconds' => $seconds]), null, $headers);
        };

        // Connexion : par identifiant (e-mail ou pseudo, insensible à la casse) et IP, pour ne pas bloquer tout un réseau partagé
        // ni permettre à un tiers de verrouiller le compte d'une autre personne depuis sa propre adresse.
        RateLimiter::for('login', static fn (Request $request): Limit => Limit::perMinute(self::limit('login_per_minute'))
            ->by(self::loginIdentifier($request).'|'.$request->ip())
            ->response($tooMany));

        // Codes OTP : par adresse e-mail (OtpService applique en plus ses propres règles d'envoi).
        RateLimiter::for('otp', static fn (Request $request): Limit => Limit::perHour(self::limit('otp_per_hour'))
            ->by('otp|'.(self::stringInput($request, 'email') ?? $request->ip()))
            ->response(static function (Request $request, array $headers): never {
                $minutes = max(1, (int) ceil(((int) ($headers['Retry-After'] ?? 3600)) / 60));

                throw new ThrottleRequestsException(__('auth.otp.rate_limited', ['minutes' => $minutes]), null, $headers);
            }));

        // Signalement anonyme : par IP.
        RateLimiter::for('report', static fn (Request $request): Limit => Limit::perHour(self::limit('report_per_hour_per_ip'))
            ->by('report|'.$request->ip())
            ->response($tooMany));

        // Suivi anonyme d'un signalement : par IP, la réponse « code inconnu ou expiré » reste neutre.
        RateLimiter::for('tracking', static fn (Request $request): Limit => Limit::perHour(self::limit('tracking_per_hour_per_ip'))
            ->by('tracking|'.$request->ip())
            ->response($tooMany));

        // Newsletter : une inscription par fenêtre glissante, par session ; un client qui ne renvoie pas le cookie de session
        // (script, curl) est compté par IP, sinon chaque requête ouvrirait une session neuve et contournerait la limite.
        RateLimiter::for('newsletter', static fn (Request $request): Limit => Limit::perSecond(1, self::limit('newsletter_seconds_between'))
            ->by('newsletter|'.self::sessionOrIp($request))
            ->response($tooMany));

        // API : par utilisateur·rice authentifié·e (session Sanctum ou jeton), sinon par IP.
        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(self::limit('api_per_minute'))
            ->by('api|'.($request->user('sanctum')?->getAuthIdentifier() ?? $request->ip()))
            ->response($tooMany));
    }

    /**
     * Seuil lu dans config/xomiton.php « rate_limits » au moment de la requête.
     */
    private static function limit(string $key): int
    {
        return max(1, (int) config('xomiton.rate_limits.'.$key));
    }

    /**
     * Valeur texte d'un champ du formulaire, normalisée (minuscules, sans espaces autour) ; null si absente ou non textuelle.
     */
    private static function stringInput(Request $request, string $field): ?string
    {
        $value = $request->input($field);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::lower(trim($value));
    }

    /**
     * Identifiant saisi sur le formulaire de connexion (e-mail ou pseudo), normalisé ; sinon l'IP seule.
     */
    private static function loginIdentifier(Request $request): string
    {
        foreach (['identifier', 'email', 'login', 'pseudo'] as $field) {
            if (($value = self::stringInput($request, $field)) !== null) {
                return $value;
            }
        }

        return 'anonymous';
    }

    /**
     * Identifiant de session quand le navigateur a renvoyé le cookie de session, sinon l'adresse IP.
     */
    private static function sessionOrIp(Request $request): string
    {
        $cookiePresented = $request->cookies->get((string) config('session.cookie')) !== null;

        if ($request->hasSession() && $cookiePresented) {
            return 'session:'.$request->session()->getId();
        }

        return 'ip:'.$request->ip();
    }

    /**
     * Lot « policies-audit » (P2-07, P2-09). Les policies App\Policies\<Modèle>Policy sont découvertes
     * automatiquement pour App\Models\<Modèle> ; les écouteurs d'audit App\Listeners\Auth\* sont découverts
     * dans app/Listeners (Login, Logout, Failed, PasswordReset, Lockout) : ne pas les enregistrer une seconde fois ici.
     */
    private function registerAuthorization(): void
    {
        // Q12 c : capacité « e-mail confirmé » (publier, commenter, envoyer un message, rejoindre un groupe) ;
        // miroir du middleware App\Http\Middleware\EnsureEmailIsVerified (alias `verified`).
        Gate::define('verified', static fn (User $user): bool => $user->hasVerifiedEmail());

        // Seul endroit où un nom de rôle est codé en dur (ROLE_ET_PERMISSIONS.md §3) : le superadmin détient tout,
        // sauf la modification du journal d'audit, immuable pour tout le monde (P2-09), et les actions sur son
        // propre compte, laissées aux garde-fous de UserPolicy (P12-01 : on ne se suspend ni ne se supprime soi-même).
        Gate::before(function (User $user, string $ability, array $arguments = []): ?bool {
            if (! $user->hasRole('superadmin')) {
                return null;
            }

            $subject = $arguments[0] ?? null;

            if ($subject instanceof User && $subject->is($user)) {
                return null;
            }

            $targetsAuditLog = $subject === AuditLog::class || $subject instanceof AuditLog;

            if ($targetsAuditLog && in_array($ability, ['create', 'update', 'delete', 'restore', 'forceDelete'], true)) {
                return false;
            }

            return true;
        });
    }
}

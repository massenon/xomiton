<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Admin\AdminNavigation;
use App\Application\Alerts\Listeners\MarkAlertNotificationsSeen;
use App\Application\Alerts\Listeners\NotifyAlertEscalated;
use App\Application\Alerts\Listeners\NotifyAlertReassigned;
use App\Application\Alerts\Listeners\NotifyAlertReminder;
use App\Application\Alerts\Listeners\NotifyAlertRouted;
use App\Application\Alerts\Listeners\NotifyAuthorOfStatusChange;
use App\Application\Alerts\Listeners\NotifySosCancelled;
use App\Application\Alerts\Listeners\NotifySosSafe;
use App\Application\Alerts\Listeners\NotifySosTriggered;
use App\Application\Assistant\AssistantProvider;
use App\Application\Moderation\Listeners\NotifyModerators;
use App\Application\Newsletter\Listeners\LogCampaignSent;
use App\Application\Shared\SosBurstWatch;
use App\Application\Sync\Listeners\NotifyAdminsOfSyncConflict;
use App\Application\Sync\Pull\TombstoneRecorder;
use App\Domain\Alerts\Events\AlertAcknowledged;
use App\Domain\Alerts\Events\AlertCreated;
use App\Domain\Alerts\Events\AlertEscalated;
use App\Domain\Alerts\Events\AlertReassigned;
use App\Domain\Alerts\Events\AlertReminderDue;
use App\Domain\Alerts\Events\AlertStatusChanged;
use App\Domain\Alerts\Events\SosCancelled;
use App\Domain\Alerts\Events\SosMarkedSafe;
use App\Domain\Alerts\Events\SosTriggered;
use App\Domain\Moderation\Events\ContentReported;
use App\Domain\Newsletter\Events\CampaignSent;
use App\Domain\Notifications\Contracts\FcmSender;
use App\Domain\Notifications\Contracts\PushSender;
use App\Domain\Notifications\Contracts\SmsSender;
use App\Domain\Sync\Events\SyncConflictDetected;
use App\Infrastructure\Assistant\AssistantProviders;
use App\Infrastructure\Notifications\NotificationDrivers;
use App\Infrastructure\Session\GuestPrivateDatabaseSessionHandler;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Commune;
use App\Models\DirectoryContact;
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
use App\Observers\DirectoryContactObserver;
use App\Observers\ReferentialObserver;
use App\Support\KeyedHash;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
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
        // Lot alertes-dispatch (P5-03) : envoi dans la requête ou au cron, consigné dans alert_notifications.
        AlertReassigned::class => [NotifyAlertReassigned::class],
        AlertReminderDue::class => [NotifyAlertReminder::class],
        AlertEscalated::class => [NotifyAlertEscalated::class],
        SosCancelled::class => [NotifySosCancelled::class],
        SosMarkedSafe::class => [NotifySosSafe::class],
        AlertAcknowledged::class => [MarkAlertNotificationsSeen::class],
        AlertStatusChanged::class => [NotifyAuthorOfStatusChange::class],
        ContentReported::class => [NotifyModerators::class],
        SyncConflictDetected::class => [NotifyAdminsOfSyncConflict::class],
        CampaignSent::class => [LogCampaignSent::class],
    ];

    /**
     * Modèles de référentiels (P3-06) dont toute écriture invalide le cache App\Application\Shared\Referentials.
     *
     * @var list<class-string<Model>>
     */
    public const REFERENTIAL_MODELS = [Zone::class, Commune::class, ViolenceType::class, Need::class, Population::class, Category::class];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerNotificationDrivers();
        $this->registerAssistantProvider();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // P16-02 : toute relation chargée paresseusement (N+1) lève une exception hors production (tests compris).
        Model::preventLazyLoading(! $this->app->isProduction());

        $this->registerAuthorization();
        $this->registerRateLimiters();
        $this->registerNotificationChannels();
        $this->registerDomainEvents();
        $this->registerReferentialObservers();
        $this->registerSessionDriver();
        // Lot synchro (P9-06) : pierres tombales de `sync/pull` (suppression définitive, réattribution).
        TombstoneRecorder::register();
        // Lot admin-socle (P10-01) : pastilles du menu d'administration oubliées à chaque alerte ou compte modifié.
        AdminNavigation::watchCounters();
    }

    /**
     * Lot signaler (P5-04, RG-01, ADR-33) : le pilote `database` n'écrit ni IP ni user agent pour une session d'invité·e
     * (GuestPrivateDatabaseSessionHandler) ; une session connectée les garde pour les appareils connectés.
     */
    private function registerSessionDriver(): void
    {
        Session::extend('database', static fn (Application $app): GuestPrivateDatabaseSessionHandler => new GuestPrivateDatabaseSessionHandler(
            $app->make('db')->connection(config('session.connection')),
            (string) config('session.table', 'sessions'),
            (int) config('session.lifetime', 120),
            $app,
        ));
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
     * Lot assistant (P8-11, Q7) : fournisseur de l'assistant membre, `kb` (base de connaissances) par défaut.
     */
    private function registerAssistantProvider(): void
    {
        $this->app->singleton(AssistantProvider::class, static fn (Application $app): AssistantProvider => AssistantProviders::make($app));
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

        // Contacts d'urgence en cache (P5-06 `GET urgences`, ListEmergencyContacts) : toute écriture sur l'annuaire l'invalide.
        DirectoryContact::observe(DirectoryContactObserver::class);
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

        // Signalement anonyme : par IP, en empreinte HMAC dans le cache (ADR-33 : le md5 du limiteur se renverse).
        RateLimiter::for('report', static fn (Request $request): Limit => Limit::perHour(self::limit('report_per_hour_per_ip'))
            ->by('report|'.KeyedHash::make((string) $request->ip()))
            ->response($tooMany));

        // Suivi anonyme d'un signalement : par IP (empreinte), la réponse « code inconnu ou expiré » reste neutre.
        RateLimiter::for('tracking', static fn (Request $request): Limit => Limit::perHour(self::limit('tracking_per_hour_per_ip'))
            ->by('tracking|'.KeyedHash::make((string) $request->ip()))
            ->response($tooMany));

        // Accusé de réception par lien signé (P5-02) : par IP, alerte et fiche (empreinte), plafond propre, pour qu'un
        // CGNAT mobile ou une rafale de suivis par code depuis la même IP ne cache jamais au contact le téléphone de la
        // personne en SOS. La signature HMAC rend la force brute vaine ; le plafond borne seulement les rejeux.
        RateLimiter::for('acknowledge', static fn (Request $request): Limit => Limit::perHour(self::limit('acknowledge_per_hour_per_link'))
            ->by('acknowledge|'.KeyedHash::make(implode('|', [
                (string) $request->ip(),
                (string) ($request->route('alert') instanceof Model ? $request->route('alert')->getRouteKey() : $request->route('alert')),
                (string) $request->query('contact'),
            ])))
            ->response($tooMany));

        // Repentir et « Je suis en sécurité » d'un SOS (P5-06 `POST sos/{id}/cancel|safe`) : par IP et alerte (empreinte),
        // plafond propre pour qu'une rafale de suivis depuis la même IP (CGNAT) ne bloque jamais la personne ; le jeton
        // X-Sos-Token rend la force brute vaine, le plafond borne seulement les rejeux.
        RateLimiter::for('sos_owner', static fn (Request $request): Limit => Limit::perMinute(self::limit('sos_owner_per_minute'))
            ->by('sos_owner|'.KeyedHash::make(implode('|', [
                (string) $request->ip(),
                (string) ($request->route('alert') instanceof Model ? $request->route('alert')->getRouteKey() : $request->route('alert')),
            ])))
            ->response($tooMany));

        // Sondage de l'état d'un SOS (P5-06 `GET sos/{id}`, toutes les 10 s) : hors limiteur global `api` (un CGNAT ne doit
        // jamais cacher l'état à la personne), plafond propre et large par IP et alerte (empreinte) contre les rejeux.
        RateLimiter::for('sos_status', static fn (Request $request): Limit => Limit::perMinute(self::limit('sos_status_per_minute'))
            ->by('sos_status|'.KeyedHash::make(implode('|', [
                (string) $request->ip(),
                (string) ($request->route('alert') instanceof Model ? $request->route('alert')->getRouteKey() : $request->route('alert')),
            ])))
            ->response($tooMany));

        // Newsletter : une inscription par fenêtre glissante, par session ; un client qui ne renvoie pas le cookie de session
        // (script, curl) est compté par IP, sinon chaque requête ouvrirait une session neuve et contournerait la limite.
        // Clé en empreinte HMAC (ADR-33 : aucune IP en clair dans le cache). Consommé après validation par
        // NewsletterController (App\Support\NamedRateLimit, P4-07) : un formulaire refusé ne brûle pas la tentative.
        // Second plafond par IP et par heure : un GET suffit à ouvrir une session neuve (nouveau jeton CSRF), et chaque
        // inscription dépose un e-mail dans la boîte d'envoi partagée avec les OTP et les alertes (quota cPanel).
        RateLimiter::for('newsletter', static fn (Request $request): array => [
            Limit::perSecond(1, self::limit('newsletter_seconds_between'))
                ->by('newsletter|'.KeyedHash::make(self::sessionOrIp($request)))
                ->response($tooMany),
            Limit::perHour(self::limit('newsletter_per_hour_per_ip'))
                ->by('newsletter_ip|'.KeyedHash::make((string) $request->ip()))
                ->response($tooMany),
        ]);

        // Demande publique de suppression de compte (/supprimer-mon-compte) : chaque demande valide peut déposer un e-mail dans la
        // boîte d'envoi ; 3 par heure et par session (ou IP), 10 par heure et par IP. Consommé après validation (NamedRateLimit).
        RateLimiter::for('account_deletion', static fn (Request $request): array => [
            Limit::perHour(3)->by('account_deletion|'.KeyedHash::make(self::sessionOrIp($request)))->response($tooMany),
            Limit::perHour(10)->by('account_deletion_ip|'.KeyedHash::make((string) $request->ip()))->response($tooMany),
        ]);

        // API : par utilisateur·rice authentifié·e (session Sanctum ou jeton), sinon par IP.
        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(self::limit('api_per_minute'))
            ->by('api|'.($request->user('sanctum')?->getAuthIdentifier() ?? $request->ip()))
            ->response($tooMany));

        // Assistant (P4-01 accueil, P8-11 /assistant) : par IP, qu'on soit connecté·e ou non (se connecter ne remet pas le
        // compteur à zéro).
        RateLimiter::for('assistant', static fn (Request $request): Limit => Limit::perMinute(self::limit('assistant_per_minute'))
            ->by('assistant|'.$request->ip())
            ->response($tooMany));

        // Téléchargement et lecture d'une ressource (P4-03, /ressources/{slug}/telecharger|lire ; P6-08, API
        // ressources/{resource}/download) : par IP, adresse et ressource (empreinte HMAC, ADR-33), contre le gonflement de
        // `downloads_count` ; par ressource pour qu'un CGNAT mobile et le précache du service worker (P5-10 : une requête
        // par ressource marquée) n'épuisent pas un quota commun.
        RateLimiter::for('resource_download', static fn (Request $request): Limit => Limit::perMinute(self::limit('resource_download_per_minute'))
            ->by('resource_download|'.KeyedHash::make(implode('|', [
                (string) $request->ip(),
                (string) $request->route()?->getName(),
                (string) ($request->route('slug') ?? $request->route('resource')),
            ])))
            ->response($tooMany));

        // Lot api-me (P6-06) : DELETE me (mot de passe vérifié) et POST me/export (un fichier par demande) : par compte
        // **et par route** (des mots de passe erronés sur DELETE me ne bloquent jamais l'export RGPD).
        RateLimiter::for('account_sensitive', static fn (Request $request): Limit => Limit::perHour(self::limit('account_sensitive_per_hour'))
            ->by('account_sensitive|'.($request->route()?->getName() ?? $request->path()).'|'.($request->user('sanctum')?->getAuthIdentifier() ?? KeyedHash::make((string) $request->ip())))
            ->response($tooMany));

        // Profil (P8-07) : avatar et couverture (`POST me/profile/avatar|cover`, ré-encodage GD coûteux) par compte et par heure.
        RateLimiter::for('profile_image', static fn (Request $request): Limit => Limit::perHour(self::limit('profile_image_per_hour'))
            ->by('profile_image|'.($request->user('sanctum')?->getAuthIdentifier() ?? KeyedHash::make((string) $request->ip())))
            ->response($tooMany));

        // Paramètres (P8-09) : section « Profil » (`PATCH me/identity`) par compte et par heure ; le pseudonyme a en plus
        // son délai entre deux changements (App\Application\Account\Settings\PseudonymChanges, Q81).
        RateLimiter::for('identity_update', static fn (Request $request): Limit => Limit::perHour(self::limit('identity_update_per_hour'))
            ->by('identity_update|'.($request->user('sanctum')?->getAuthIdentifier() ?? KeyedHash::make((string) $request->ip())))
            ->response($tooMany));

        // Fil (P7-02) : publications et commentaires (`feed_write`) par compte et par minute, signalements de contenu
        // (`content_report`) par compte et par heure ; routes du groupe auth:sanctum, donc toujours un compte.
        RateLimiter::for('feed_write', static fn (Request $request): Limit => Limit::perMinute(self::limit('feed_write_per_minute'))
            ->by('feed_write|'.($request->user('sanctum')?->getAuthIdentifier() ?? KeyedHash::make((string) $request->ip())))
            ->response($tooMany));
        RateLimiter::for('content_report', static fn (Request $request): Limit => Limit::perHour(self::limit('content_report_per_hour'))
            ->by('content_report|'.($request->user('sanctum')?->getAuthIdentifier() ?? KeyedHash::make((string) $request->ip())))
            ->response($tooMany));
        // Messagerie (P8-01) : créations de conversation, envois et ajouts de participant·es, par compte et par minute.
        RateLimiter::for('messages_write', static fn (Request $request): Limit => Limit::perMinute(self::limit('messages_write_per_minute'))
            ->by('messages_write|'.($request->user('sanctum')?->getAuthIdentifier() ?? KeyedHash::make((string) $request->ip())))
            ->response($tooMany));
        // Groupes et pages (P8-02, P8-03) : créer, rejoindre, quitter, décider d'une demande, suivre, par compte et par minute.
        RateLimiter::for('community_write', static fn (Request $request): Limit => Limit::perMinute(self::limit('community_write_per_minute'))
            ->by('community_write|'.($request->user('sanctum')?->getAuthIdentifier() ?? KeyedHash::make((string) $request->ip())))
            ->response($tooMany));
        // Qui suivre (P8-06, Q79) : invitations envoyées par compte et par jour (chacune notifie l'autre personne : contre
        // le harcèlement, en plus de community_write) ; `/qui-suivre?invite=<id>` (aperçu d'une personne par identifiant)
        // par compte et par minute, sans limite pour la page sans `invite`.
        RateLimiter::for('network_invite', static fn (Request $request): Limit => Limit::perDay(self::limit('network_invites_per_day'))
            ->by('network_invite|'.($request->user('sanctum')?->getAuthIdentifier() ?? KeyedHash::make((string) $request->ip())))
            ->response($tooMany));
        // Recherche membre (P8-13) : `GET search` et `search/suggestions` par compte et par minute, hors `api` (les
        // suggestions instantanées de l'en-tête ne doivent pas épuiser le quota de la cloche ou de la messagerie). La clé
        // ne porte jamais le terme cherché.
        RateLimiter::for('search', static fn (Request $request): Limit => Limit::perMinute(self::limit('search_per_minute'))
            ->by('search|'.($request->user('sanctum')?->getAuthIdentifier() ?? KeyedHash::make((string) $request->ip())))
            ->response($tooMany));
        // Recherche globale de l'administration (P10-08) : par compte et par minute ; lu par le composant Livewire
        // (RateLimiter::limiter), la clé ne porte jamais le terme cherché.
        RateLimiter::for('admin-search', static fn (Request $request): Limit => Limit::perMinute(self::limit('admin_search_per_minute'))
            ->by('admin-search|'.($request->user()?->getAuthIdentifier() ?? KeyedHash::make((string) $request->ip()))));
        // Balise des compteurs de contenu (P10-06) : par IP (empreinte) et par minute, hors `api` ; réponse 429 sans corps.
        RateLimiter::for('content-event', static fn (Request $request): Limit => Limit::perMinute(self::limit('content_event_per_minute'))
            ->by('content-event|'.KeyedHash::make((string) $request->ip())));
        // Annuaire membre (P8-12) : demandes de rappel par compte et par heure (`idempotent:required,limit=directory_callback`),
        // à part du limiteur `report` par IP : les membres derrière un même CGNAT n'épuisent pas le quota des signalements.
        RateLimiter::for('directory_callback', static fn (Request $request): Limit => Limit::perHour(self::limit('directory_callback_per_hour'))
            ->by('directory_callback|'.($request->user('sanctum')?->getAuthIdentifier() ?? KeyedHash::make((string) $request->ip())))
            ->response($tooMany));
        // Demande du rôle pair (D-MOB-15, `POST me/role-requests`, `idempotent:required,limit=role_request`) : quelques demandes
        // par compte et par jour ; un rejeu de la même clé ne compte pas. S'ajoute à la règle « une demande en cours à la fois ».
        RateLimiter::for('role_request', static fn (Request $request): Limit => Limit::perDay(self::limit('role_request_per_day'))
            ->by('role_request|'.($request->user('sanctum')?->getAuthIdentifier() ?? KeyedHash::make((string) $request->ip())))
            ->response($tooMany));
        // Synchronisation (P9-06) : lots `sync/push` par compte (`idempotent:required,limit=sync_push` : un rejeu ne compte
        // pas) ; seul un lot fait uniquement de SOS n'est jamais limité (RG-01, comme POST sos) : un SOS rejoué ne fait pas
        // passer les autres éléments. Chaque message et signalement d'un lot compte en plus sur `messages_write` / `report`
        // (App\Http\Requests\Api\Sync\SyncItemLimiter) ; chaque SOS neuf passe par SosBurstWatch.
        RateLimiter::for('sync_push', static fn (Request $request): Limit => self::onlySos($request)
            ? Limit::none()
            : Limit::perMinute(self::limit('sync_push_per_minute'))
                ->by('sync_push|'.($request->user('sanctum')?->getAuthIdentifier() ?? KeyedHash::make((string) $request->ip())))
                ->response($tooMany));
        RateLimiter::for('network_preview', static fn (Request $request): Limit => $request->query('invite') === null
            ? Limit::none()
            : Limit::perMinute(self::limit('network_preview_per_minute'))
                ->by('network_preview|'.($request->user()?->getAuthIdentifier() ?? KeyedHash::make((string) $request->ip())))
                ->response($tooMany));

        // Vote « Cet article vous a-t-il aidé·e ? » (P4-05, POST /aide/{slug}/avis) : par session (un CGNAT mobile partage
        // une IP entre de nombreuses personnes, P12-03 ne doit pas perdre leurs voix), par IP pour un client sans cookie ;
        // second plafond large par IP contre un script qui ouvre une session par requête. Clés en empreinte HMAC (ADR-33).
        // Une voix par votant·e et par question est déjà garantie par RecordFaqFeedback.
        RateLimiter::for('faq_feedback', static fn (Request $request): array => [
            Limit::perHour(self::limit('faq_feedback_per_hour'))
                ->by('faq_feedback|'.KeyedHash::make(self::sessionOrIp($request)))
                ->response($tooMany),
            Limit::perHour(self::limit('faq_feedback_per_hour_per_ip'))
                ->by('faq_feedback_ip|'.KeyedHash::make((string) $request->ip()))
                ->response($tooMany),
        ]);

        // SOS (ARCH §4, P2-08, P5-09) : jamais de 429. Au-delà de 5 envois par minute et par IP, journal + cloche admin
        // (App\Application\Shared\SosBurstWatch : abus ou personne qui insiste, l'équipe regarde, la plateforme ne refuse
        // pas), IP en empreinte HMAC seulement (ADR-33). Route SOS : ->withoutMiddleware('throttle:api')
        // ->middleware('idempotent:required,nonblocking,limit=sos') : hors limiteur global, rejeux non comptés.
        RateLimiter::for('sos', static function (Request $request): Limit {
            app(SosBurstWatch::class)->record($request);

            return Limit::none();
        });
    }

    /**
     * Lot `sync/push` fait uniquement de SOS (au moins un élément).
     */
    private static function onlySos(Request $request): bool
    {
        $types = array_column(array_filter((array) $request->input('items', []), 'is_array'), 'type');

        return $types !== [] && array_filter($types, static fn (mixed $type): bool => $type !== 'sos') === [];
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

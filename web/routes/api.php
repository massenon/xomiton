<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\AlertController as AdminAlertController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\DeviceController as AdminDeviceController;
use App\Http\Controllers\Api\V1\Admin\InvitationController as AdminInvitationController;
use App\Http\Controllers\Api\V1\Admin\MaintenanceController as AdminMaintenanceController;
use App\Http\Controllers\Api\V1\Admin\MemberController as AdminMemberController;
use App\Http\Controllers\Api\V1\Admin\ModerationController as AdminModerationController;
use App\Http\Controllers\Api\V1\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Api\V1\Admin\RegistrationController as AdminRegistrationController;
use App\Http\Controllers\Api\V1\Admin\RoleRequestController as AdminRoleRequestController;
use App\Http\Controllers\Api\V1\Alerts\EmergencyContactController;
use App\Http\Controllers\Api\V1\Alerts\ReportController;
use App\Http\Controllers\Api\V1\Alerts\SosController;
use App\Http\Controllers\Api\V1\Assistant\AssistantConversationController;
use App\Http\Controllers\Api\V1\Assistant\AssistantEscalationController;
use App\Http\Controllers\Api\V1\AssistantQuestionController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\OtpSendController;
use App\Http\Controllers\Api\V1\Auth\OtpVerifyController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Community\GroupController;
use App\Http\Controllers\Api\V1\Community\GroupDiscussionController;
use App\Http\Controllers\Api\V1\Community\GroupMembershipController;
use App\Http\Controllers\Api\V1\Community\GroupPostController;
use App\Http\Controllers\Api\V1\Community\PageController;
use App\Http\Controllers\Api\V1\Content\ResourceController;
use App\Http\Controllers\Api\V1\Content\ThematiqueController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\Directory\DirectoryController;
use App\Http\Controllers\Api\V1\Events\EventController;
use App\Http\Controllers\Api\V1\Feed\CommentController;
use App\Http\Controllers\Api\V1\Feed\ContentReportController;
use App\Http\Controllers\Api\V1\Feed\FeedController;
use App\Http\Controllers\Api\V1\Feed\PollVoteController;
use App\Http\Controllers\Api\V1\Feed\PostController;
use App\Http\Controllers\Api\V1\Feed\PostReactionController;
use App\Http\Controllers\Api\V1\Feed\UserPostController;
use App\Http\Controllers\Api\V1\Forums\ForumController;
use App\Http\Controllers\Api\V1\Forums\ForumReplyController;
use App\Http\Controllers\Api\V1\Forums\ForumReportController;
use App\Http\Controllers\Api\V1\Me\AccountController;
use App\Http\Controllers\Api\V1\Me\DeviceController;
use App\Http\Controllers\Api\V1\Me\ExportController;
use App\Http\Controllers\Api\V1\Me\IdentityController;
use App\Http\Controllers\Api\V1\Me\NewsletterController as MeNewsletterController;
use App\Http\Controllers\Api\V1\Me\PasswordController;
use App\Http\Controllers\Api\V1\Me\PrivacyController;
use App\Http\Controllers\Api\V1\Me\ProfileImageController;
use App\Http\Controllers\Api\V1\Me\PushSubscriptionController;
use App\Http\Controllers\Api\V1\Me\RoleRequestController as MeRoleRequestController;
use App\Http\Controllers\Api\V1\Me\SecurityController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Member\MemberProfileController;
use App\Http\Controllers\Api\V1\Messaging\ConversationActionController;
use App\Http\Controllers\Api\V1\Messaging\ConversationController;
use App\Http\Controllers\Api\V1\Messaging\ConversationParticipantController;
use App\Http\Controllers\Api\V1\Messaging\MessageController;
use App\Http\Controllers\Api\V1\Network\FollowController;
use App\Http\Controllers\Api\V1\Network\InvitationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\Peer\CaseController;
use App\Http\Controllers\Api\V1\Peer\CaseFollowupController;
use App\Http\Controllers\Api\V1\Peer\RoutedAlertController;
use App\Http\Controllers\Api\V1\Peer\ValidationRuleController;
use App\Http\Controllers\Api\V1\Platform\AppVersionController;
use App\Http\Controllers\Api\V1\Platform\StatusController;
use App\Http\Controllers\Api\V1\ReferentialController;
use App\Http\Controllers\Api\V1\Search\SearchController;
use App\Http\Controllers\Api\V1\Sync\HeartbeatController;
use App\Http\Controllers\Api\V1\Sync\SyncController;
use App\Http\Middleware\BindOutboxAccount;
use App\Http\Middleware\EnsureAdminMobile;
use App\Http\Middleware\EnsureAlertRoutedToViewer;
use App\Http\Middleware\EnsureDeviceNotRevoked;
use App\Http\Middleware\EnsurePeerSpaceAccess;
use App\Http\Middleware\ForgetSearchTerms;
use App\Http\Middleware\SlideMobileTokenExpiry;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/*
 * API /api/v1 (SOFTWARE_ARCHITECTURE.md §4, docs/API.md) : REST JSON, Sanctum, réponses { data, meta?, links? }
 * (App\Http\Controllers\Api\V1\ApiController), erreurs { message, code, errors? } (App\Exceptions\ApiExceptionRenderer).
 * Tout /api/v1 porte throttle:api (bootstrap/app.php, 60/min par compte ou IP). Deux groupes (ADR-31, P5-09) :
 *   1. public, anonyme : chaque route sensible porte un limiteur nommé (AppServiceProvider::registerRateLimiters) ;
 *   2. authentifié : auth:sanctum (jeton Bearer de Flutter ou cookie de session de Vue en mode SPA).
 * Les POST rejouables par la boîte d'envoi portent `idempotent:required` (ADR-32 : même clé → même réponse), leur limiteur
 * nommé passant par `limit=<nom>` (jamais `throttle:<nom>` à côté : Laravel le trierait avant et un rejeu consommerait le quota).
 * Familles à venir : P8 communauté (conversations/ livrées, P8-01 ; groups/, pages/ livrés, P8-02, P8-03 ; events/ livré, P8-04 ; forums/ livré, P8-05 ; follows/, invitations/ livrés, P8-06), P9 pair et sync (groupe 2) ; signalements / sos / urgences livrés (P5-06),
 * me/* et notifications/* livrés (P6-06, P6-07, P6-09), feed/, posts/*, comments/* livrés (P7-02), dashboard,
 * thematiques, ressources, users/{id}/posts livrés (P6-08).
 */

// === 1. Groupe public, anonyme ===================================================================================
Route::group([], function (): void {
    Route::get('/health', static fn () => response()->json(['data' => ['status' => 'ok', 'version' => config('xomiton.version', '1.0')]]))
        ->name('api.health');

    // --- État public de la plateforme (P13-08, docs/API.md §5) : page de maintenance et mobile ; liste blanche de
    // maintenance, cache serveur 15 s (MaintenanceStatus::publicStatus). Sans session même appelé depuis le site (Referer
    // de même origine) : la réponse `Cache-Control: public` ne porte aucun Set-Cookie qu'un cache partagé garderait.
    Route::get('/status', StatusController::class)->withoutMiddleware([EnsureFrontendRequestsAreStateful::class])->name('api.status');

    // --- Version de la plateforme et version minimale de l'application mobile (P13-02, docs/API.md §5) : publique, sans
    // session (aucun Set-Cookie), `?version=` ou `X-App-Version` pour savoir si une mise à jour est disponible ou requise.
    Route::get('/app/version', AppVersionController::class)->withoutMiddleware([EnsureFrontendRequestsAreStateful::class])->name('api.app_version');

    // --- Référentiels publics (P3-06) : cache serveur 24 h (App\Application\Shared\Referentials), ETag dérivé du contenu,
    // If-None-Match → 304, Cache-Control public 1 h (middleware cache.headers, config xomiton.referentials.http_max_age) ;
    // sans session, comme `status` (revue du lot maintenance) : une réponse `public` ne porte jamais de Set-Cookie -----
    Route::middleware('cache.headers:public;max_age='.(int) config('xomiton.referentials.http_max_age', 3600).';etag')
        ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class])
        ->as('api.referentials.')
        ->group(function (): void {
            Route::get('zones', [ReferentialController::class, 'zones'])->name('zones');
            Route::get('violence-types', [ReferentialController::class, 'violenceTypes'])->name('violence_types');
            Route::get('needs', [ReferentialController::class, 'needs'])->name('needs');
        });

    // --- Authentification (P2-06, P2-12, LOGIN §9) : partagée par Flutter et l'espace Vue ; login 5/min par identifiant
    // + IP, otp 5/h par adresse ---------------------------------------------------------------------------------------
    Route::prefix('auth')->as('api.auth.')->group(function (): void {
        Route::post('register', RegisterController::class)->middleware('throttle:login')->name('register');
        Route::post('login', LoginController::class)->middleware('throttle:login')->name('login');
        Route::post('otp/send', OtpSendController::class)->middleware('throttle:otp')->name('otp.send');
        Route::post('otp/verify', OtpVerifyController::class)->middleware('throttle:login')->name('otp.verify');
        Route::post('password/forgot', ForgotPasswordController::class)->middleware('throttle:otp')->name('password.forgot');
        Route::post('password/reset', ResetPasswordController::class)->middleware('throttle:login')->name('password.reset');
    });

    // --- Assistant (P4-01, Q41 a ; P8-11) : KnowledgeBaseAnswerer, sans stockage pour un·e visiteur·se ; compte connecté :
    // fournisseur hors crise et `persist` facultatif (AskAssistant) ; 20/min par IP ---------------------------------------
    Route::post('assistant/question', AssistantQuestionController::class)->middleware('throttle:assistant')->name('api.assistant.question');

    // --- Parcours de crise (P5-06, docs/API.md §6) : anonymes, liste blanche de maintenance (RG-01) ----------------------
    // signalements : 10/h par IP, rejeux non comptés ; suivi par code 20/h par IP, réponse neutre (`mine` est dans le
    // groupe 2, d'où l'exclusion ci-dessous). sos : jamais de 429 (202 processing si l'envoi d'origine est en cours ; au-delà
    // de 5/min par IP, journal + cloche admin), lecture et actions au compte propriétaire ou au jeton X-Sos-Token (Q52 b).
    Route::prefix('signalements')->as('api.signalements.')->group(function (): void {
        // BindOutboxAccount (P5-10) : rejeu de la boîte d'envoi annonçant un autre compte que la session → sans compte.
        Route::post('/', [ReportController::class, 'store'])->middleware(['idempotent:required,limit=report', BindOutboxAccount::class])->name('store');
        Route::get('{code}', [ReportController::class, 'show'])->where('code', '(?!mine$)[^/]{1,40}')
            ->middleware('throttle:tracking')->name('show');
    });

    Route::prefix('sos')->as('api.sos.')->group(function (): void {
        Route::post('/', [SosController::class, 'store'])
            ->withoutMiddleware('throttle:api')->middleware(['idempotent:required,nonblocking,limit=sos', BindOutboxAccount::class])->name('store');
        // Suivi, repentir et « Je suis en sécurité » hors limiteur global `api` (quota par IP partagé derrière un CGNAT) :
        // limiteurs propres par IP + alerte seulement.
        Route::get('{alert:ulid}', [SosController::class, 'show'])
            ->withoutMiddleware('throttle:api')->middleware('throttle:sos_status')->name('show');
        Route::post('{alert:ulid}/cancel', [SosController::class, 'cancel'])
            ->withoutMiddleware('throttle:api')->middleware('throttle:sos_owner')->name('cancel');
        Route::post('{alert:ulid}/safe', [SosController::class, 'safe'])
            ->withoutMiddleware('throttle:api')->middleware('throttle:sos_owner')->name('safe');
    });

    // urgences : même source que /urgences (ListEmergencyContacts), cache HTTP comme les référentiels, sans session.
    Route::get('urgences', EmergencyContactController::class)
        ->middleware('cache.headers:public;max_age='.(int) config('xomiton.referentials.http_max_age', 3600).';etag')
        ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class])
        ->name('api.urgences.index');

    // --- Contenus publics pour l'application mobile (P6-08, docs/API.md §11) : mêmes requêtes que /thematiques et
    // /ressources (P4-02, P4-03) ; `conditional` (ETag, 304, Cache-Control: private, no-cache : une fiche programmée
    // apparaît à son heure) ; lien de téléchargement signé en no-store, limiteur `resource_download` ----------------
    Route::middleware('conditional')->group(function (): void {
        Route::get('thematiques', [ThematiqueController::class, 'index'])->name('api.thematiques.index');
        Route::get('thematiques/{slug}', [ThematiqueController::class, 'show'])
            ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('api.thematiques.show');
        Route::get('ressources', [ResourceController::class, 'index'])->name('api.ressources.index');
        Route::get('ressources/{resource}/download', [ResourceController::class, 'download'])->whereNumber('resource')
            ->middleware('throttle:resource_download')->name('api.ressources.download');
    });
});

// === 2. Groupe authentifié (auth:sanctum) =========================================================================
// `conditional` (P6-01) : ETag + If-None-Match → 304 sur toute lecture 200 du groupe (Cache-Control: private, no-cache).
Route::middleware(['auth:sanctum', SlideMobileTokenExpiry::class, EnsureDeviceNotRevoked::class, 'conditional'])->group(function (): void {
    Route::prefix('auth')->as('api.auth.')->group(function (): void {
        Route::post('logout', LogoutController::class)->name('logout');
    });

    // --- Mes signalements (P5-06) : alertes non anonymes du compte, pagination par page --------------------------------
    Route::get('signalements/mine', [ReportController::class, 'mine'])->name('api.signalements.mine');

    // --- Assistant membre (P8-11, docs/API.md §5) : « Parler à un humain » = alerte besoin routée (CreateReport, quota
    // `report` des signalements, rejeu idempotent) ; effacer une conversation enregistrée (404 si elle n'est pas à soi) ---
    Route::prefix('assistant')->as('api.assistant.')->group(function (): void {
        Route::post('escalate', AssistantEscalationController::class)->middleware('idempotent:required,limit=report')->name('escalate');
        Route::delete('conversations/{conversation}', [AssistantConversationController::class, 'destroy'])->whereNumber('conversation')
            ->name('conversations.destroy');
    });

    // --- Tableau de bord mobile et publications d'un profil (P6-08, docs/API.md §11) --------------------------------
    Route::get('dashboard', DashboardController::class)->name('api.dashboard');
    Route::get('users/{user}/posts', UserPostController::class)->whereNumber('user')->name('api.users.posts');

    // --- Profil d'un·e membre (P8-07, docs/API.md §18) : par pseudo, invisible → 404 ; publications : users/{id}/posts
    Route::prefix('members/{pseudo}')->where(['pseudo' => '[^/]{1,64}'])->as('api.members.')->group(function (): void {
        Route::get('/', [MemberProfileController::class, 'show'])->name('show');
        Route::get('connections', [MemberProfileController::class, 'connections'])->name('connections');
        Route::get('media', [MemberProfileController::class, 'media'])->name('media');
        Route::get('events', [MemberProfileController::class, 'events'])->name('events');
    });

    // --- Mon compte (P2-06, P6-06, P6-09) : profil, réglages, sécurité, newsletter, export RGPD, suppression, appareils
    // connectés, appareils de l'application (sync_devices) et abonnements push -----------------------------------------
    Route::prefix('me')->as('api.me.')->group(function (): void {
        Route::get('/', [MeController::class, 'show'])->name('show');
        Route::delete('/', [AccountController::class, 'destroy'])->middleware('throttle:account_sensitive')->name('destroy');
        Route::get('profile', [MeController::class, 'profile'])->name('profile.show');
        Route::patch('profile', [MeController::class, 'updateProfile'])->name('profile.update');
        // P8-07 : avatar et couverture (MediaUploader, EXIF retiré ; e-mail confirmé exigé comme toute image vue des
        // autres, P2-03), journal d'activité (soi-même seulement).
        Route::post('profile/{kind}', [ProfileImageController::class, 'store'])->whereIn('kind', ['avatar', 'cover'])
            ->middleware(['verified', 'throttle:profile_image'])->name('profile.image.store');
        Route::delete('profile/{kind}', [ProfileImageController::class, 'destroy'])->whereIn('kind', ['avatar', 'cover'])
            ->middleware('throttle:profile_image')->name('profile.image.destroy');
        Route::get('activity', [MemberProfileController::class, 'activity'])->name('activity');
        Route::get('settings', [MeController::class, 'settings'])->name('settings.show');
        Route::patch('settings', [MeController::class, 'updateSettings'])->name('settings.update');
        Route::get('security', [SecurityController::class, 'show'])->name('security.show');
        Route::patch('security', [SecurityController::class, 'update'])->name('security.update');
        Route::get('newsletter', [MeNewsletterController::class, 'show'])->name('newsletter.show');
        Route::patch('newsletter', [MeNewsletterController::class, 'update'])->name('newsletter.update');
        Route::post('export', [ExportController::class, 'store'])->middleware('throttle:account_sensitive')->name('export');
        // P8-09 (lot parametres-notifications) : section « Profil », confidentialité, mot de passe, autres appareils.
        Route::get('identity', [IdentityController::class, 'show'])->name('identity.show');
        Route::patch('identity', [IdentityController::class, 'update'])->middleware('throttle:identity_update')->name('identity.update');
        Route::get('privacy', [PrivacyController::class, 'show'])->name('privacy.show');
        Route::patch('privacy', [PrivacyController::class, 'update'])->name('privacy.update');
        Route::put('password', [PasswordController::class, 'update'])->middleware('throttle:account_sensitive')->name('password.update');
        Route::delete('sessions', [MeController::class, 'destroyOtherSessions'])->name('sessions.destroy_others');
        Route::get('sessions', [MeController::class, 'sessions'])->name('sessions.index');
        Route::delete('sessions/{id}', [MeController::class, 'destroySession'])->where('id', '[a-z]+-[A-Za-z0-9]+')->name('sessions.destroy');
        Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::delete('devices/{uuid}', [DeviceController::class, 'destroy'])->whereUuid('uuid')->name('devices.destroy');
        // P15-11 : accusé d'effacement à distance (naturellement idempotent), seul appel permis à un appareil révoqué par l'ordre.
        Route::post('devices/{uuid}/wiped', [DeviceController::class, 'wiped'])->whereUuid('uuid')->name('devices.wiped');
        // D-MOB-15 : demander le rôle pair depuis le profil (jamais d'auto-attribution : la décision est celle d'un·e admin).
        Route::get('role-requests', [MeRoleRequestController::class, 'index'])->name('role_requests.index');
        Route::post('role-requests', [MeRoleRequestController::class, 'store'])->middleware('idempotent:required,limit=role_request')->name('role_requests.store');
        Route::get('push-subscriptions', [PushSubscriptionController::class, 'index'])->name('push_subscriptions.index');
        Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push_subscriptions.store');
        Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push_subscriptions.destroy');
    });

    // --- Fil et sondages (P7-02, docs/API.md §8) : publications par ULID (ADR-27), publication invisible → 404 ; publier,
    // modifier et commenter exigent un e-mail confirmé (`verified`, Q12 c) et passent par `feed_write` (10/min), signaler par
    // `content_report` (20/h) ; réactions et votes idempotents ------------------------------------------------------
    Route::get('feed', [FeedController::class, 'index'])->name('api.feed.index');
    Route::get('feed/saved', [FeedController::class, 'saved'])->name('api.feed.saved');

    Route::prefix('posts')->as('api.posts.')->group(function (): void {
        // P7-04 (lot fil-ecrans) : publication mise en file hors connexion (store outbox) → Idempotency-Key facultative,
        // limiteur `feed_write` par `limit=` (un rejeu ne consomme pas le quota, ADR-32).
        Route::post('/', [PostController::class, 'store'])->middleware(['verified', 'idempotent:optional,limit=feed_write'])->name('store');
        Route::get('{post:ulid}', [PostController::class, 'show'])->name('show');
        Route::patch('{post:ulid}', [PostController::class, 'update'])->middleware(['verified', 'throttle:feed_write'])->name('update');
        Route::delete('{post:ulid}', [PostController::class, 'destroy'])->name('destroy');
        Route::post('{post:ulid}/like', [PostReactionController::class, 'like'])->name('like');
        Route::delete('{post:ulid}/like', [PostReactionController::class, 'unlike'])->name('unlike');
        Route::post('{post:ulid}/save', [PostReactionController::class, 'save'])->name('save');
        Route::delete('{post:ulid}/save', [PostReactionController::class, 'unsave'])->name('unsave');
        Route::post('{post:ulid}/hide', [PostReactionController::class, 'hide'])->name('hide');
        Route::delete('{post:ulid}/hide', [PostReactionController::class, 'unhide'])->name('unhide');
        Route::post('{post:ulid}/report', [ContentReportController::class, 'post'])->middleware('throttle:content_report')->name('report');
        Route::get('{post:ulid}/comments', [CommentController::class, 'index'])->name('comments.index');
        Route::post('{post:ulid}/comments', [CommentController::class, 'store'])->middleware(['verified', 'throttle:feed_write'])->name('comments.store');
        Route::post('{post:ulid}/poll/vote', [PollVoteController::class, 'store'])->name('poll.vote');
        Route::delete('{post:ulid}/poll/vote', [PollVoteController::class, 'destroy'])->name('poll.unvote');
    });

    Route::prefix('comments')->as('api.comments.')->group(function (): void {
        Route::delete('{comment}', [CommentController::class, 'destroy'])->whereNumber('comment')->name('destroy');
        Route::post('{comment}/report', [ContentReportController::class, 'comment'])->whereNumber('comment')
            ->middleware('throttle:content_report')->name('report');
    });

    // --- Cloche (P6-07) : liste paginée, compteurs par filtre (polling 30 s, ETag), lecture, suppression -------------
    Route::prefix('notifications')->as('api.notifications.')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('unread-count', [NotificationController::class, 'counts'])->name('unread_count');
        Route::post('read-all', [NotificationController::class, 'readAll'])->name('read_all');
        Route::post('{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('read');
        Route::delete('{notification}', [NotificationController::class, 'destroy'])->whereUuid('notification')->name('destroy');
    });

    // --- Messagerie (P8-01, docs/API.md §12) : conversations privées et de groupe, messages par curseur ou `after`
    // (sondage court, D7), envoi idempotent par `client_uuid` (boîte d'envoi, ADR-32), lecture, sourdine, départ,
    // blocage, signalement. Écrire et gérer un groupe exigent un e-mail confirmé (`verified`) et passent par
    // `messages_write` (30/min) ; l'envoi, rejoué par la boîte d'envoi, le prend par `limit=` (ADR-32). Bloquer et
    // signaler (gestes de sécurité, comme sur le fil) : sans `verified`, sous `throttle:api` / `content_report`.
    Route::prefix('conversations')->as('api.conversations.')->group(function (): void {
        Route::get('/', [ConversationController::class, 'index'])->name('index');
        Route::post('/', [ConversationController::class, 'store'])->middleware(['verified', 'throttle:messages_write'])->name('store');
        Route::get('recipients', [ConversationController::class, 'recipients'])->name('recipients');
        Route::get('{conversation:ulid}', [ConversationController::class, 'show'])->name('show');
        Route::patch('{conversation:ulid}', [ConversationController::class, 'update'])->middleware(['verified', 'throttle:messages_write'])->name('update');
        Route::get('{conversation:ulid}/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('{conversation:ulid}/messages', [MessageController::class, 'store'])->middleware(['verified', 'idempotent:optional,limit=messages_write'])->name('messages.store');
        Route::delete('{conversation:ulid}/messages/{message}', [MessageController::class, 'destroy'])->whereNumber('message')->middleware('throttle:messages_write')->name('messages.destroy');
        Route::post('{conversation:ulid}/read', [ConversationActionController::class, 'read'])->name('read');
        Route::post('{conversation:ulid}/mute', [ConversationActionController::class, 'mute'])->middleware('throttle:messages_write')->name('mute');
        Route::delete('{conversation:ulid}/mute', [ConversationActionController::class, 'unmute'])->middleware('throttle:messages_write')->name('unmute');
        Route::post('{conversation:ulid}/leave', [ConversationActionController::class, 'leave'])->name('leave');
        Route::post('{conversation:ulid}/block', [ConversationActionController::class, 'block'])->name('block');
        Route::delete('{conversation:ulid}/block', [ConversationActionController::class, 'unblock'])->name('unblock');
        Route::post('{conversation:ulid}/report', [ConversationActionController::class, 'report'])->middleware('throttle:content_report')->name('report');
        Route::post('{conversation:ulid}/participants', [ConversationParticipantController::class, 'store'])->middleware(['verified', 'throttle:messages_write'])->name('participants.store');
        Route::delete('{conversation:ulid}/participants/{user}', [ConversationParticipantController::class, 'destroy'])->whereNumber('user')->middleware(['verified', 'throttle:messages_write'])->name('participants.destroy');
    });

    // --- Groupes (P8-02, docs/API.md §13) : par ULID (ADR-27) ; groupe privé : fiche réduite pour une non-membre,
    // publications et membres en 404 (FR-SOC-05). Créer exige un e-mail confirmé (`verified`) ; créer, rejoindre,
    // quitter et décider d'une demande passent par `community_write` (20/min). Publier : `POST posts` avec `group`.
    Route::prefix('groups')->as('api.groups.')->group(function (): void {
        Route::get('/', [GroupController::class, 'index'])->name('index');
        Route::post('/', [GroupController::class, 'store'])->middleware(['verified', 'throttle:community_write'])->name('store');
        Route::get('{group:ulid}', [GroupController::class, 'show'])->name('show');
        Route::post('{group:ulid}/join', [GroupMembershipController::class, 'join'])->middleware('throttle:community_write')->name('join');
        Route::post('{group:ulid}/leave', [GroupMembershipController::class, 'leave'])->middleware('throttle:community_write')->name('leave');
        Route::get('{group:ulid}/members', [GroupMembershipController::class, 'index'])->name('members.index');
        Route::post('{group:ulid}/members/{user}/accept', [GroupMembershipController::class, 'accept'])->whereNumber('user')->middleware('throttle:community_write')->name('members.accept');
        Route::patch('{group:ulid}/members/{user}', [GroupMembershipController::class, 'update'])->whereNumber('user')->middleware('throttle:community_write')->name('members.update');
        Route::delete('{group:ulid}/members/{user}', [GroupMembershipController::class, 'destroy'])->whereNumber('user')->middleware('throttle:community_write')->name('members.destroy');
        Route::get('{group:ulid}/posts', [GroupPostController::class, 'index'])->name('posts.index');
        Route::post('{group:ulid}/conversation', [GroupDiscussionController::class, 'store'])->middleware('throttle:messages_write')->name('conversation');
    });

    // --- Pages d'organisations (P8-03, docs/API.md §14) : visibles des autres membres une fois approuvées (P12-05) ;
    // une page en attente n'est servie qu'à sa propriétaire et à la modération (404 sinon).
    Route::prefix('pages')->as('api.pages.')->group(function (): void {
        Route::get('/', [PageController::class, 'index'])->name('index');
        Route::post('/', [PageController::class, 'store'])->middleware(['verified', 'throttle:community_write'])->name('store');
        Route::get('{page:ulid}', [PageController::class, 'show'])->name('show');
        Route::post('{page:ulid}/follow', [PageController::class, 'follow'])->middleware('throttle:community_write')->name('follow');
        Route::delete('{page:ulid}/follow', [PageController::class, 'unfollow'])->middleware('throttle:community_write')->name('unfollow');
    });

    // --- Événements (P8-04, docs/API.md §15) : par ULID (ADR-27) ; invisible (groupe privé, brouillon, masqué) → 404.
    // `address` et `online_url` seulement pour qui a répondu « Je participe » (et l'organisation, la modération). Créer
    // exige un e-mail confirmé (`verified`), répondre « Je participe » aussi (RespondToEvent, annuler reste possible) ;
    // créer et répondre passent par `community_write` (20/min). Réponses `no-store` (adresse et lien déchiffrés).
    Route::prefix('events')->as('api.events.')->group(function (): void {
        Route::get('/', [EventController::class, 'index'])->name('index');
        Route::post('/', [EventController::class, 'store'])->middleware(['verified', 'throttle:community_write'])->name('store');
        Route::get('{event:ulid}', [EventController::class, 'show'])->name('show');
        Route::post('{event:ulid}/rsvp', [EventController::class, 'rsvp'])->middleware('throttle:community_write')->name('rsvp');
    });

    // --- Forums (P8-05, docs/API.md §16) : 5 forums fixes, sujets par ULID (ADR-27), réponses par id ; sujet invisible
    // (masqué, supprimé, forum fermé, blocage) → 404 ; anonyme → « Membre anonyme » sans identifiant (RG-02). Ouvrir un
    // sujet et répondre exigent un e-mail confirmé (`verified`) et passent par `feed_write` (10/min), signaler par
    // `content_report` (20/h), comme le fil.
    Route::prefix('forums')->as('api.forums.')->group(function (): void {
        Route::get('/', [ForumController::class, 'index'])->name('index');
        Route::get('topics', [ForumController::class, 'topics'])->name('topics.index');
        Route::post('topics', [ForumController::class, 'store'])->middleware(['verified', 'throttle:feed_write'])->name('topics.store');
        Route::get('topics/{topic:ulid}', [ForumController::class, 'show'])->name('topics.show');
        Route::delete('topics/{topic:ulid}', [ForumController::class, 'destroy'])->name('topics.destroy');
        Route::post('topics/{topic:ulid}/report', [ForumReportController::class, 'topic'])->middleware('throttle:content_report')->name('topics.report');
        Route::get('topics/{topic:ulid}/replies', [ForumReplyController::class, 'index'])->name('replies.index');
        Route::post('topics/{topic:ulid}/replies', [ForumReplyController::class, 'store'])->middleware(['verified', 'throttle:feed_write'])->name('replies.store');
        Route::delete('replies/{reply}', [ForumReplyController::class, 'destroy'])->whereNumber('reply')->name('replies.destroy');
        Route::post('replies/{reply}/report', [ForumReportController::class, 'reply'])->whereNumber('reply')->middleware('throttle:content_report')->name('replies.report');
    });

    // --- Qui suivre et invitations (P8-06, docs/API.md §17) : suggestions (pseudonyme, zone affichée, rôle public, jamais
    // de donnée privée), suivre / ne plus suivre (idempotents), invitations reçues et envoyées en attente, inviter (sans
    // doublon : l'invitation en attente est rendue), accepter, refuser, annuler. Blocage dans un sens ou dans l'autre :
    // personne absente des suggestions, invitation en 404. Inviter exige un e-mail confirmé (`verified`, l'invitation
    // notifie l'autre personne) ; tous les gestes passent par `community_write` (20/min), inviter aussi par
    // `network_invite` (30/jour, Q79). Listes par curseur (`meta.next_cursor`).
    Route::prefix('follows')->as('api.follows.')->group(function (): void {
        Route::get('suggestions', [FollowController::class, 'suggestions'])->name('suggestions');
        Route::post('/', [FollowController::class, 'store'])->middleware('throttle:community_write')->name('store');
        Route::delete('{user}', [FollowController::class, 'destroy'])->whereNumber('user')->middleware('throttle:community_write')->name('destroy');
    });

    Route::prefix('invitations')->as('api.invitations.')->group(function (): void {
        Route::get('/', [InvitationController::class, 'index'])->name('index');
        Route::post('/', [InvitationController::class, 'store'])->middleware(['verified', 'throttle:community_write', 'throttle:network_invite'])->name('store');
        Route::post('{invitation}/accept', [InvitationController::class, 'accept'])->whereNumber('invitation')->middleware('throttle:community_write')->name('accept');
        Route::post('{invitation}/refuse', [InvitationController::class, 'refuse'])->whereNumber('invitation')->middleware('throttle:community_write')->name('refuse');
        Route::delete('{invitation}', [InvitationController::class, 'destroy'])->whereNumber('invitation')->middleware('throttle:community_write')->name('destroy');
    });

    // --- Synchronisation hors connexion (P9-06, lot synchro, docs/API.md §21, ADR-26, ADR-32) : appareils enregistrés
    // seulement (`X-Device-Id`, sinon 403 `device_required` / `device_revoked`). push : lot de 50 éléments, Idempotency-Key
    // = uuid du lot (réponse d'origine rejouée ; lot encore en cours : 202 `processing` + Retry-After, jamais 429, P2-08),
    // éléments dédoublonnés par uuid ; hors `throttle:api` comme POST sos, limiteur `sync_push` par compte après la clé
    // (un lot fait seulement de SOS n'est jamais limité) et quotas des routes dédiées par élément (PushBatch). push et
    // heartbeat sont dans la liste blanche de maintenance (EnsureNotInMaintenance, RG-01 ; seuls SOS et signalements y
    // sont traités hors administration).
    Route::prefix('sync')->as('api.sync.')->group(function (): void {
        Route::post('push', [SyncController::class, 'push'])
            ->withoutMiddleware('throttle:api')->middleware('idempotent:required,nonblocking,limit=sync_push')->name('push');
        Route::get('pull', [SyncController::class, 'pull'])->name('pull');
        Route::get('conflicts', [SyncController::class, 'conflicts'])->name('conflicts');
        // Le pair tranche lui-même un conflit 409 de ses appareils (valeur serveur ou appareil), sur ses propres dossiers.
        Route::post('conflicts/{conflict}/resolve', [SyncController::class, 'resolve'])->whereNumber('conflict')
            ->middleware('idempotent')->name('conflicts.resolve');
    });
    Route::post('heartbeat', HeartbeatController::class)->name('api.heartbeat');

    // --- Annuaire membre (P8-12, lot annuaire-membre, docs/API.md §19) : fiches selon la portée du compte (publiques pour
    // un·e membre, réseau compris pour pair·e validé·e, modération, administration), `no-store`. Demande de rappel →
    // RequestCallback (P5-02, sans RG-04) : rejouable (`idempotent:required`), limiteur `directory_callback` (par compte)
    // après la clé.
    Route::prefix('directory')->as('api.directory.')->group(function (): void {
        Route::get('/', [DirectoryController::class, 'index'])->name('index');
        Route::post('{contact}/callback', [DirectoryController::class, 'callback'])->whereNumber('contact')
            ->middleware('idempotent:required,limit=directory_callback')->name('callback');
    });

    // --- Espace pair (P9-02, lot dossiers-api-pair, docs/API.md §22) : compte actif `peer_space.access` (RG-03,
    // EnsurePeerSpaceAccess, sinon 403). Dossiers par code **dans le scope** visibleTo (dossier d'un autre ou code inconnu
    // → même 404), collecte et suivi idempotents par `client_uuid` et `Idempotency-Key` facultative (ADR-32 : autre
    // contenu → 422 `idempotency_key_reused`), pas de suppression ; suivi des victimes ; alertes
    // routées vers mes fiches d'annuaire (accuser, prendre en charge, clore — ni réattribution ni export ; non routée →
    // 403, même pour l'administration : EnsureAlertRoutedToViewer) ; règles de validation actives (ETag). Réponses
    // no-store sauf les règles.
    Route::prefix('pair')->as('api.pair.')->middleware(EnsurePeerSpaceAccess::class)->group(function (): void {
        Route::get('cases', [CaseController::class, 'index'])->name('cases.index');
        Route::post('cases', [CaseController::class, 'store'])->middleware('idempotent')->name('cases.store');
        Route::get('cases/{code}', [CaseController::class, 'show'])->where('code', '[^/]{1,20}')->name('cases.show');
        Route::patch('cases/{code}', [CaseController::class, 'update'])->where('code', '[^/]{1,20}')->name('cases.update');
        Route::get('cases/{code}/followups', [CaseFollowupController::class, 'index'])->where('code', '[^/]{1,20}')->name('cases.followups.index');
        Route::post('cases/{code}/followups', [CaseFollowupController::class, 'store'])->where('code', '[^/]{1,20}')
            ->middleware('idempotent')->name('cases.followups.store');
        Route::patch('cases/{code}/followups/{followup}', [CaseFollowupController::class, 'update'])->where('code', '[^/]{1,20}')
            ->whereNumber('followup')->name('cases.followups.update');
        Route::get('alerts', [RoutedAlertController::class, 'index'])->name('alerts.index');
        Route::middleware(EnsureAlertRoutedToViewer::class)->group(function (): void {
            Route::get('alerts/{alert:ulid}', [RoutedAlertController::class, 'show'])->name('alerts.show');
            Route::post('alerts/{alert:ulid}/acknowledge', [RoutedAlertController::class, 'acknowledge'])->name('alerts.acknowledge');
            Route::post('alerts/{alert:ulid}/status', [RoutedAlertController::class, 'status'])->name('alerts.status');
        });
        Route::get('validation-rules', ValidationRuleController::class)->name('validation_rules.index');
    });
});

// === 2 bis. API admin mobile (P15-16, application mobile unique, docs/API.md §23) ================================
// Groupe `admin/*` : compte actif détenant `admin_mobile.access` (admin, superadmin ; EnsureAdminMobile, sinon 403), limiteur
// `admin_api` par compte en plus de `api`, réponses `no-store`, journal d'audit `source=mobile`. Les mêmes cas d'usage que
// l'administration Livewire (aucune logique dupliquée ; chacun autorise par policy). Écritures : `Idempotency-Key`
// obligatoire (`idempotent:required,limit=admin_write` : un rejeu rend la réponse d'origine et ne consomme pas le quota) ;
// actions à fort impact (maintenance, rôles, invitations, refus d'une demande, réattribution) : confirmation `step-up`
// (`POST admin/step-up` → en-tête `X-Step-Up-Token`, usage unique).
Route::prefix('admin')->as('api.admin.')->middleware(['auth:sanctum', SlideMobileTokenExpiry::class, EnsureDeviceNotRevoked::class, 'conditional', EnsureAdminMobile::class, 'throttle:admin_api'])->group(function (): void {
    $write = 'idempotent:required,limit=admin_write';

    Route::get('dashboard', [AdminDashboardController::class, 'show'])->name('dashboard');
    Route::post('step-up', [AdminDashboardController::class, 'stepUp'])->middleware('throttle:admin_step_up')->name('step_up');

    Route::prefix('alerts')->as('alerts.')->group(function () use ($write): void {
        Route::get('/', [AdminAlertController::class, 'index'])->name('index');
        Route::get('{alert:ulid}', [AdminAlertController::class, 'show'])->name('show');
        Route::get('{alert:ulid}/contacts', [AdminAlertController::class, 'contacts'])->name('contacts');
        Route::post('{alert:ulid}/acknowledge', [AdminAlertController::class, 'acknowledge'])->middleware($write)->name('acknowledge');
        Route::post('{alert:ulid}/take', [AdminAlertController::class, 'take'])->middleware($write)->name('take');
        Route::post('{alert:ulid}/status', [AdminAlertController::class, 'status'])->middleware($write)->name('status');
        Route::post('{alert:ulid}/close', [AdminAlertController::class, 'close'])->middleware($write)->name('close');
        Route::post('{alert:ulid}/reassign', [AdminAlertController::class, 'reassign'])->middleware($write)->name('reassign');
        Route::post('{alert:ulid}/notes', [AdminAlertController::class, 'note'])->middleware($write)->name('notes.store');
    });

    Route::get('registrations', [AdminRegistrationController::class, 'index'])->name('registrations.index');
    Route::post('registrations/{user}/approve', [AdminRegistrationController::class, 'approve'])->whereNumber('user')->middleware($write)->name('registrations.approve');
    Route::post('registrations/{user}/reject', [AdminRegistrationController::class, 'reject'])->whereNumber('user')->middleware($write)->name('registrations.reject');

    // D-MOB-15 : file des demandes du rôle pair (`peers.assign`) ; décider exige le step-up.
    Route::get('role-requests', [AdminRoleRequestController::class, 'index'])->name('role_requests.index');
    Route::post('role-requests/{roleRequest:ulid}/approve', [AdminRoleRequestController::class, 'approve'])->middleware($write)->name('role_requests.approve');
    Route::post('role-requests/{roleRequest:ulid}/reject', [AdminRoleRequestController::class, 'reject'])->middleware($write)->name('role_requests.reject');

    Route::get('members', [AdminMemberController::class, 'index'])->name('members.index');
    Route::get('members/{user}', [AdminMemberController::class, 'show'])->whereNumber('user')->name('members.show');
    Route::post('members/{user}/roles/peer', [AdminMemberController::class, 'grantPeer'])->whereNumber('user')->middleware($write)->name('members.peer.grant');
    Route::delete('members/{user}/roles/peer', [AdminMemberController::class, 'revokePeer'])->whereNumber('user')->middleware($write)->name('members.peer.revoke');
    Route::put('members/{user}/role', [AdminMemberController::class, 'changeRole'])->whereNumber('user')->middleware($write)->name('members.role');

    // P15-11 : appareils d'un compte et effacement à distance (`device.wipe`, step-up).
    Route::get('members/{user}/devices', [AdminDeviceController::class, 'index'])->whereNumber('user')->name('members.devices');
    Route::post('devices/{device}/wipe', [AdminDeviceController::class, 'wipe'])->whereNumber('device')->middleware($write)->name('devices.wipe');

    Route::get('invitations', [AdminInvitationController::class, 'index'])->name('invitations.index');
    Route::post('invitations', [AdminInvitationController::class, 'store'])->middleware($write)->name('invitations.store');
    Route::delete('invitations/{invitation}', [AdminInvitationController::class, 'destroy'])->whereNumber('invitation')->middleware($write)->name('invitations.destroy');

    Route::get('maintenance', [AdminMaintenanceController::class, 'show'])->name('maintenance.show');
    Route::post('maintenance/enable', [AdminMaintenanceController::class, 'enable'])->middleware($write)->name('maintenance.enable');
    Route::post('maintenance/disable', [AdminMaintenanceController::class, 'disable'])->middleware($write)->name('maintenance.disable');

    Route::get('moderation/reports', [AdminModerationController::class, 'index'])->name('moderation.index');
    Route::get('moderation/reports/{report}', [AdminModerationController::class, 'show'])->whereNumber('report')->name('moderation.show');
    Route::post('moderation/reports/{report}/decision', [AdminModerationController::class, 'decision'])->whereNumber('report')->middleware($write)->name('moderation.decision');
    Route::post('moderation/reports/{report}/warn', [AdminModerationController::class, 'warn'])->whereNumber('report')->middleware($write)->name('moderation.warn');
    Route::post('moderation/reports/{report}/hide', [AdminModerationController::class, 'hide'])->whereNumber('report')->middleware($write)->name('moderation.hide');
    Route::post('moderation/reports/{report}/restore', [AdminModerationController::class, 'restore'])->whereNumber('report')->middleware($write)->name('moderation.restore');
    Route::post('moderation/reports/{report}/dismiss', [AdminModerationController::class, 'dismiss'])->whereNumber('report')->middleware($write)->name('moderation.dismiss');

    Route::get('notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [AdminNotificationController::class, 'readAll'])->middleware($write)->name('notifications.read_all');
});

// === 3. Recherche membre (P8-13, lot recherche-membre, docs/API.md §20) ==========================================
// Ressources, thématiques, contacts de l'annuaire (portée du compte) et membres trouvables ; réponses no-store, jamais le
// terme renvoyé. Hors du groupe 2 pour que ForgetSearchTerms passe **avant** auth:sanctum (le terme ne reste jamais dans
// la session, même pour une requête refusée) ; hors `throttle:api` : les suggestions instantanées de l'en-tête
// (anti-rebond) ont leur propre quota `search` par compte et n'épuisent pas celui du reste de l'espace (cloche, messages).
// `conditional` comme tout le groupe authentifié : sans effet ici (réponses no-store, jamais d'ETag).
Route::middleware([ForgetSearchTerms::class.':sanctum', 'auth:sanctum', SlideMobileTokenExpiry::class, EnsureDeviceNotRevoked::class, 'conditional', 'throttle:search'])
    ->withoutMiddleware('throttle:api')
    ->prefix('search')->as('api.search.')->group(function (): void {
        Route::get('/', [SearchController::class, 'index'])->name('index');
        Route::get('suggestions', [SearchController::class, 'suggestions'])->name('suggestions');
    });

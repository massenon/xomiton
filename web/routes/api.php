<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Alerts\EmergencyContactController;
use App\Http\Controllers\Api\V1\Alerts\ReportController;
use App\Http\Controllers\Api\V1\Alerts\SosController;
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
use App\Http\Controllers\Api\V1\Me\NewsletterController as MeNewsletterController;
use App\Http\Controllers\Api\V1\Me\PushSubscriptionController;
use App\Http\Controllers\Api\V1\Me\SecurityController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Messaging\ConversationActionController;
use App\Http\Controllers\Api\V1\Messaging\ConversationController;
use App\Http\Controllers\Api\V1\Messaging\ConversationParticipantController;
use App\Http\Controllers\Api\V1\Messaging\MessageController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ReferentialController;
use App\Http\Middleware\BindOutboxAccount;
use Illuminate\Support\Facades\Route;

/*
 * API /api/v1 (SOFTWARE_ARCHITECTURE.md §4, docs/API.md) : REST JSON, Sanctum, réponses { data, meta?, links? }
 * (App\Http\Controllers\Api\V1\ApiController), erreurs { message, code, errors? } (App\Exceptions\ApiExceptionRenderer).
 * Tout /api/v1 porte throttle:api (bootstrap/app.php, 60/min par compte ou IP). Deux groupes (ADR-31, P5-09) :
 *   1. public, anonyme : chaque route sensible porte un limiteur nommé (AppServiceProvider::registerRateLimiters) ;
 *   2. authentifié : auth:sanctum (jeton Bearer de Flutter ou cookie de session de Vue en mode SPA).
 * Les POST rejouables par la boîte d'envoi portent `idempotent:required` (ADR-32 : même clé → même réponse), leur limiteur
 * nommé passant par `limit=<nom>` (jamais `throttle:<nom>` à côté : Laravel le trierait avant et un rejeu consommerait le quota).
 * Familles à venir : P8 communauté (conversations/ livrées, P8-01 ; groups/, pages/ livrés, P8-02, P8-03 ; events/ livré, P8-04 ; forums/ livré, P8-05), P9 pair et sync (groupe 2) ; signalements / sos / urgences livrés (P5-06),
 * me/* et notifications/* livrés (P6-06, P6-07, P6-09), feed/, posts/*, comments/* livrés (P7-02), dashboard,
 * thematiques, ressources, users/{id}/posts livrés (P6-08).
 */

// === 1. Groupe public, anonyme ===================================================================================
Route::group([], function (): void {
    Route::get('/health', static fn () => response()->json(['data' => ['status' => 'ok', 'version' => config('xomiton.version', '1.0')]]))
        ->name('api.health');

    // --- Référentiels publics (P3-06) : cache serveur 24 h (App\Application\Shared\Referentials), ETag dérivé du contenu,
    // If-None-Match → 304, Cache-Control public 1 h (middleware cache.headers, config xomiton.referentials.http_max_age) -----
    Route::middleware('cache.headers:public;max_age='.(int) config('xomiton.referentials.http_max_age', 3600).';etag')
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

    // --- Assistant public de l'accueil (P4-01, Q41 a) : KnowledgeBaseAnswerer, sans stockage ; 20/min par IP ---------------
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

    // urgences : même source que /urgences (ListEmergencyContacts), cache HTTP comme les référentiels.
    Route::get('urgences', EmergencyContactController::class)
        ->middleware('cache.headers:public;max_age='.(int) config('xomiton.referentials.http_max_age', 3600).';etag')
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
Route::middleware(['auth:sanctum', 'conditional'])->group(function (): void {
    Route::prefix('auth')->as('api.auth.')->group(function (): void {
        Route::post('logout', LogoutController::class)->name('logout');
    });

    // --- Mes signalements (P5-06) : alertes non anonymes du compte, pagination par page --------------------------------
    Route::get('signalements/mine', [ReportController::class, 'mine'])->name('api.signalements.mine');

    // --- Tableau de bord mobile et publications d'un profil (P6-08, docs/API.md §11) --------------------------------
    Route::get('dashboard', DashboardController::class)->name('api.dashboard');
    Route::get('users/{user}/posts', UserPostController::class)->whereNumber('user')->name('api.users.posts');

    // --- Mon compte (P2-06, P6-06, P6-09) : profil, réglages, sécurité, newsletter, export RGPD, suppression, appareils
    // connectés, appareils de l'application (sync_devices) et abonnements push -----------------------------------------
    Route::prefix('me')->as('api.me.')->group(function (): void {
        Route::get('/', [MeController::class, 'show'])->name('show');
        Route::delete('/', [AccountController::class, 'destroy'])->middleware('throttle:account_sensitive')->name('destroy');
        Route::get('profile', [MeController::class, 'profile'])->name('profile.show');
        Route::patch('profile', [MeController::class, 'updateProfile'])->name('profile.update');
        Route::get('settings', [MeController::class, 'settings'])->name('settings.show');
        Route::patch('settings', [MeController::class, 'updateSettings'])->name('settings.update');
        Route::get('security', [SecurityController::class, 'show'])->name('security.show');
        Route::patch('security', [SecurityController::class, 'update'])->name('security.update');
        Route::get('newsletter', [MeNewsletterController::class, 'show'])->name('newsletter.show');
        Route::patch('newsletter', [MeNewsletterController::class, 'update'])->name('newsletter.update');
        Route::post('export', [ExportController::class, 'store'])->middleware('throttle:account_sensitive')->name('export');
        Route::get('sessions', [MeController::class, 'sessions'])->name('sessions.index');
        Route::delete('sessions/{id}', [MeController::class, 'destroySession'])->where('id', '[a-z]+-[A-Za-z0-9]+')->name('sessions.destroy');
        Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::delete('devices/{uuid}', [DeviceController::class, 'destroy'])->whereUuid('uuid')->name('devices.destroy');
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
});

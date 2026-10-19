<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\RegistrationConsentsController;
use App\Http\Controllers\Auth\RegistrationProfileController;
use App\Http\Controllers\Auth\RegistrationSecurityController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Web\Account\PersonalDataExportController;
use App\Http\Controllers\Web\ActualitesIndexController;
use App\Http\Controllers\Web\ActualitesShowController;
use App\Http\Controllers\Web\AideFeedbackController;
use App\Http\Controllers\Web\AideIndexController;
use App\Http\Controllers\Web\AideShowController;
use App\Http\Controllers\Web\AlertAcknowledgementController;
use App\Http\Controllers\Web\Alerts\ReportPageController;
use App\Http\Controllers\Web\Alerts\ReportTrackingController;
use App\Http\Controllers\Web\ConfidentialiteController;
use App\Http\Controllers\Web\DesignSystemController;
use App\Http\Controllers\Web\DoGlegbeController;
use App\Http\Controllers\Web\EventsPageController;
use App\Http\Controllers\Web\Feed\SharedPostController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\LegacyAideRedirectController;
use App\Http\Controllers\Web\LegacyRessourceRedirectController;
use App\Http\Controllers\Web\LegacyThematiqueRedirectController;
use App\Http\Controllers\Web\LoisController;
use App\Http\Controllers\Web\Member\DesignSystemVueController;
use App\Http\Controllers\Web\Member\FeedPageController;
use App\Http\Controllers\Web\Member\ForumsPageController;
use App\Http\Controllers\Web\Member\GroupsPageController;
use App\Http\Controllers\Web\Member\MessagesPageController;
use App\Http\Controllers\Web\Member\PagesPageController;
use App\Http\Controllers\Web\Member\PlaceholderPageController;
use App\Http\Controllers\Web\Newsletter\LegacyNewsletterRedirectController;
use App\Http\Controllers\Web\Newsletter\NewsletterConfirmationController;
use App\Http\Controllers\Web\Newsletter\NewsletterController;
use App\Http\Controllers\Web\Newsletter\NewsletterTrackingController;
use App\Http\Controllers\Web\Newsletter\NewsletterUnsubscribeController;
use App\Http\Controllers\Web\RessourceDownloadController;
use App\Http\Controllers\Web\RessourcesIndexController;
use App\Http\Controllers\Web\RessourcesShowController;
use App\Http\Controllers\Web\RessourceStreamController;
use App\Http\Controllers\Web\RobotsController;
use App\Http\Controllers\Web\ServiceWorkerController;
use App\Http\Controllers\Web\SignedMediaController;
use App\Http\Controllers\Web\SitemapController;
use App\Http\Controllers\Web\SosPageController;
use App\Http\Controllers\Web\ThematiquesIndexController;
use App\Http\Controllers\Web\ThematiquesShowController;
use App\Http\Controllers\Web\UrgencesController;
use App\Http\Controllers\Web\ViolencesController;
use App\Http\Middleware\EnsureNotInMaintenance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InertiaForMembers;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
 * Routes web : pages publiques (Blade) et espace membre / pair (Vue + Inertia, D9).
 * Noms « module.action », chemins en français (MODULE_SPECIFICATIONS.md §A à §C).
 * Les écrans membres provisoires ($memberPlaceholder) sont remplacés phase par phase (docs/IMPLEMENTATION_PLAN.md).
 */

// P6-03 : écran provisoire d'une route membre = page Inertia `Member/Placeholder` dans le gabarit membre Vue ; titre
// = clé de lang/fr/menus.php (member_pages.*), traduite à la requête par Member\PlaceholderPageController.
$memberPlaceholder = static function (string $uri, string $name, string $title, string $prototype, bool $aside = false): RoutingRoute {
    return Route::get($uri, PlaceholderPageController::class)
        ->name($name)
        ->defaults('title', 'menus.member_pages.'.$title)
        ->defaults('prototype', $prototype)
        ->defaults('aside', $aside);
};

// --- Site public (P4) -------------------------------------------------------
Route::get('/', HomeController::class)->name('home'); // P4-01
// P4-02 : publiques et indexables (Q19 a) ; fiche résolue par ShowThematique (publiées seulement), pas de liaison implicite.
Route::get('/thematiques', ThematiquesIndexController::class)->name('thematiques.index');
Route::get('/thematiques/{slug}', ThematiquesShowController::class)->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('thematiques.show');
// P4-03 : publiques et indexables (Q19 a) ; fiche résolue par ShowResource (publiées seulement, `article` → /actualites).
// Téléchargement : compte puis 302 vers un lien signé `media.signed` ; lecture vidéo / audio : fichier servi avec `Range`
// (206), sans redirection. Adresses stables pour le cache hors connexion (P5-10) ; limiteur nommé `resource_download`.
Route::get('/ressources', RessourcesIndexController::class)->name('ressources.index');
Route::get('/ressources/{slug}', RessourcesShowController::class)->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('ressources.show');
Route::get('/ressources/{slug}/telecharger', RessourceDownloadController::class)->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->middleware('throttle:resource_download')->name('ressources.download');
Route::get('/ressources/{slug}/lire', RessourceStreamController::class)->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->middleware('throttle:resource_download')->name('ressources.stream');
// P4-04 : publiques et indexables (Q19 a), vous ; auto-évaluation (/violences), recherche et version simple (/lois), quiz
// (/do-glegbe) entièrement dans le navigateur, sans requête réseau.
Route::get('/violences', ViolencesController::class)->name('violences');
Route::get('/lois', LoisController::class)->name('lois');
Route::get('/do-glegbe', DoGlegbeController::class)->name('do-glegbe');
// P4-05 : centre d'aide public et indexable (vous) ; questions du périmètre HelpCenter (catégories `faq`), adressées par
// slug ; vote « Cet article vous a-t-il aidé·e ? » persisté dans `faq_feedback` (POST + @csrf, limiteur `faq_feedback`).
Route::get('/aide', AideIndexController::class)->name('aide.index');
Route::get('/aide/{slug}', AideShowController::class)->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('aide.show');
Route::post('/aide/{slug}/avis', AideFeedbackController::class)->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->middleware('throttle:faq_feedback')->name('aide.feedback');
// P4-10 : actualités = ressources `article` publiées (Q1 a) ; une ressource d'un autre type → 301 /ressources/{slug}.
Route::get('/actualites', ActualitesIndexController::class)->name('actualites.index');
Route::get('/actualites/{slug}', ActualitesShowController::class)->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('actualites.show');
// P4-06 : gabarit sobre, tu (Q47) ; même source que GET /api/v1/urgences (ListEmergencyContacts) ; nom `urgences`
// servi pendant la maintenance (EnsureNotInMaintenance, RG-01).
Route::get('/urgences', UrgencesController::class)->name('urgences');
// P4-08 : charte, confidentialité, conditions et signalement sur une page (ancres), publique et indexable (vous).
Route::get('/confidentialite', ConfidentialiteController::class)->name('confidentialite');
// P4-07 : newsletter publique (vous). `/newsletter` indexable ; remerciement, confirmation et désinscription en noindex,
// hors sitemap ; seules les pages à jeton (confirmation, désinscription) sont en plus exclues par robots.txt. Inscription :
// limiteurs `newsletter` (session 1 / 20 s, IP 10 / h) consommés après validation par le contrôleur (pas de middleware
// throttle), réponse et durée identiques quelle que soit l'adresse. Noms `newsletter.*`
// servis pendant la maintenance (EnsureNotInMaintenance, RG-01).
Route::get('/newsletter', [NewsletterController::class, 'show'])->name('newsletter.show');
Route::post('/newsletter', [NewsletterController::class, 'store'])->name('newsletter.subscribe');
Route::get('/newsletter/merci', [NewsletterController::class, 'thanks'])->name('newsletter.thanks');
Route::get('/newsletter/confirmer/{token}', [NewsletterConfirmationController::class, 'show'])->where('token', '[A-Za-z0-9]{1,64}')->name('newsletter.confirm');
Route::post('/newsletter/confirmer/{token}', [NewsletterConfirmationController::class, 'store'])->where('token', '[A-Za-z0-9]{1,64}')->name('newsletter.confirm.store');
Route::get('/newsletter/desinscription/{token}', [NewsletterUnsubscribeController::class, 'show'])->where('token', '[A-Za-z0-9]{1,64}')->name('newsletter.unsubscribe');
// « One-click » RFC 8058 : la messagerie poste sans cookie ni jeton CSRF ; le jeton de l'adresse fait foi.
Route::post('/newsletter/desinscription/{token}', [NewsletterUnsubscribeController::class, 'store'])->where('token', '[A-Za-z0-9]{1,64}')
    ->withoutMiddleware(ValidateCsrfToken::class)->name('newsletter.unsubscribe.store');
// Pixel d'ouverture et clic suivi (liens signés HMAC des campagnes P14-03) : sans session ni cookie.
Route::withoutMiddleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class])
    ->group(function (): void {
        Route::get('/newsletter/ouverture/{recipient}/{signature}', [NewsletterTrackingController::class, 'open'])
            ->where(['recipient' => '[0-9]{1,18}', 'signature' => '[A-Za-z0-9]{1,64}'])->name('newsletter.open');
        Route::get('/newsletter/clic/{recipient}/{signature}', [NewsletterTrackingController::class, 'click'])
            ->where(['recipient' => '[0-9]{1,18}', 'signature' => '[A-Za-z0-9]{1,64}'])->name('newsletter.click');
    });

// --- Alertes, SOS, signalement (P5) ----------------------------------------
// Signalement (P5-04, Q11 a, Q18 a) : POST classique + @csrf (sans JavaScript), même charge utile et même cas d'usage que
// POST /api/v1/signalements ; 10/h par IP (`report`). Suivi par code en POST (code hors URL), 20/h par IP (`tracking`).
// Noms en `signaler.*` : servis pendant la maintenance (EnsureNotInMaintenance, RG-01).
Route::get('/signaler', [ReportPageController::class, 'create'])->name('signaler');
Route::post('/signaler', [ReportPageController::class, 'store'])->middleware('throttle:report')->name('signaler.store');
Route::get('/signaler/confirmation', [ReportPageController::class, 'confirmation'])->name('signaler.confirmation');
Route::get('/suivi', [ReportTrackingController::class, 'show'])->name('signaler.suivi');
Route::post('/suivi', [ReportTrackingController::class, 'lookup'])->middleware('throttle:tracking')->name('signaler.suivi.show');
// P5-05 : page sobre (tu), envoi par l'API (Idempotency-Key) ; `?alerte={ulid}` : SOS du compte connecté (bouton flottant P5-08).
Route::get('/sos', SosPageController::class)->name('sos');
// Accusé de réception par lien signé (P5-02) : contact d'annuaire sans compte ; GET sans effet, POST + CSRF.
// Signature vérifiée par AcknowledgeAlertRequest (403 en français), limiteur dédié `acknowledge` (60/h par IP + alerte
// + fiche, jamais partagé avec le suivi par code : CGNAT mobile), servi en maintenance.
Route::middleware('throttle:acknowledge')->group(function (): void {
    Route::get('/alertes/{alert:ulid}/accuser', [AlertAcknowledgementController::class, 'show'])->name('alertes.accuser');
    Route::post('/alertes/{alert:ulid}/accuser', [AlertAcknowledgementController::class, 'store'])->name('alertes.accuser.store');
});

// --- Authentification (P2-02 à P2-05, P4-09 — lot ecrans-web) ----------------------
// Limiteurs nommés de AppServiceProvider : 'login' (identifiant + IP), 'otp' (adresse e-mail).
Route::middleware('guest')->group(function (): void {
    Route::get('/connexion', [LoginController::class, 'create'])->name('connexion');
    Route::post('/connexion', [LoginController::class, 'store'])->middleware('throttle:login')->name('connexion.store');
    Route::get('/inscription', [RegisterController::class, 'create'])->name('inscription');
    Route::post('/inscription', [RegisterController::class, 'store'])->middleware('throttle:login')->name('inscription.store');
    Route::get('/mot-de-passe-oublie', [ForgotPasswordController::class, 'create'])->name('mot-de-passe-oublie');
    Route::post('/mot-de-passe-oublie', [ForgotPasswordController::class, 'store'])->middleware('throttle:otp')->name('mot-de-passe-oublie.store');
    Route::get('/nouveau-mot-de-passe', [ResetPasswordController::class, 'create'])->name('reinitialisation');
    Route::post('/nouveau-mot-de-passe', [ResetPasswordController::class, 'store'])->middleware('throttle:login')->name('reinitialisation.store');
});
// Code de vérification : usage login (connexion en attente), signup (connecté·e, e-mail non confirmé) ou reset.
// OtpService borne déjà à 5 essais par code et 5 codes par heure et par adresse ; le limiteur 20/min par IP (ou par
// compte) n'est qu'une défense en profondeur, assez large pour un réseau partagé (le limiteur 'login', 5/min, l'était trop).
Route::get('/code-de-verification', [OtpController::class, 'show'])->name('otp');
Route::post('/code-de-verification', [OtpController::class, 'verify'])->middleware('throttle:20,1')->name('otp.verify');
Route::post('/code-de-verification/renvoyer', [OtpController::class, 'resend'])->middleware('throttle:otp')->name('otp.resend');
// Phases 2 à 4 de l'inscription (facultatives, « Passer ») : le compte existe déjà, la session est ouverte (Q12 c).
Route::middleware('auth')->prefix('inscription')->as('inscription.')->group(function (): void {
    Route::get('/profil', [RegistrationProfileController::class, 'show'])->name('profil');
    Route::post('/profil', [RegistrationProfileController::class, 'store'])->name('profil.store');
    Route::get('/securite', [RegistrationSecurityController::class, 'show'])->name('securite');
    Route::post('/securite', [RegistrationSecurityController::class, 'store'])->name('securite.store');
    Route::get('/consentements', [RegistrationConsentsController::class, 'show'])->name('consentements');
    Route::post('/consentements', [RegistrationConsentsController::class, 'store'])->name('consentements.store');
    Route::post('/{etape}/passer', [RegisterController::class, 'skip'])->whereIn('etape', ['profile', 'security', 'consents'])->name('passer');
});
Route::post('/deconnexion', LogoutController::class)->middleware('auth')->name('deconnexion');
// Lot api-me (P6-06) : export RGPD, lien signé de l'e-mail `export-donnees` ; connexion au même compte exigée.
// Signature vérifiée par le contrôleur (lien expiré ou altéré : page dédiée « redemande un export »).
Route::get('/mes-donnees/export/{token}', PersonalDataExportController::class)
    ->middleware(['auth'])->where('token', '[a-z0-9]{40}')->name('mes-donnees.export');

// P7-02, Q31 b : lien de partage d'une publication = page d'atterrissage publique **neutre** (titre générique, invitation
// à se connecter, aucun contenu, noindex) ; aucune lecture en base, même réponse que la publication existe ou non.
// Connecté·e : redirection vers le détail /fil/{ulid} (qui applique la visibilité).
Route::get('/p/{ulid}', SharedPostController::class)->where('ulid', '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}')->name('p.show');

// P8-04 (lot evenements), Q35 a : une adresse, deux publics. Connecté·e : pages Vue `Events/Index`, `Events/Show`
// (InertiaForMembers → HandleInertiaRequests, noindex) ; visiteur·se : page Blade publique indexée des seuls événements
// publics (sans adresse), un événement réservé aux membres renvoyant vers la connexion. Par ULID (ADR-27).
Route::middleware(InertiaForMembers::class)->group(function (): void {
    Route::get('/evenements', [EventsPageController::class, 'index'])->name('evenements.index');
    Route::get('/evenements/{event}', [EventsPageController::class, 'show'])->where('event', '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}')->name('evenements.show');
});

// --- Espace membre et pair éducateur·rice : Vue + Inertia (P6 à P9) ---------
// HandleInertiaRequests (P6-02) : props partagées et X-Robots-Tag noindex. Toutes les pages sont Inertia, dans le gabarit
// membre Vue (P6-03, resources/js/layouts/MemberLayout.vue) : les écrans pas encore livrés sont `Member/Placeholder`
// ($memberPlaceholder) ; le gabarit les relie par <Link> (Inertia), les pages publiques Blade par un <a> simple.
Route::middleware(['auth', HandleInertiaRequests::class])->group(function () use ($memberPlaceholder): void {
    // P6-04 : catalogue des composants Xm* (local et recette, 404 ailleurs), page de démonstration de la coquille Inertia.
    Route::get('/design-system/vue', DesignSystemVueController::class)->name('design-system.vue');
    // P7-03 à P7-06 (lot fil-ecrans) : fil, publications enregistrées, détail par ULID (ADR-27 ; invisible → 404).
    Route::get('/fil', [FeedPageController::class, 'index'])->name('fil');
    Route::get('/fil/enregistres', [FeedPageController::class, 'saved'])->name('fil.saved');
    Route::get('/fil/{post:ulid}', [FeedPageController::class, 'show'])->where('post', '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}')->name('fil.show');
    // P8-01 : messagerie (liste + conversation, sondage court, boîte d'envoi hors connexion).
    Route::get('/messages', MessagesPageController::class)->name('messages');
    // P8-02, P8-03 (lot groupes-pages) : groupes et pages d'organisations par ULID (ADR-27 ; invisible → 404, groupe privé
    // d'une non-membre → fiche réduite, page non approuvée → 404 sauf propriétaire et modération).
    Route::get('/groupes', [GroupsPageController::class, 'index'])->name('groupes.index');
    Route::get('/groupes/{group:ulid}', [GroupsPageController::class, 'show'])->where('group', '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}')->name('groupes.show');
    Route::get('/pages', [PagesPageController::class, 'index'])->name('pages.index');
    Route::get('/pages/creer', [PagesPageController::class, 'create'])->name('pages.create');
    Route::get('/pages/{page:ulid}', [PagesPageController::class, 'show'])->where('page', '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}')->name('pages.show');
    // P8-05 (lot forums) : forums et sujets par ULID (ADR-27 ; invisible → 404, vue comptée une fois par session).
    Route::get('/forums', [ForumsPageController::class, 'index'])->name('forums.index');
    Route::get('/forums/{topic:ulid}', [ForumsPageController::class, 'show'])->where('topic', '[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}')->name('forums.show');
    $memberPlaceholder('/qui-suivre', 'qui-suivre', 'who_to_follow', 'who-to-follow.php');
    $memberPlaceholder('/profil', 'profil', 'profile', 'my-profile.php');
    $memberPlaceholder('/parametres', 'parametres', 'settings', 'settings.php');
    $memberPlaceholder('/notifications', 'notifications', 'notifications', 'notifications.php');
    $memberPlaceholder('/assistant', 'assistant', 'assistant', 'assistant.php');
    $memberPlaceholder('/annuaire', 'annuaire', 'directory', 'annuaire.php');
    $memberPlaceholder('/medias', 'medias', 'medias', 'albums.php');
    $memberPlaceholder('/ccc', 'ccc', 'ccc', 'ccc.php');
    $memberPlaceholder('/espace-pair', 'espace-pair', 'peer_space', 'espace-pair.php')->middleware('can:peer_space.access');
    $memberPlaceholder('/collecte', 'collecte', 'collecte', 'collecte.php')->middleware('can:peer_space.access');
});

// --- Catalogue vivant du design system (P0-10) : local et recette seulement, 404 ailleurs ----
Route::get('/design-system', DesignSystemController::class)->name('design-system');

// --- Fichiers privés (P3-04) : lien temporaire signé produit par MediaUploader::signedUrl() ----
Route::get('/media/{disk}/{path}', SignedMediaController::class)->where('path', '.*')->name('media.signed');

// --- Redirections des anciennes adresses du prototype ------------------------
Route::permanentRedirect('/index.php', '/');
Route::permanentRedirect('/landing.php', '/');
Route::permanentRedirect('/fil.php', '/fil');
Route::permanentRedirect('/sign-in.php', '/connexion');
Route::permanentRedirect('/sign-up.php', '/inscription');
Route::permanentRedirect('/signalement.php', '/signaler');
Route::permanentRedirect('/sos.php', '/sos');
Route::permanentRedirect('/urgences.php', '/urgences');
// P4-10 : blog.php filtrait par libellé (?cat=) et blog-details.php adressait des articles fictifs par id : liste.
Route::permanentRedirect('/blog.php', '/actualites');
Route::permanentRedirect('/blog-details.php', '/actualites');
Route::permanentRedirect('/thematiques.php', '/thematiques');
// P4-04 : pages Apprendre.
Route::permanentRedirect('/violences.php', '/violences');
Route::permanentRedirect('/lois-benin.php', '/lois');
Route::permanentRedirect('/do-glegbe.php', '/do-glegbe');
// P4-05 : help.php garde ?cat= et ?q= ; help-details.php?id= (rang dans x_faq()) → article du slug semé de même rang.
Route::get('/help.php', [LegacyAideRedirectController::class, 'index'])->name('legacy.help');
Route::get('/help-details.php', [LegacyAideRedirectController::class, 'show'])->name('legacy.help_details');
// P4-08 : page légale.
Route::permanentRedirect('/privacy-and-terms.php', '/confidentialite');
// Le prototype adressait les fiches par id ; la couverture semée le garde (covers/th-<id>.jpg, ContentSeeder) : P4-02.
Route::get('/thematique.php', LegacyThematiqueRedirectController::class)->name('legacy.thematique');
// P4-03 : ressources.php garde ?q= et ?cat= ; ressource.php?id= redirigeait vers thematique.php?id= (même résolution).
Route::get('/ressources.php', [LegacyRessourceRedirectController::class, 'index'])->name('legacy.ressources');
Route::get('/ressource.php', [LegacyRessourceRedirectController::class, 'show'])->name('legacy.ressource');
// P4-07 : newsletter.php?a=confirm|unsub&t= → pages à jeton ; suivi du prototype (autre clé) et le reste → /newsletter.
Route::get('/newsletter.php', LegacyNewsletterRedirectController::class)->name('legacy.newsletter');

// --- Référencement (P4-11) : robots.txt et sitemap.xml natifs, sans fichier statique ----
// Sans session ni cookies : une visite de robot n'ouvre pas de session et la réponse `Cache-Control: public` ne porte
// aucun Set-Cookie qu'un cache partagé pourrait conserver.
Route::withoutMiddleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class])
    ->group(function (): void {
        Route::get('/robots.txt', RobotsController::class)->name('robots');
        Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
    });

// --- Service worker (P5-10, ADR-26) : à la racine pour la portée « / », sans session ni cookie, servi en maintenance ----
Route::withoutMiddleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class, EnsureNotInMaintenance::class])
    ->get('/sw.js', ServiceWorkerController::class)->name('service-worker');

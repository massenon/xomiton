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
use App\Http\Controllers\Web\DesignSystemController;
use App\Http\Controllers\Web\PlaceholderController;
use App\Http\Controllers\Web\SignedMediaController;
use Illuminate\Support\Facades\Route;

/*
 * Routes web : pages publiques (Blade) et espace membre / pair (Vue + Inertia, D9).
 * Noms « module.action », chemins en français (MODULE_SPECIFICATIONS.md §A à §C).
 * Les routes encore servies par PlaceholderController sont remplacées phase par phase (docs/IMPLEMENTATION_PLAN.md).
 */

$placeholder = static function (string $uri, string $name, string $title, string $prototype, string $layout = 'public'): void {
    Route::get($uri, PlaceholderController::class)
        ->name($name)
        ->defaults('title', $title)
        ->defaults('prototype', $prototype)
        ->defaults('layout', $layout);
};

// --- Site public (P4) -------------------------------------------------------
$placeholder('/', 'home', 'Accueil', 'index.php');
$placeholder('/thematiques', 'thematiques.index', 'Thématiques', 'thematiques.php');
$placeholder('/thematiques/{slug}', 'thematiques.show', 'Thématique', 'thematique.php');
$placeholder('/ressources', 'ressources.index', 'Ressources', 'ressources.php');
$placeholder('/ressources/{slug}', 'ressources.show', 'Ressource', 'ressource.php');
$placeholder('/violences', 'violences', 'Comprendre les violences', 'violences.php');
$placeholder('/lois', 'lois', 'Lois et codes du Bénin', 'lois-benin.php');
$placeholder('/do-glegbe', 'do-glegbe', 'Do Glegbe', 'do-glegbe.php');
$placeholder('/aide', 'aide.index', 'Centre d’aide', 'help.php');
$placeholder('/aide/{slug}', 'aide.show', 'Aide', 'help-details.php');
$placeholder('/actualites', 'actualites.index', 'Actualités', 'blog.php');
$placeholder('/actualites/{slug}', 'actualites.show', 'Actualité', 'blog-details.php');
$placeholder('/urgences', 'urgences', 'Contacts d’urgence', 'urgences.php', 'plain');
$placeholder('/confidentialite', 'confidentialite', 'Confidentialité et conditions', 'privacy-and-terms.php');
$placeholder('/newsletter', 'newsletter.show', 'Newsletter', 'newsletter.php');
Route::post('/newsletter', PlaceholderController::class)->name('newsletter.subscribe')
    ->defaults('title', 'Newsletter')->defaults('prototype', 'newsletter.php');

// --- Alertes, SOS, signalement (P5) ----------------------------------------
$placeholder('/signaler', 'signaler', 'Signaler', 'signalement.php', 'plain');
$placeholder('/sos', 'sos', 'SOS', 'sos.php', 'plain');

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

// --- Espace membre et pair éducateur·rice : Vue + Inertia (P6 à P9) ---------
Route::middleware('auth')->group(function () use ($placeholder): void {
    $placeholder('/fil', 'fil', 'Fil d’actualité', 'fil.php');
    $placeholder('/fil/{post}', 'fil.show', 'Publication', 'post-details.php');
    $placeholder('/messages', 'messages', 'Messages', 'messaging.php');
    $placeholder('/groupes', 'groupes.index', 'Groupes', 'groups.php');
    $placeholder('/groupes/{group}', 'groupes.show', 'Groupe', 'group-details.php');
    $placeholder('/pages', 'pages.index', 'Pages', 'pages.php');
    $placeholder('/pages/creer', 'pages.create', 'Créer une page', 'create-page.php');
    $placeholder('/evenements', 'evenements.index', 'Événements', 'events.php');
    $placeholder('/evenements/{event}', 'evenements.show', 'Événement', 'event-details.php');
    $placeholder('/forums', 'forums.index', 'Forums', 'forums.php');
    $placeholder('/forums/{topic}', 'forums.show', 'Sujet', 'forum-topic.php');
    $placeholder('/qui-suivre', 'qui-suivre', 'Qui suivre', 'who-to-follow.php');
    $placeholder('/profil', 'profil', 'Mon profil', 'my-profile.php');
    $placeholder('/parametres', 'parametres', 'Paramètres', 'settings.php');
    $placeholder('/notifications', 'notifications', 'Notifications', 'notifications.php');
    $placeholder('/assistant', 'assistant', 'Assistant', 'assistant.php');
    $placeholder('/annuaire', 'annuaire', 'Annuaire', 'annuaire.php');
    $placeholder('/medias', 'medias', 'Médiathèque', 'albums.php');
    $placeholder('/ccc', 'ccc', 'Outils CCC', 'ccc.php');
    $placeholder('/espace-pair', 'espace-pair', 'Espace pair', 'espace-pair.php');
    $placeholder('/collecte', 'collecte', 'Collecte de données', 'collecte.php');
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

// --- Référencement (P4-11) : robots.txt et sitemap.xml natifs, sans fichier statique ----
Route::get('/robots.txt', App\Http\Controllers\Web\RobotsController::class)->name('robots');
Route::get('/sitemap.xml', App\Http\Controllers\Web\SitemapController::class)->name('sitemap');

<?php

declare(strict_types=1);

use App\Http\Controllers\Web\DesignSystemController;
use App\Http\Controllers\Web\PlaceholderController;
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

// --- Authentification (P2, P4-09) ------------------------------------------
Route::middleware('guest')->group(function () use ($placeholder): void {
    $placeholder('/connexion', 'connexion', 'Connexion', 'sign-in.php', 'auth');
    $placeholder('/inscription', 'inscription', 'Créer un compte', 'sign-up.php', 'auth');
    $placeholder('/mot-de-passe-oublie', 'mot-de-passe-oublie', 'Mot de passe oublié', 'forgot-password.php', 'auth');
    $placeholder('/code-de-verification', 'otp', 'Code de vérification', 'sign-in.php', 'auth');
});
Route::post('/deconnexion', PlaceholderController::class)->middleware('auth')->name('deconnexion')
    ->defaults('title', 'Déconnexion')->defaults('prototype', 'logout.php');

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

// --- Redirections des anciennes adresses du prototype ------------------------
Route::permanentRedirect('/index.php', '/');
Route::permanentRedirect('/landing.php', '/');
Route::permanentRedirect('/fil.php', '/fil');
Route::permanentRedirect('/sign-in.php', '/connexion');
Route::permanentRedirect('/sign-up.php', '/inscription');
Route::permanentRedirect('/signalement.php', '/signaler');
Route::permanentRedirect('/sos.php', '/sos');
Route::permanentRedirect('/urgences.php', '/urgences');

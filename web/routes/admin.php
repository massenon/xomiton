<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CsvExportController;
use App\Http\Controllers\Admin\MaintenancePreviewController;
use App\Http\Controllers\Admin\ReportDownloadController;
use App\Http\Controllers\Web\PlaceholderController;
use App\Http\Middleware\ForgetSearchTerms;
use App\Livewire\Admin\Alerts\Index as AlertsIndex;
use App\Livewire\Admin\Dashboard\Index as DashboardIndex;
use App\Livewire\Admin\Indicators\Index as IndicatorsIndex;
use App\Livewire\Admin\Journal\Index as JournalIndex;
use App\Livewire\Admin\Maintenance\Index as MaintenanceIndex;
use App\Livewire\Admin\Reports\Index as ReportsIndex;
use App\Livewire\Admin\Search\Index as SearchIndex;
use Illuminate\Support\Facades\Route;

/*
 * Administration (Livewire 3) : préfixe /admin, middleware auth + can:admin.access (DEVELOPMENT_STANDARDS.md §2).
 * Un écran = un composant Livewire pleine page (app/Livewire/Admin/<Module>/Index) avec le gabarit components.layouts.admin.
 * Les écrans encore servis par PlaceholderController sont remplacés en P10 à P14.
 */

$screens = [
    // Pilotage
    // Gestion des données
    ['/registres', 'registres', 'Registres numériques', 'admin/registres.php'],
    ['/fiches', 'fiches', 'Fiches VBG / VDH', 'admin/fiches.php'],
    ['/suivi', 'suivi', 'Suivi des victimes', 'admin/suivi.php'],
    ['/annuaire', 'annuaire', 'Annuaire dynamique', 'admin/annuaire.php'],
    ['/validation', 'validation', 'Règles de validation', 'admin/validation.php'],
    // Plateforme
    ['/utilisateurs', 'utilisateurs', 'Utilisateurs & rôles', 'admin/utilisateurs.php'],
    ['/inscriptions', 'inscriptions', 'Inscriptions', 'admin/inscriptions.php'],
    ['/contenu', 'contenu', 'Contenu : thématiques & ressources', 'admin/contenu.php'],
    ['/newsletter', 'newsletter', 'Newsletter', 'admin/newsletter.php'],
    ['/ccc', 'ccc', 'Outils CCC', 'admin/ccc.php'],
    ['/reseau', 'reseau', 'Réseau social', 'admin/reseau.php'],
    ['/moderation', 'moderation', 'Modération', 'admin/moderation.php'],
    ['/assistant', 'assistant', 'Assistant LLM', 'admin/assistant.php'],
    ['/passerelles', 'passerelles', 'Passerelles réseaux', 'admin/passerelles.php'],
    // Système
    ['/synchronisation', 'synchronisation', 'Hors connexion & synchro', 'admin/synchronisation.php'],
    ['/mises-a-jour', 'mises-a-jour', 'Mises à jour & optimisation', 'admin/mises-a-jour.php'],
    ['/emails', 'emails', 'Templates e-mails', 'admin/emails.php'],
    ['/parametres', 'parametres', 'Paramètres', 'admin/parametres.php'],
];

foreach ($screens as [$uri, $name, $title, $prototype]) {
    Route::get($uri, PlaceholderController::class)
        ->name($name)
        ->defaults('title', $title)
        ->defaults('prototype', $prototype)
        ->defaults('layout', 'admin');
}

// --- Pilotage (P10-04, P10-05) : tableau de bord (chaque bloc selon la permission de son écran d'origine) et alertes &
// signalements (`alerts.view` ; le composant autorise aussi `viewAny` et applique AlertScope) ------------------
Route::get('/', DashboardIndex::class)->name('dashboard');
Route::get('/alertes', AlertsIndex::class)->middleware('can:alerts.view')->name('alertes');

// --- Mode maintenance (P13-06) : admin et superadmin (`platform.maintenance`), jamais bloqué par la maintenance -------
Route::middleware('can:platform.maintenance')->group(function (): void {
    Route::get('/maintenance', MaintenanceIndex::class)->name('maintenance');
    Route::get('/maintenance/apercu', MaintenancePreviewController::class)->name('maintenance.preview');
});

// --- Journal d'audit (P13-05, écran témoin de WithDataTable P10-02) : lecture et export `audit_logs.view`, purge
// `audit_logs.purge` (superadmin) vérifiée par le composant ----------------------------------------------------------
Route::get('/journal', JournalIndex::class)->middleware('can:audit_logs.view')->name('journal');

// --- Exports CSV des écrans WithDataTable (P10-02, ADR-33) : lien signé et chiffré de courte durée, lié au compte ;
// l'écran rejoue ses contrôles (policy, `cases.export` pour un export complet), le plafond et la ligne d'audit ----------
Route::get('/export', CsvExportController::class)->middleware('signed')->name('export');

// --- Indicateurs, rapports et recherche globale (P10-06 à P10-08) --------------------------------------------------------
// Indicateurs : lecture `indicators.view` ; saisie et suppression d'un indicateur personnalisé `indicators.manage` (composant).
Route::get('/indicateurs', IndicatorsIndex::class)->middleware('can:indicators.view')->name('indicateurs');

// Rapports : `reports.generate` ; le téléchargement passe par un lien signé de 5 minutes lié au compte (contrôleur).
Route::middleware('can:reports.generate')->group(function (): void {
    Route::get('/rapports', ReportsIndex::class)->name('rapports');
    Route::get('/rapports/{report}/telecharger', ReportDownloadController::class)->middleware('signed')->name('rapports.telecharger');
});

// Recherche globale : tout compte d'administration (`admin.access`, déjà exigé par le groupe) ; chaque groupe de résultats
// ne s'ouvre qu'avec la permission de son écran. ForgetSearchTerms : le terme `?q=` n'est jamais conservé en session.
Route::get('/recherche', SearchIndex::class)->middleware(ForgetSearchTerms::class)->name('recherche');

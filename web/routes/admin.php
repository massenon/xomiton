<?php

declare(strict_types=1);

use App\Application\Admin\AdminNavigation;
use App\Http\Controllers\Admin\BackupDownloadController;
use App\Http\Controllers\Admin\CaseFormPdfController;
use App\Http\Controllers\Admin\CsvExportController;
use App\Http\Controllers\Admin\MaintenancePreviewController;
use App\Http\Controllers\Admin\ReportDownloadController;
use App\Http\Controllers\Web\PlaceholderController;
use App\Http\Middleware\ForgetSearchTerms;
use App\Livewire\Admin\Alerts\Index as AlertsIndex;
use App\Livewire\Admin\Assistant\Index as AssistantIndex;
use App\Livewire\Admin\Backups\Index as BackupsIndex;
use App\Livewire\Admin\Ccc\Index as CccIndex;
use App\Livewire\Admin\Content\Index as ContentIndex;
use App\Livewire\Admin\Dashboard\Index as DashboardIndex;
use App\Livewire\Admin\Directory\Index as DirectoryIndex;
use App\Livewire\Admin\Emails\Index as EmailsIndex;
use App\Livewire\Admin\Followups\Index as FollowupsIndex;
use App\Livewire\Admin\Forms\Index as FormsIndex;
use App\Livewire\Admin\Gateways\Index as GatewaysIndex;
use App\Livewire\Admin\Indicators\Index as IndicatorsIndex;
use App\Livewire\Admin\Journal\Index as JournalIndex;
use App\Livewire\Admin\Maintenance\Index as MaintenanceIndex;
use App\Livewire\Admin\Moderation\Index as ModerationIndex;
use App\Livewire\Admin\Network\Index as NetworkIndex;
use App\Livewire\Admin\Newsletter\Index as NewsletterIndex;
use App\Livewire\Admin\Registers\Index as RegistersIndex;
use App\Livewire\Admin\Registrations\Index as RegistrationsIndex;
use App\Livewire\Admin\Reports\Index as ReportsIndex;
use App\Livewire\Admin\Search\Index as SearchIndex;
use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Livewire\Admin\Sync\Index as SyncIndex;
use App\Livewire\Admin\Updates\Index as UpdatesIndex;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Admin\Validation\Index as ValidationIndex;
use Illuminate\Support\Facades\Route;

/*
 * Administration (Livewire 3) : préfixe /admin, middleware auth + can:admin.access (DEVELOPMENT_STANDARDS.md §2).
 * Un écran = un composant Livewire pleine page (app/Livewire/Admin/<Module>/Index) avec le gabarit components.layouts.admin.
 * Les écrans encore servis par PlaceholderController sont remplacés en P10 à P14.
 */

$screens = [
    // Pilotage
    // Gestion des données
    // Plateforme
    // Système
];

// P12-09 : un écran provisoire exige déjà la permission de son entrée de menu (AdminNavigation::GROUPS) ; le modérateur
// (`admin.access` seul) ne voit donc aucun écran qui n'est pas le sien, même avant la livraison de sa phase.
$screenPermissions = [];

foreach (AdminNavigation::GROUPS as $entries) {
    foreach ($entries as $entry) {
        $screenPermissions[$entry[0]] = $entry[3];
    }
}

foreach ($screens as [$uri, $name, $title, $prototype]) {
    $screen = Route::get($uri, PlaceholderController::class)
        ->name($name)
        ->defaults('title', $title)
        ->defaults('prototype', $prototype)
        ->defaults('layout', 'admin');

    if (($screenPermissions[$name] ?? null) !== null) {
        $screen->middleware('can:'.$screenPermissions[$name]);
    }
}

// --- Pilotage (P10-04, P10-05) : tableau de bord (chaque bloc selon la permission de son écran d'origine) et alertes &
// signalements (`alerts.view` ; le composant autorise aussi `viewAny` et applique AlertScope) ------------------
Route::get('/', DashboardIndex::class)->name('dashboard');
Route::get('/alertes', AlertsIndex::class)->middleware('can:alerts.view')->name('alertes');

// --- Gestion des données (P11) : un écran = une permission (menu AdminNavigation) ; chaque composant autorise aussi la
// policy du modèle et applique le scope du compte (CaseRecordScope) ----------------------------------------------------
Route::get('/registres', RegistersIndex::class)->middleware('can:cases.view_all')->name('registres');
Route::get('/suivi', FollowupsIndex::class)->middleware('can:case_followups.manage')->name('suivi');
// Fiches VBG / VDH : écran `case_forms.manage` ; PDF A4 côté serveur (ADR-23), policy `print` rejouée par le contrôleur.
// Annuaire dynamique : `directory.manage` (CRUD, import / export CSV, aperçu du routage RG-04).
Route::get('/annuaire', DirectoryIndex::class)->middleware('can:directory.manage')->name('annuaire');
// Règles de validation : `validation_rules.manage` (catalogue, bac à sable, plausibilité, doublons, rapport de qualité).
Route::get('/validation', ValidationIndex::class)->middleware('can:validation_rules.manage')->name('validation');
Route::get('/fiches', FormsIndex::class)->middleware('can:case_forms.manage')->name('fiches');
Route::get('/fiches/{form}/pdf', CaseFormPdfController::class)->middleware('can:case_forms.manage')->whereNumber('form')->name('fiches.pdf');

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

// --- Administration de la plateforme, partie 1 (P12-01, P12-02, P12-06, P12-09) : un écran = une permission (menu
// AdminNavigation). Utilisateurs & rôles : lecture `users.view` (modérateur compris : liste sans e-mail, action
// « Suspendre » seule) ; chaque action rejoue sa policy dans le composant. Inscriptions : `registrations.manage`.
// Modération : `moderation.manage` (admin, modérateur·rice, superadmin).
Route::get('/utilisateurs', UsersIndex::class)->middleware('can:users.view')->name('utilisateurs');
Route::get('/inscriptions', RegistrationsIndex::class)->middleware('can:registrations.manage')->name('inscriptions');
Route::get('/moderation', ModerationIndex::class)->middleware('can:moderation.manage')->name('moderation');

// --- Administration de la plateforme, partie 2 (P12-03, P12-04, P12-05, P12-07, P12-08) : un écran = une permission (menu
// AdminNavigation) ; chaque onglet et chaque action rejouent la permission et la policy de leur contenu dans le composant.
// Contenu : `thematiques.manage` (onglets Ressources `resources.manage`, FAQ `faqs.manage`).
Route::get('/contenu', ContentIndex::class)->middleware('can:thematiques.manage')->name('contenu');
// Outils CCC : `ccc_tools.manage` (onglet Lois : `laws.manage` en plus).
Route::get('/ccc', CccIndex::class)->middleware('can:ccc_tools.manage')->name('ccc');
// Réseau social : `posts.moderate` (modérateur·rice, admin, superadmin) ; approuver et mettre en avant : `moderation.manage` (PagePolicy).
Route::get('/reseau', NetworkIndex::class)->middleware('can:posts.moderate')->name('reseau');
// Assistant LLM : `assistant_kb.manage` (base de connaissances, consigne, garde-fous, conversations signalées).
Route::get('/assistant', AssistantIndex::class)->middleware('can:assistant_kb.manage')->name('assistant');
// Passerelles : `gateways.connect` (comptes) ; file, modèles et salons rejouent `gateways.publish` (OutboundPostPolicy).
Route::get('/passerelles', GatewaysIndex::class)->middleware('can:gateways.connect')->name('passerelles');

// --- Administration système (P13-01 à P13-05) : un écran = une permission (menu AdminNavigation) ; chaque action rejoue la
// permission et la policy de son contenu dans le composant. Sauvegardes : lecture / création / téléchargement
// `backups.create` / `backups.download` (admin), restauration et suppression `backups.restore` / `backups.delete`
// (superadmin) ; le téléchargement passe par un contrôleur qui rejoue la policy et journalise.
Route::get('/sauvegardes', BackupsIndex::class)->middleware('can:backups.create')->name('sauvegardes');
Route::get('/sauvegardes/{backup}/telecharger', BackupDownloadController::class)->middleware('can:backups.download')->whereNumber('backup')->name('sauvegardes.telecharger');
// Hors connexion & synchro : `sync.manage` (admin, superadmin) ; résolution des conflits et révocation rejouent leurs policies.
Route::get('/synchronisation', SyncIndex::class)->middleware('can:sync.manage')->name('synchronisation');
// Mises à jour & optimisation : lecture `updates.view` (admin, superadmin) ; publier, planifier, optimiser `updates.manage` (superadmin).
Route::get('/mises-a-jour', UpdatesIndex::class)->middleware('can:updates.view')->name('mises-a-jour');
// Paramètres : lecture `settings.view` (admin, superadmin) ; écriture `settings.manage` et SMTP (superadmin), rejouées par le composant.
Route::get('/parametres', SettingsIndex::class)->middleware('can:settings.view')->name('parametres');

// --- Communication (P14-01, P14-02) : un écran = une permission (menu AdminNavigation) ; chaque onglet et chaque action rejouent
// la permission et la policy de leur contenu dans le composant. Newsletter : `newsletter.manage_campaigns` (abonné·es :
// `newsletter.manage_subscribers`, programmer ou lancer : `newsletter.send`). Templates e-mails : `email_templates.manage`
// (suppression d'un template système : `email_templates.delete_system`).
Route::get('/newsletter', NewsletterIndex::class)->middleware('can:newsletter.manage_campaigns')->name('newsletter');
Route::get('/emails', EmailsIndex::class)->middleware('can:email_templates.manage')->name('emails');

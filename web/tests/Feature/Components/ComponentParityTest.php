<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

/*
 * Parité Blade / Vue des composants (P6-04, D9 : « les composants Xm* reproduisent exactement le HTML des x-* ») :
 * test de snapshot HTML commun. Chaque cas rend ici un composant x-* et le compare à sa référence
 * resources/js/components/__parity__/<cas>.html ; resources/js/components/parity.test.ts rend le Xm* équivalent et le
 * compare à la même référence (arbre normalisé : classes et attributs triés, espaces repliés, crochets Alpine et
 * data-xm-* ignorés). Un changement de HTML d'un x-* casse donc ce test tant que la référence n'est pas régénérée
 * (XM_PARITY_WRITE=1 vendor/bin/pest --filter=ComponentParity), puis le test Vitest tant que le Xm* n'est pas aligné.
 */

/** HTML comparable : adresses absolues ramenées à la racine (APP_URL diffère selon l'environnement), espaces repliés. */
function parityHtml(string $html): string
{
    $origin = rtrim(url('/'), '/');
    $html = str_replace(['="'.$origin.'"', $origin.'/'], ['="/"', '/'], $html);
    $html = (string) preg_replace('/\s+/u', ' ', $html);

    return trim((string) preg_replace('/>\s+</u', '><', $html));
}

/** @return array<string, string> cas => appel Blade (mêmes noms que les cas de parity.test.ts) */
function parityCases(): array
{
    return [
        'button-primary' => '<x-button icon="send">Envoyer</x-button>',
        'button-soft' => '<x-button variant="primary-soft" icon="plus-lg">Doux</x-button>',
        'button-icon' => '<x-button variant="icon" icon="gear" label="Réglages" />',
        'button-link-disabled' => '<x-button href="/fil" variant="outline" size="sm" disabled>Fil</x-button>',
        'button-loading' => '<x-button type="submit" loading>Envoyer</x-button>',
        'badge-soft' => '<x-badge color="success" soft icon="check-circle" pill>Actif</x-badge>',
        'badge-violet' => '<x-badge color="violet">Pair</x-badge>',
        'badge-status' => '<x-badge scope="alert_type" status="denonciation" />',
        'badge-unknown-status' => '<x-badge scope="case" status="archived" />',
        'status' => '<x-status status="assigned" />',
        'urgency' => '<x-urgency level="critical" />',
        'alert-dismissible' => '<x-alert type="warning" title="Attention" dismissible>Vérifie ton adresse.</x-alert>',
        'alert-no-icon' => '<x-alert type="info" :icon="false">Information.</x-alert>',
        'stat-trend' => '<x-stat label="Publications" value="128" icon="chat-dots" trend="+12 %" direction="up" sub="Ce mois-ci" />',
        'stat-link' => '<x-stat label="Alertes" value="3" variant="danger" href="/espace-pair" />',
        'card-link' => '<x-card title="Comprendre le consentement" badge="Fiche" meta="5 min de lecture" href="/thematiques/consentement">Une fiche courte.</x-card>',
        'card-header' => '<x-card header title="Mes groupes"><x-slot:actions><button type="button" class="btn btn-sm btn-light">Exporter</button></x-slot:actions>'."\n".'Corps</x-card>',
        'card-image' => '<x-card variant="event" title="Atelier" image="/assets/images/xomiton/covers/og-default.jpg" image-alt="Atelier au centre">Samedi.</x-card>',
        'avatar-initials' => '<x-avatar name="Awa Dossou" size="lg" />',
        'avatar-image' => '<x-avatar name="Awa" src="/assets/images/xomiton/avatar1.png" size="sm" rounded="2" />',
        'empty-state' => '<x-empty-state title="Rien pour l’instant" icon="people">Les publications apparaîtront ici.<x-slot:action><a href="/groupes" class="btn btn-primary">Voir les groupes</a></x-slot:action></x-empty-state>',
        'empty-state-compact' => '<x-empty-state title="Aucun résultat" compact />',
        'skeleton-lines' => '<x-skeleton :lines="4" />',
        'skeleton-card' => '<x-skeleton type="card" />',
        'skeleton-avatar' => '<x-skeleton type="avatar" label="Chargement des membres" />',
        'skeleton-kpi' => '<x-skeleton type="kpi" />',
        'skeleton-table' => '<x-skeleton type="table" :rows="2" />',
        'stepper' => '<x-stepper :steps="[\'Identité\', \'Contact\', \'Confirmation\']" :current="2" />',
        'tabs' => '<x-tabs id="dsTabs" :tabs="[\'infos\' => \'Informations\', \'notes\' => \'Notes\']"><x-slot:infos>Premier panneau</x-slot:infos><x-slot:notes>Second panneau</x-slot:notes></x-tabs>',
        'accordion' => '<x-accordion id="faq" :items="[[\'q\' => \'Mes messages sont-ils privés ?\', \'a\' => \'<p>Oui.</p>\'], [\'q\' => \'Puis-je partir ?\', \'a\' => \'<p>À tout moment.</p>\']]" level="h2" />',
        'breadcrumb' => '<x-breadcrumb :items="[[\'label\' => \'Accueil\', \'url\' => \'/\'], [\'label\' => \'Ressources\', \'url\' => \'/ressources\'], [\'label\' => \'Kit\']]" />',
        'error-summary' => '<x-error-summary :messages="[\'pseudo\' => \'Choisis un pseudo.\', \'contacts.0.phone\' => [\'Numéro invalide.\']]" :autofocus="false" />',
        'logo' => '<x-logo :width="120" />',
        'logo-adaptive' => '<x-logo adaptive :link="false" img-class="navbar-brand-item" />',
        'logo-icone' => '<x-logo variant="icone" :width="48" />',
        'img-picture' => '<x-img src="/media/a.jpg" alt="Atelier" width="800" height="450" webp="/media/a.webp 800w" />',
        'img-decorative' => '<x-img src="/media/d.svg" alt="" />',
        'img-priority' => '<x-img src="/media/hero.jpg" alt="Accueil" priority srcset="/media/hero-800.jpg 800w, /media/hero.jpg 1600w" />',
        'modal-delete' => '<x-modal id="mDel" title="Supprimer la publication ?" type="delete" confirm-word="SUPPRIMER">Cette action est définitive.</x-modal>',
        'modal-static' => '<x-modal id="mEdit" title="Modifier" size="lg" action="Enregistrer" static>Corps</x-modal>',
        'field-text' => '<x-field name="pseudo" label="Pseudo" hint="Visible par les membres." error="Trop court." autocomplete="nickname" optional />',
        'field-select' => '<x-field name="zone" label="Département" type="select" :options="[\'1\' => \'Littoral\', \'2\' => \'Atlantique\']" />',
        'field-switch' => '<x-field name="gps" label="Partager ma position" type="switch" />',
        'field-radio' => '<x-field name="age" label="Âge" type="radio-group" :options="[\'a\' => \'15-17 ans\', \'b\' => \'18-24 ans\']" />',
        'field-password' => '<x-field name="password" label="Mot de passe" type="password" autocomplete="current-password" />',
        'field-textarea' => '<x-field name="bio" label="Présentation" type="textarea" maxlength="200" :rows="3" />',
        'toast' => '<x-toast message="Ta publication est en ligne." type="success" />',
        'bell' => '<x-bell variant="member" :items="[[\'id\' => \'n1\', \'text\' => \'Nouvelle réponse\', \'time\' => \'il y a 5 min\', \'href\' => \'/fil\', \'read\' => false]]" />',
        'sos-button' => '<x-sos-button :fixed="false" id="dsSos" />',
        'timeline' => '<x-timeline label="Suivi du dossier" :items="[[\'title\' => \'Entretien d’accueil\', \'date\' => \'12/03/2026\', \'datetime\' => \'2026-03-12\', \'by\' => \'Pair A.\', \'kind\' => \'shared\', \'badge\' => [\'label\' => \'Réalisé\', \'color\' => \'success\'], \'text\' => \'Premier échange.\'], [\'title\' => \'Relance\', \'kind\' => \'internal\', \'next\' => \'20/03/2026\', \'late\' => true], [\'title\' => \'Clôture\', \'date\' => \'25/03/2026\', \'color\' => \'warning\']]" />',
        'timeline-empty' => '<x-timeline :items="[]" />',
    ];
}

dataset('parity', fn (): array => collect(parityCases())->map(fn (string $blade, string $case): array => [$case, $blade])->all());

it('rend chaque x-* comme sa référence de parité Blade / Vue', function (string $case, string $blade): void {
    $path = resource_path("js/components/__parity__/{$case}.html");
    $html = parityHtml(Blade::render($blade));

    if (getenv('XM_PARITY_WRITE') === '1') {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $html."\n");
    }

    expect(File::exists($path))->toBeTrue("Référence absente : {$path} (XM_PARITY_WRITE=1 pour la créer).")
        ->and(parityHtml(File::get($path)))->toBe($html);
})->with('parity');

it('a une référence par cas, sans fichier orphelin', function (): void {
    $files = collect(File::files(resource_path('js/components/__parity__')))
        ->map(fn (SplFileInfo $file): string => $file->getBasename('.html'))
        ->sort()->values()->all();

    expect($files)->toBe(collect(array_keys(parityCases()))->sort()->values()->all());
});

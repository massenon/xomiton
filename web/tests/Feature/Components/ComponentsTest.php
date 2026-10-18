<?php

declare(strict_types=1);

use Illuminate\Testing\TestView;
use Illuminate\View\ViewException;

/*
 * Rendu des composants Blade x-* (P0-07) : classes clés du prototype (thème Social + xm-*) et accessibilité (aria-*, libellés).
 */

function render(string $blade, array $data = []): TestView
{
    return test()->blade($blade, $data);
}

it('rend un bouton primaire, un lien-bouton, le chargement et exige un libellé pour la variante icône', function (): void {
    render('<x-button icon="send">Envoyer</x-button>')
        ->assertSee('class="btn btn-primary"', false)
        ->assertSee('type="button"', false)
        ->assertSee('<i class="bi bi-send me-1" aria-hidden="true"></i>', false)
        ->assertSee('Envoyer');

    render('<x-button variant="secondary" size="sm" href="/signaler">Signaler</x-button>')
        ->assertSee('<a href="/signaler" class="btn btn-secondary btn-sm"', false);

    render('<x-button :loading="true">Enregistrer</x-button>')
        ->assertSee('aria-busy="true"', false)
        ->assertSee('disabled', false)
        ->assertSee('spinner-border spinner-border-sm', false)
        ->assertSee(__('common.sending'));

    render('<x-button variant="icon" icon="bell" label="Notifications" />')
        ->assertSee('aria-label="Notifications"', false)
        ->assertSee('title="Notifications"', false)
        ->assertSee('xm-btn-icon', false)
        ->assertDontSee('me-1');

    expect(fn () => render('<x-button variant="icon" icon="bell" />'))->toThrow(ViewException::class, 'label');
});

it('rend un champ avec libellé lié, aide, erreur et facultatif', function (): void {
    render('<x-field name="email" label="Adresse e-mail" type="email" hint="Jamais partagée." error="Adresse invalide." optional autocomplete="email" />')
        ->assertSee('<label class="form-label" for="f-email">', false)
        ->assertSee(__('components.field.optional'))
        ->assertSee('class="form-control is-invalid"', false)
        ->assertSee('aria-describedby="f-email-hint f-email-error"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('id="f-email-hint" class="form-text"', false)
        ->assertSee('id="f-email-error" class="invalid-feedback d-block"', false)
        ->assertSee('autocomplete="email"', false)
        ->assertSee('mb-3 xm-field', false);

    render('<x-field name="discret" label="Mode discret" type="switch" />')
        ->assertSee('form-check form-switch', false)
        ->assertSee('role="switch"', false);

    render('<x-field name="password" label="Mot de passe" type="password" />')
        ->assertSee(__('components.field.show_password'))
        ->assertSee('aria-pressed="false"', false);

    render('<x-field name="pref" label="Contact" type="radio-group" :options="[\'tel\' => \'Téléphone\', \'email\' => \'E-mail\']" value="email" />')
        ->assertSee('<fieldset', false)
        ->assertSee('<legend class="form-label fs-6">Contact</legend>', false)
        ->assertSee('type="radio"', false)
        ->assertSee('value="email" checked', false);
});

it('rend le résumé d’erreurs focalisable avec un lien par champ', function (): void {
    render('<x-error-summary :messages="[\'email\' => \'Adresse invalide.\', \'password\' => \'Trop court.\']" />')
        ->assertSee('class="alert alert-danger xm-error-summary" role="alert" tabindex="-1"', false)
        ->assertSee('2 erreurs à corriger')
        ->assertSee('href="#f-email"', false)
        ->assertSee('href="#f-password"', false);

    render('<x-error-summary />')->assertDontSee('alert-danger');
});

it('rend une carte Social et la variante liste', function (): void {
    render('<x-card variant="resource" title="Comprendre ses droits" badge="Droits" href="/r/1" meta="5 min">Texte</x-card>')
        ->assertSee('class="card xm-card--resource xm-card-hover h-100"', false)
        ->assertSee('<span class="xm-chip mb-2">Droits</span>', false)
        ->assertSee('<h3 class="h5 card-title">', false)
        ->assertSee('stretched-link', false)
        ->assertSee('class="card-body"', false)
        ->assertSee('small text-secondary mb-2">5 min', false);

    render('<x-card :header="true" title="Dernières alertes"><x-slot:actions><a href="#">Tout voir</a></x-slot:actions> Corps</x-card>')
        ->assertSee('class="card-header d-flex justify-content-between align-items-center gap-2"', false)
        ->assertSee('<h2 class="h6 mb-0">Dernières alertes</h2>', false)
        ->assertDontSee('xm-card-hover');
});

it('rend le KPI du prototype avec sa variante et sa tendance', function (): void {
    render('<x-stat label="Cas ouverts" value="42" variant="danger" icon="folder2-open" trend="+12 %" direction="up" href="/admin/registres" />')
        ->assertSee('class="card xm-stat h-100 danger xm-card-hover"', false)
        ->assertSee('<div class="small text-secondary">Cas ouverts</div>', false)
        ->assertSee('<div class="kpi-value">42</div>', false)
        ->assertSee('bi bi-folder2-open fs-2 text-secondary opacity-50', false)
        ->assertSee('bi-arrow-up-right', false)
        ->assertSee('('.__('components.stat.trend_up').')')
        ->assertSee('<a href="/admin/registres" class="text-decoration-none text-body d-block h-100">', false);
});

it('rend les badges, statuts de cas et niveaux d’urgence avec un libellé en toutes lettres', function (): void {
    render('<x-badge color="success">Ouvertes</x-badge>')->assertSee('class="badge bg-success"', false)->assertSee('Ouvertes');
    render('<x-badge color="warning">Actif</x-badge>')->assertSee('bg-warning text-dark', false);
    render('<x-badge color="primary" soft>Témoignage</x-badge>')->assertSee('bg-primary bg-opacity-10 text-primary', false);
    render('<x-badge color="neutral" pill>Archivé</x-badge>')->assertSee('bg-light text-body border rounded-pill', false);
    render('<x-badge scope="alert" status="nouveau" />')->assertSee('bg-danger', false)->assertSee('Nouveau')->assertSee('bi-bell-fill', false);
    render('<x-badge scope="priority" status="normale" />')->assertSee('Normale')->assertDontSee('bg-secondary');

    render('<x-status status="assigned" />')->assertSee('xm-badge-violet', false)->assertSee('Assigné');
    render('<x-status status="unresolved" />')->assertSee('bg-danger', false)->assertSee('Non résolu');

    render('<x-urgency level="critical" />')
        ->assertSee('class="xm-urgency xm-urgency--critical"', false)
        ->assertSee(__('components.urgency.prefix'))
        ->assertSee('Critique');
});

it('rend une modale Bootstrap avec pied fixe : Annuler à gauche, action à droite', function (): void {
    render('<x-modal id="mContact" title="Nouveau contact" type="form" size="lg" form="/admin/annuaire" action="Enregistrer"><p>Corps</p></x-modal>')
        ->assertSee('class="modal fade" id="mContact" tabindex="-1" aria-labelledby="mContactTitle" aria-hidden="true"', false)
        ->assertSee('class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"', false)
        ->assertSee('<form class="modal-content" method="post" action="/admin/annuaire">', false)
        ->assertSee('<h5 class="modal-title" id="mContactTitle">Nouveau contact</h5>', false)
        ->assertSee('class="btn-close" data-bs-dismiss="modal" aria-label="'.__('common.close').'"', false)
        ->assertSeeInOrder(['<div class="modal-footer">', 'class="btn btn-light" data-bs-dismiss="modal">'.__('common.cancel'), 'type="submit" class="btn btn-primary" data-xm-modal-action', 'Enregistrer'], false);

    render('<x-modal id="mTpl" title="Modifier" size="xl"><p>Corps</p></x-modal>')
        ->assertSee('modal-xl modal-fullscreen-lg-down', false);
});

it('rend la modale de suppression avec le champ de confirmation et le bouton rouge inactif', function (): void {
    render('<x-modal id="mDelete" title="Supprimer ce template ?" type="delete" confirm-word="SUPPRIMER"><p>Définitif.</p></x-modal>')
        ->assertSee('data-xm-confirm-word="SUPPRIMER"', false)
        ->assertSee('class="modal-title text-danger"', false)
        ->assertSee('bi-exclamation-triangle', false)
        ->assertSee('<label class="form-label mb-1" for="mDeleteWord">', false)
        ->assertSee('<code>SUPPRIMER</code>', false)
        ->assertSee('id="mDeleteWord" name="confirm_word" autocomplete="off" required data-xm-confirm-input', false)
        ->assertSee('class="btn btn-danger" data-xm-modal-action disabled', false)
        ->assertSee(__('components.modal.delete_action'));
});

it('rend le conteneur de toasts Bootstrap et un toast initial', function (): void {
    render('<x-toast message="Publié." type="success" />')
        ->assertSee('class="toast-container position-fixed top-0 end-0 p-3" id="xmToastWrap"', false)
        ->assertSee('class="toast align-items-center text-bg-success border-0" role="status" aria-live="polite" aria-atomic="true" data-xm-toast-autoshow', false)
        ->assertSee('<div class="toast-body">Publié.</div>', false)
        ->assertSee('btn-close btn-close-white me-2 m-auto', false);

    render('<x-toast message="Échec." type="danger" />')->assertSee('role="alert" aria-live="assertive"', false);
});

it('rend le bouton SOS de 64 px avec ses alternatives clavier et son annonce', function (): void {
    render('<x-sos-button action="sos-send" />')
        ->assertSee('x-data="xmSos(', false)
        ->assertSee('xm-sos-wrap--fixed', false)
        ->assertSee('class="xm-sos" id="xmSos"', false)
        ->assertSee('data-holding="false"', false)
        ->assertSee('aria-describedby="xmSosHelp"', false)
        ->assertSee('x-on:keydown.space.prevent="startKey()"', false)
        ->assertSee('x-on:keydown.enter.prevent="ask()"', false)
        ->assertSee('role="alertdialog" aria-modal="true" aria-labelledby="xmSosTitle"', false)
        ->assertSee('aria-live="assertive"', false)
        ->assertSee(__('components.sos.cancel'))
        ->assertSee(__('components.sos.help'));

    render('<x-sos-button :fixed="false" id="s2" />')->assertDontSee('xm-sos-wrap--fixed')->assertSee('id="s2"', false);
});

it('rend un accordéon Bootstrap, des onglets ARIA et un fil d’Ariane', function (): void {
    render('<x-accordion id="faq" :items="[[\'q\' => \'Q1\', \'a\' => \'<p>R1</p>\'], [\'q\' => \'Q2\', \'a\' => \'<p>R2</p>\']]" />')
        ->assertSee('class="accordion xm-accordion accordion-flush" id="faq"', false)
        ->assertSee('<h3 class="accordion-header" id="faq-h0">', false)
        ->assertSee('class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq-p0" aria-expanded="true" aria-controls="faq-p0"', false)
        ->assertSee('accordion-button fw-semibold collapsed', false)
        ->assertSee('class="accordion-collapse collapse show" data-bs-parent="#faq"', false)
        ->assertSee('<div class="accordion-body xm-prose"><p>R1</p></div>', false);

    render('<x-tabs :tabs="[\'infos\' => \'Informations\', \'notes\' => \'Notes\']" label="Fiche"><x-slot:infos>A</x-slot:infos> <x-slot:notes>B</x-slot:notes> </x-tabs>')
        ->assertSee('data-xm-tabs="tab"', false)
        ->assertSee('role="tablist" aria-label="Fiche"', false)
        ->assertSee('class="nav-link active" id="tab-infos" data-bs-toggle="tab" data-bs-target="#tab-infos-panel" type="button" role="tab" aria-controls="tab-infos-panel" aria-selected="true"', false)
        ->assertSee('aria-selected="false" tabindex="-1"', false)
        ->assertSee('class="tab-pane fade show active" id="tab-infos-panel" role="tabpanel" aria-labelledby="tab-infos" tabindex="0">A</div>', false);

    render('<x-breadcrumb :items="[[\'label\' => \'Accueil\', \'url\' => \'/\'], [\'label\' => \'Ressources\']]" />')
        ->assertSee('<nav aria-label="'.__('components.breadcrumb.label').'">', false)
        ->assertSee('<ol class="breadcrumb mb-0 small">', false)
        ->assertSee('<li class="breadcrumb-item active" aria-current="page">Ressources</li>', false)
        ->assertDontSee('BreadcrumbList');

    render('<x-breadcrumb :json-ld="true" :items="[[\'label\' => \'Accueil\', \'url\' => \'/\'], [\'label\' => \'Ressources\']]" />')->assertSee('"BreadcrumbList"', false);
});

it('rend le stepper, l’alerte, l’état vide et l’avatar', function (): void {
    render('<x-stepper :steps="[\'Identité\', \'Contact\', \'Sécurité\']" :current="2" />')
        ->assertSee('class="xm-stepper list-unstyled d-flex flex-wrap gap-3 mb-4" aria-label="'.__('components.stepper.label').'"', false)
        ->assertSee('xm-stepper__item is-done', false)
        ->assertSee('xm-stepper__item is-current" aria-current="step"', false)
        ->assertSee(__('components.stepper.todo'));

    render('<x-alert type="danger" title="Erreur" dismissible>Réessaie.</x-alert>')
        ->assertSee('class="alert alert-danger d-flex align-items-start gap-2 alert-dismissible fade show" role="alert"', false)
        ->assertSee('bi-exclamation-octagon', false)
        ->assertSee('data-bs-dismiss="alert" aria-label="'.__('common.close').'"', false);
    render('<x-alert type="success">OK</x-alert>')->assertSee('role="status"', false);

    render('<x-empty-state title="Aucun cas" icon="folder2-open">Les nouveaux cas apparaîtront ici.<x-slot:action><a href="#">Créer</a></x-slot:action> </x-empty-state>')
        ->assertSee('text-center text-secondary xm-empty py-5 px-3', false)
        ->assertSee('bi bi-folder2-open fs-1" aria-hidden="true"', false)
        ->assertSee('<p class="h5 text-body mt-2 mb-2">Aucun cas</p>', false)
        ->assertSee('Créer');

    render('<x-avatar name="Ada Lovelace" size="sm" />')
        ->assertSee('class="avatar avatar-sm"', false)
        ->assertSee('class="avatar-img rounded-circle xm-avatar" role="img" aria-label="'.__('components.avatar.alt', ['name' => 'Ada Lovelace']).'"', false)
        ->assertSee('>AL</span>', false);
    render('<x-avatar name="Ada Lovelace" src="/a.jpg" />')
        ->assertSee('<img class="avatar-img rounded-circle" src="/a.jpg" alt="Avatar de Ada Lovelace" title="Ada Lovelace" width="48" height="48" loading="lazy">', false);
});

it('rend une image accessible et refuse le rendu sans alt', function (): void {
    render('<x-img src="/img.jpg" alt="Deux femmes" width="400" height="300" />')
        ->assertSee('alt="Deux femmes"', false)
        ->assertSee('title="Deux femmes"', false)
        ->assertSee('width="400" height="300"', false)
        ->assertSee('loading="lazy"', false)
        ->assertSee('decoding="async"', false)
        ->assertDontSee('<picture>', false);

    render('<x-img src="/img.jpg" alt="Hero" :priority="true" srcset="/img-800.jpg 800w, /img-1600.jpg 1600w" sizes="100vw" webp="/img.webp" />')
        ->assertSee('loading="eager"', false)
        ->assertSee('fetchpriority="high"', false)
        ->assertSee('srcset="/img-800.jpg 800w, /img-1600.jpg 1600w" sizes="100vw"', false)
        ->assertSee('<source type="image/webp" srcset="/img.webp"', false);

    render('<x-img src="/deco.png" alt="" />')
        ->assertSee('alt=""', false)
        ->assertSee('aria-hidden="true"', false)
        ->assertDontSee('title=');

    expect(fn () => render('<x-img src="/img.jpg" />'))->toThrow(ViewException::class, 'alt');
});

it('construit <picture> WebP → repli + srcset depuis un média téléversé, sans AVIF fictif', function (): void {
    Storage::fake('public');
    $root = Storage::disk('public')->url('');
    $variant = fn (string $name, int $w, int $h): App\Application\Shared\StoredMedia => new App\Application\Shared\StoredMedia('public', "covers/01-$name.webp", 'image/webp', 1000, $w, $h, 'c.jpg', "covers/01-$name.jpg");
    $media = new App\Application\Shared\StoredMedia('public', 'covers/01.webp', 'image/webp', 9000, 2048, 1365, 'c.jpg', 'covers/01.jpg', [
        'thumb' => $variant('thumb', 320, 213), 'medium' => $variant('medium', 1024, 683), 'large' => $variant('large', 1600, 1067),
    ]);

    $view = render('<x-img :media="$media" alt="Atelier à Porto-Novo" />', ['media' => $media]);

    $view->assertSee('<picture>', false)
        ->assertDontSee('image/avif', false)
        ->assertSee('<source type="image/webp" srcset="'.$root.'covers/01-thumb.webp 320w, '.$root.'covers/01-medium.webp 1024w, '.$root.'covers/01-large.webp 1600w" sizes="(max-width: 1600px) 100vw, 1600px">', false)
        ->assertSee('src="'.$root.'covers/01-large.jpg"', false)
        ->assertSee('srcset="'.$root.'covers/01-thumb.jpg 320w, '.$root.'covers/01-medium.jpg 1024w, '.$root.'covers/01-large.jpg 1600w"', false)
        ->assertSee('width="1600" height="1067"', false)
        ->assertSee('loading="lazy"', false)
        ->assertDontSee('covers/01.webp 2048w', false);

    // Forme tableau (colonne JSON) et sizes personnalisé ; une image déjà petite n'a ni variante ni srcset.
    $small = new App\Application\Shared\StoredMedia('public', 'avatars/02.webp', 'image/webp', 500, 300, 300, 'a.png', 'avatars/02.png');
    render('<x-img :media="$media" alt="Avatar" sizes="300px" />', ['media' => $small->toArray()])
        ->assertSee('<source type="image/webp" srcset="'.$root.'avatars/02.webp 300w" sizes="300px">', false)
        ->assertSee('src="'.$root.'avatars/02.png"', false)
        ->assertDontSee('srcset="'.$root.'avatars/02.png', false);

    // Variantes explicites par type MIME : AVIF avant WebP.
    render('<x-img src="/h.jpg" alt="Hero" :variants="[\'image/webp\' => \'/h.webp 1600w\', \'image/avif\' => \'/h.avif 1600w\']" />')
        ->assertSeeInOrder(['type="image/avif"', 'type="image/webp"', 'src="/h.jpg"'], false);

    // Un média privé n'a pas d'URL publique : rendu refusé.
    $private = new App\Application\Shared\StoredMedia('local', 'images/x.webp', 'image/webp', 10, 100, 100, 'x.jpg');
    expect(fn () => render('<x-img :media="$media" alt="Privé" />', ['media' => $private]))->toThrow(ViewException::class, 'public disk');
});

it('rend le logo depuis le dossier de marque', function (): void {
    render('<x-logo variant="couleur" :width="160" />')
        ->assertSee('aria-label="'.__('components.logo.home').'"', false)
        ->assertSee('assets/images/xomiton/brand/logo-couleur.svg', false)
        ->assertSee('width="160" height="41"', false);

    render('<x-logo variant="vertical-couleur" :width="120" :link="false" />')->assertSee('<span', false)->assertDontSee('<a ', false)
        ->assertSee('logo-vertical-couleur.svg', false)->assertSee('width="120" height="69"', false);

    // Icône carrée (icone-appli-*.svg), alias « blanc » et « icone », alt « Xomiton » partout.
    render('<x-logo variant="icone" :width="48" :link="false" />')
        ->assertSee('assets/images/xomiton/brand/icone-appli-bleu.svg', false)->assertSee('width="48" height="48"', false)->assertSee('alt="'.__('components.logo.alt').'"', false);
    render('<x-logo variant="icone-blanc" :width="32" :link="false" />')->assertSee('icone-appli-blanc.svg', false)->assertSee('width="32" height="32"', false);
    render('<x-logo variant="blanc" :link="false" />')->assertSee('logo-mono-blanc.svg', false);

    // Variante inconnue : refus explicite plutôt qu'une image cassée.
    expect(fn () => render('<x-logo variant="rouge" />'))->toThrow(ViewException::class, 'unknown variant');

    // Variante adaptative (en-têtes) : couleur en clair, sombre en sombre, classes sur les <img>, cible et classes du lien.
    render('<x-logo class="navbar-brand" :href="route(\'urgences\')" :adaptive="true" :width="140" img-class="navbar-brand-item" />')
        ->assertSee('<a href="'.route('urgences').'" class="navbar-brand" aria-label="'.__('components.logo.home').'">', false)
        ->assertSee('logo-couleur.svg', false)->assertSee('class="light-mode-item navbar-brand-item"', false)
        ->assertSee('logo-sombre.svg', false)->assertSee('class="dark-mode-item navbar-brand-item"', false)
        ->assertSee('width="140" height="36"', false);
});

it('rend les squelettes de lignes et de cartes', function (): void {
    render('<x-skeleton :lines="2" />')
        ->assertSee('class="xm-skeleton placeholder-glow xm-skeleton--lines" role="status" aria-busy="true"', false)
        ->assertSee(__('components.skeleton.loading'))
        ->assertSee('<div aria-hidden="true">', false)
        ->assertSee('placeholder d-block mb-2 col-12', false);

    render('<x-skeleton type="card" />')->assertSee('xm-skeleton--card', false)->assertSee('xm-ratio-16x9', false)->assertSee('card-body', false);
    render('<x-skeleton type="table" :rows="2" />')->assertSee('xm-skeleton--table', false);
});

it('rend la chronologie d’un dossier et son état vide', function (): void {
    render('<x-timeline :items="[[\'title\' => \'Accueil\', \'date\' => \'12/03/2026\', \'datetime\' => \'2026-03-12\', \'by\' => \'Pair A.\', \'kind\' => \'internal\', \'badge\' => [\'label\' => \'Réalisé\', \'color\' => \'success\'], \'text\' => \'Note.\', \'next\' => \'20/03/2026\', \'late\' => true]]" />')
        ->assertSee('class="xm-timeline list-unstyled mb-0 ms-2 border-start border-2 ps-3" aria-label="'.__('components.timeline.label').'"', false)
        ->assertSee('xm-timeline__dot position-absolute rounded-circle bg-danger', false)
        ->assertSee(__('components.timeline.kinds.internal'))
        ->assertSee('<span class="fw-semibold">Accueil</span>', false)
        ->assertSee('Réalisé')
        ->assertSee(__('components.timeline.late'))
        ->assertSee('<time datetime="2026-03-12">12/03/2026</time>', false)
        ->assertSee(__('components.timeline.next', ['date' => '20/03/2026']));

    render('<x-timeline :items="[]" />')->assertSee(__('components.timeline.empty'));
});

it('rend la cloche avec compteur, onglets et état vide', function (): void {
    $items = [
        ['id' => 'a1', 'icon' => 'bell-fill', 'color' => 'danger', 'text' => 'Alerte SOS', 'time' => 'il y a 5 min', 'href' => '/admin/alertes', 'read' => false],
        ['id' => 'a2', 'icon' => 'check2-circle', 'color' => 'success', 'text' => 'Alerte traitée', 'time' => 'hier', 'href' => '/admin/alertes', 'read' => true],
    ];
    render('<x-bell :items="$items" href="/admin/journal" />', ['items' => $items])
        ->assertSee('class="dropdown xm-bell" id="xmNotif" data-xm-bell', false)
        ->assertSee('data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="'.__('components.bell.label').'"', false)
        ->assertSee('<i class="bi bi-bell" aria-hidden="true"></i>', false)
        ->assertSee('badge rounded-pill bg-danger" data-xm-bell-count>1<span class="visually-hidden"> notification non lue</span>', false)
        ->assertSee('role="tablist" aria-label="'.__('components.bell.filter').'"', false)
        ->assertSeeInOrder([__('components.bell.tabs.unread'), __('components.bell.tabs.read'), __('components.bell.tabs.all')])
        ->assertSee('xm-notif-list', false)
        ->assertSee('xm-notif-unread" href="/admin/alertes" data-notif-id="a1" data-notif-read="0"', false)
        ->assertSee('data-notif-id="a2" data-notif-read="1"', false)
        ->assertSee('icon-md rounded-circle bg-danger bg-opacity-10 text-danger', false)
        ->assertSee(__('components.bell.mark_all_read'))
        ->assertSee(__('components.bell.empty'))
        ->assertSee('href="/admin/journal"', false);

    render('<x-bell :items="[]" />')->assertSee('bg-danger d-none" data-xm-bell-count>0', false)->assertSee('text-secondary small" data-xm-bell-empty', false);
});

it('rend la structure de tableau d’administration avec caption, scope, aria-sort et état vide', function (): void {
    $columns = [['key' => 'code', 'label' => 'Dossier', 'sort' => 'asc', 'href' => '?sort=code'], ['key' => 'zone', 'label' => 'Zone', 'sort' => 'none'], ['key' => 'actions', 'sr' => 'Actions', 'class' => 'text-end']];
    render('<x-datatable caption="Dossiers" :columns="$columns"><tr><td data-label="Dossier">VBG-1</td><td data-label="Zone">Littoral</td><td class="text-end">…</td></tr><x-slot:footer><span>Affichage de 1 à 1 sur 1 entrée</span></x-slot:footer> </x-datatable>', ['columns' => $columns])
        ->assertSee('class="xm-table-wrap"', false)
        ->assertSee('class="table table-hover align-middle mb-0 xm-table--stack"', false)
        ->assertSee('<caption class="visually-hidden">Dossiers</caption>', false)
        ->assertSee('<th scope="col" aria-sort="ascending">', false)
        ->assertSee('<a class="xm-sort text-reset text-decoration-none fw-bold" href="?sort=code"', false)
        ->assertSee('aria-sort="none"', false)
        ->assertSee('data-xm-sort="zone"', false)
        ->assertSee('<span class="visually-hidden">Actions</span>', false)
        ->assertSee('<td data-label="Dossier">VBG-1</td>', false)
        ->assertSee('class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 xm-dt-bar"', false);

    render('<x-datatable caption="Contacts" :columns="$columns" :stack="false" empty="Aucun contact." />', ['columns' => $columns])
        ->assertSee('<td colspan="3" class="text-center text-secondary py-4 xm-table__empty">Aucun contact.</td>', false)
        ->assertDontSee('xm-table--stack');
});

it('sert le catalogue /design-system en local et en recette, 404 en production', function (): void {
    foreach (['local', 'staging'] as $env) {
        $this->app['env'] = $env;
        config(['app.env' => $env]);
        $this->get('/design-system')
            ->assertOk()
            ->assertSee(__('design_system.heading'))
            ->assertSee('data-bs-theme-value="dark"', false)
            ->assertSee('class="xm-sos" id="dsSos"', false)
            ->assertSee('id="dsModalDelete"', false)
            ->assertSee('content="noindex, nofollow"', false)
            ->assertDontSee('@endslot')
            ->assertDontSee('@php');
    }

    $this->app['env'] = 'production';
    config(['app.env' => 'production']);
    $this->get('/design-system')->assertNotFound();
});

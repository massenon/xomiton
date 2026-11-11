<?php

declare(strict_types=1);

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;

/*
 * P10-02 : rendu des composants communs de l'administration — x-admin.kpi (accordéon < 768 px, premier KPI en résumé,
 * « données masquées »), x-admin.filters (recherche ?q= en direct, Réinitialiser si filtre actif), x-admin.table
 * (aria-sort sur la seule colonne triée, tri Livewire, data-label, squelettes wire:loading, état vide),
 * x-admin.pagination (« Affichage de x à y sur n entrées », 10 / 25 / 50, page courante), x-admin.bulk-bar,
 * x-admin.modal (montée en permanence, wire:ignore.self), x-admin.empty (variante filtrée).
 */

function adminRender(string $blade, array $data = []): string
{
    return Blade::render($blade, $data);
}

it('rend la rangée de KPI avec l’accordéon mobile et le premier KPI en résumé', function (): void {
    $html = adminRender('<x-admin.kpi id="k" :items="$items" />', ['items' => [
        ['label' => 'Entrées', 'value' => '1 204', 'icon' => 'clock-history'],
        ['label' => 'Cas', 'value' => '3', 'masked' => true],
    ]]);

    expect($html)->toContain('<h2 class="visually-hidden" id="kTitle">Indicateurs clés</h2>')
        ->toMatch('/<button type="button" class="xm-kpis__toggle d-md-none" data-bs-toggle="collapse" data-bs-target="#kBody" aria-expanded="false" aria-controls="kBody"/')
        ->toContain("Entrées\u{00A0}: 1 204")
        ->toContain('class="collapse d-md-block xm-kpis__body" id="kBody" wire:ignore.self')
        ->toContain('données masquées')
        ->toContain('xm-stat--masked')
        ->toContain('Les effectifs inférieurs à 5 ne sont pas affichés')
        ->and(substr_count($html, 'class="card xm-stat'))->toBe(2);
});

it('rend la barre de filtres : recherche en direct, aide ADR-33, Réinitialiser seulement si un filtre est actif', function (): void {
    $idle = adminRender('<x-admin.filters id="f" />');
    $active = adminRender('<x-admin.filters id="f" :active="2"><div class="col-6">select</div></x-admin.filters>');

    expect($idle)->toContain('role="search"')
        ->toContain('wire:submit="applyFilters"')
        ->toContain('wire:model.live.debounce.300ms="search"')
        ->toContain('jamais sur les récits chiffrés')
        ->not->toContain('wire:click="resetFilters"')
        ->and($active)->toContain('wire:click="resetFilters"')
        ->toContain('2 filtres actifs')
        ->toContain('<div class="col-6">select</div>');
});

it('rend le tableau Livewire : tri, aria-sort sur la colonne triée, squelettes et état vide', function (): void {
    $columns = [
        ['key' => 'date', 'label' => 'Date', 'sortable' => true],
        ['key' => 'nom', 'label' => 'Nom', 'sortable' => true],
        ['key' => 'actions', 'label' => 'Actions', 'sr' => 'Actions', 'class' => 'text-end'],
    ];
    $html = adminRender('<x-admin.table caption="Liste" :columns="$columns" sort="nom" direction="desc" target="search,sortBy"><tr><td data-label="Date">1</td></tr><x-slot:footer>pied</x-slot:footer></x-admin.table>', ['columns' => $columns]);
    $empty = adminRender('<x-admin.table caption="Liste" :columns="$columns" :empty="true"><x-slot:emptyState>Rien ici</x-slot:emptyState></x-admin.table>', ['columns' => $columns]);

    expect($html)->toContain('<caption class="visually-hidden">Liste</caption>')
        ->toContain('aria-sort="descending"')
        ->toContain('wire:click="sortBy(\'date\')"')
        ->toContain('wire:click="sortBy(\'nom\')"')
        ->toContain('(tri décroissant)')
        ->toContain('<span class="visually-hidden">Actions</span>')
        ->toContain('class="table table-hover align-middle mb-0 xm-table--stack"')
        ->toContain('wire:loading.delay.class="d-none" wire:target="search,sortBy"')
        ->toContain('class="d-none xm-table__skeleton" wire:loading.delay.class.remove="d-none"')
        ->toContain('Chargement du tableau…')
        ->toContain('class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 xm-dt-bar">pied')
        ->and(substr_count($html, 'aria-sort='))->toBe(1)
        ->and($empty)->toContain('<td colspan="3" class="xm-table__empty">Rien ici</td>');
});

it('rend la pagination serveur : compteur, tailles 10 / 25 / 50, page courante et fenêtre avec « … »', function (): void {
    $paginator = new LengthAwarePaginator(range(1, 25), 300, 25, 6);
    $html = adminRender('<x-admin.pagination :paginator="$p" />', ['p' => $paginator]);

    expect($html)->toContain('Affichage de 126 à 150 sur 300 entrées')
        ->toContain('wire:model.live="perPage"')
        ->toContain('<option value="10">10</option>')
        ->toContain('<option value="25">25</option>')
        ->toContain('<option value="50">50</option>')
        ->toContain('aria-label="Pagination du tableau"')
        ->toContain('wire:click="gotoPage(6, \'page\')"')
        ->toContain('aria-label="Page 6, page actuelle"')
        ->toContain('wire:click="gotoPage(12, \'page\')"')
        ->toContain('wire:click="previousPage(\'page\')"')
        ->and(substr_count($html, '<span class="page-link">…</span>'))->toBe(2);

    $none = adminRender('<x-admin.pagination :paginator="$p" />', ['p' => new LengthAwarePaginator([], 0, 25, 1)]);
    expect($none)->toContain('Aucune entrée')->not->toContain('aria-label="Pagination du tableau"');
});

it('rend la barre d’actions groupées seulement quand une ligne est sélectionnée', function (): void {
    expect(trim(adminRender('<x-admin.bulk-bar :count="0" :actions="[\'delete\' => \'Supprimer\']" />')))->toBe('');

    // Aucune action choisie : « Appliquer » inactif, sans confirmation ni appel.
    $idle = adminRender('<x-admin.bulk-bar :count="3" :actions="[\'delete\' => \'Supprimer\']" />');
    expect($idle)->toContain('3 sélectionné·es')
        ->toContain('wire:model.live="bulkAction"')
        ->toContain('<option value="delete">Supprimer</option>')
        ->toMatch('/<button[^>]*disabled>Appliquer<\/button>/')
        ->not->toContain('wire:click="applyBulk"')
        ->not->toContain('wire:confirm=')
        ->toContain('wire:click="clearSelection"');

    // Action choisie : la confirmation nomme l'action et accorde le nombre.
    $one = adminRender('<x-admin.bulk-bar :count="1" :actions="[\'delete\' => \'Supprimer\']" selected="delete" />');
    $many = adminRender('<x-admin.bulk-bar :count="3" :actions="[\'delete\' => \'Supprimer\']" selected="delete" />');
    expect($one)->toContain('wire:click="applyBulk"')
        ->toContain("wire:confirm=\"Appliquer «\u{00A0}Supprimer\u{00A0}» à 1 élément\u{00A0}?\"")
        ->and($many)->toContain("wire:confirm=\"Appliquer «\u{00A0}Supprimer\u{00A0}» à 3 éléments\u{00A0}?\"")
        ->and($many)->toContain('wire:key="xmBulkApply-delete-3"');
});

it('rend la modale Livewire montée en permanence et l’état vide filtré', function (): void {
    $modal = adminRender('<x-admin.modal id="mEdit" title="Modifier le contact"><p>Corps</p><x-slot:footer><button class="btn btn-primary">Enregistrer</button></x-slot:footer></x-admin.modal>');
    $empty = adminRender('<x-admin.empty filtered />');
    $plain = adminRender('<x-admin.empty title="Aucun contact" icon="people">Ajoutez le premier.</x-admin.empty>');

    expect($modal)->toContain('id="mEdit"')
        ->toContain('wire:ignore.self')
        ->toContain('data-xm-livewire-modal')
        ->toContain('<h2 class="modal-title h5" id="mEditTitle">Modifier le contact</h2>')
        ->toContain('modal-dialog-scrollable')
        ->toContain('Enregistrer')
        ->and($empty)->toContain('Aucun élément ne correspond aux filtres.')
        ->toContain('wire:click="resetFilters"')
        ->and($plain)->toContain('Aucun contact')->toContain('Ajoutez le premier.')->not->toContain('resetFilters');
});

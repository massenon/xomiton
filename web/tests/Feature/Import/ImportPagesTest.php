<?php

declare(strict_types=1);

use App\Application\Import\PrototypeImporter;
use App\Models\Page;
use App\Models\User;

/*
 * P8-03 (revue du lot groupes-pages) : les pages du prototype sont importées **approuvées** à leur création ; une
 * réimportation ne revient pas sur une décision de la modération, et une page d'un·e membre au même slug n'est ni
 * écrasée ni approuvée sans modération (ligne rejetée).
 */

it('approuve les pages importées à leur création, sans reprendre la page d’un·e membre ni une page dé-approuvée', function (): void {
    $member = User::factory()->withProfile()->create();
    $mine = Page::factory()->create([
        'name' => 'Clinique de la Paix', 'slug' => 'clinique-de-la-paix', 'description' => 'Ma page', 'owner_id' => $member->id,
        'is_approved' => false, 'approved_at' => null,
    ]);
    $revoked = Page::factory()->create([
        'name' => 'Barreau – Aide juridique', 'slug' => 'barreau-aide-juridique', 'owner_id' => null, 'is_approved' => false, 'approved_at' => null,
    ]);

    $rows = (new PrototypeImporter)
        ->import(directory: dirname(__DIR__, 4).'/prototype_social/data', only: ['pages'], withDemo: true)
        ->rows();

    expect($rows['pages']['rejected'])->toBe(1)
        ->and($rows['pages']['created'])->toBe(2)
        ->and($mine->fresh()->is_approved)->toBeFalse()
        ->and($mine->fresh()->description)->toBe('Ma page')
        ->and($mine->fresh()->owner_id)->toBe($member->id)
        ->and($revoked->fresh()->is_approved)->toBeFalse()
        ->and(Page::query()->where('slug', 'xomiton-page-officielle')->value('is_approved'))->toBeTrue()
        ->and(Page::query()->where('slug', 'reseau-des-pairs-sud')->value('is_approved'))->toBeTrue();
});

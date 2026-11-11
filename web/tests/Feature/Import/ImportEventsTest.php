<?php

declare(strict_types=1);

use App\Application\Import\PrototypeImporter;
use App\Models\Event;

/*
 * P8-04 : un lieu du prototype « Ville – lieu précis » est coupé à l'import — la ville reste publique (`place`), le lieu
 * précis devient l'adresse exacte chiffrée, réservée aux participant·es ; aucun événement importé n'est public (Q35).
 */

it('sépare la ville de l’adresse exacte et n’ouvre aucun événement au public', function (): void {
    (new PrototypeImporter)->import(directory: dirname(__DIR__, 4).'/prototype_social/data', only: ['events'], withDemo: true);

    $stade = Event::query()->where('slug', 'seance-de-self-defense')->sole();
    $online = Event::query()->where('slug', 'webinaire-connaitre-ses-droits')->sole();

    expect($stade->place)->toBe('Cotonou')
        ->and($stade->address)->toBe('Stade de l\'Amitié')
        ->and($online->place)->toBe('En ligne')
        ->and($online->address)->toBeNull()
        ->and($online->is_online)->toBeTrue()
        ->and(Event::query()->where('is_public', true)->count())->toBe(0);
});

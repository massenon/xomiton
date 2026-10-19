<?php

declare(strict_types=1);

use App\Application\Sync\Pull\PullCursor;
use App\Application\Sync\Pull\PullPosition;
use App\Domain\Sync\Exceptions\PullCursorInvalid;
use App\Domain\Sync\FieldMerge;
use App\Domain\Sync\FieldOwner;

/*
 * P9-06 (lot synchro) : fusion par champ (SOFTWARE_ARCHITECTURE.md §5) et jeton de reprise de `sync/pull`.
 */

$owners = ['status' => FieldOwner::Server, 'notes' => FieldOwner::Author];

it('applique la valeur de l’appareil quand le serveur n’a pas bougé et ignore une valeur déjà identique', function () use ($owners): void {
    $outcome = FieldMerge::resolve(
        server: ['referral' => 'Juriste', 'sex' => 'femme'],
        base: ['referral' => 'Juriste', 'sex' => 'homme'],
        device: ['referral' => 'Psychologue', 'sex' => 'femme'],
        owners: $owners,
        deviceIsAuthor: false,
    );

    expect($outcome->applied)->toBe(['referral' => 'Psychologue'])
        ->and($outcome->unchanged)->toBe(['sex'])
        ->and($outcome->kept)->toBe([])
        ->and($outcome->hasConflicts())->toBeFalse();
});

it('tranche une modification concurrente : serveur pour le statut, autrice pour ses notes, conflit sinon', function () use ($owners): void {
    $server = ['status' => 'en_cours', 'notes' => 'Version du serveur.', 'referral' => 'Juriste'];
    $base = ['status' => 'ouvert', 'notes' => 'Version initiale.', 'referral' => 'Point focal'];
    $device = ['status' => 'clos', 'notes' => 'Version de l’appareil.', 'referral' => 'Psychologue'];

    $author = FieldMerge::resolve($server, $base, $device, $owners, deviceIsAuthor: true);
    expect($author->applied)->toBe(['notes' => 'Version de l’appareil.'])
        ->and($author->kept)->toBe(['status'])
        ->and($author->conflicts)->toBe(['referral' => ['server' => 'Juriste', 'device' => 'Psychologue']]);

    $other = FieldMerge::resolve($server, $base, $device, $owners, deviceIsAuthor: false);
    expect($other->applied)->toBe([])
        ->and(array_keys($other->conflicts))->toBe(['notes', 'referral']);
});

it('encode et relit le jeton de reprise, refuse un jeton illisible', function (): void {
    $cursor = PullCursor::start()->with('cases', new PullPosition(1_760_000_000, 42, 1_760_000_100, 7));
    $token = $cursor->encode();

    expect($token)->not->toContain('=')->not->toContain('+')->not->toContain('/')
        ->and(PullCursor::decode($token)->position('cases')->toArray())->toBe([1_760_000_000, 42, 1_760_000_100, 7])
        ->and(PullCursor::decode($token)->position('alerts')->isStart())->toBeTrue()
        ->and(PullCursor::decode(null)->position('cases')->isStart())->toBeTrue();

    foreach (['pas-un-jeton', base64_encode('{"v":2,"c":{}}'), base64_encode('{"v":1,"c":{"cases":[1,2,3]}}'), base64_encode('{"v":1,"c":{"cases":[1,2,3,-4]}}')] as $bad) {
        expect(fn () => PullCursor::decode($bad))->toThrow(PullCursorInvalid::class);
    }
});

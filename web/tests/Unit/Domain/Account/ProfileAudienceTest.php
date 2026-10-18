<?php

declare(strict_types=1);

use App\Domain\Account\ProfileAudience;
use App\Enums\ProfileVisibility;

/*
 * Visibilité d'un profil (P6-08, `GET users/{id}/posts`) : règle pure, mêmes valeurs que les albums.
 */

it('montre toujours son propre profil, même privé, suspendu ou bloqué', function (): void {
    expect(ProfileAudience::allows(ProfileVisibility::Private, isSelf: true, isReachable: false, blocked: true))->toBeTrue();
});

it('applique public, communauté, abonné·es et privé aux autres comptes', function (?ProfileVisibility $visibility, bool $follows, bool $expected): void {
    expect(ProfileAudience::allows($visibility, isSelf: false, followsOwner: $follows))->toBe($expected);
})->with([
    'public' => [ProfileVisibility::Public, false, true],
    'communauté' => [ProfileVisibility::Community, false, true],
    'non renseignée = communauté' => [null, false, true],
    'abonné·es, non suivi·e' => [ProfileVisibility::Followers, false, false],
    'abonné·es, suivi·e' => [ProfileVisibility::Followers, true, true],
    'privé' => [ProfileVisibility::Private, true, false],
]);

it('retire le profil d’un compte inaccessible ou en cas de blocage', function (): void {
    expect(ProfileAudience::allows(ProfileVisibility::Public, isSelf: false, isReachable: false))->toBeFalse()
        ->and(ProfileAudience::allows(ProfileVisibility::Public, isSelf: false, blocked: true))->toBeFalse();
});

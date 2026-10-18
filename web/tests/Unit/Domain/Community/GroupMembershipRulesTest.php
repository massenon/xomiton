<?php

declare(strict_types=1);

use App\Domain\Community\GroupMembershipRules as Rules;
use App\Enums\GroupPrivacy;
use App\Enums\GroupRole;
use App\Enums\MembershipStatus;

/*
 * P8-02 : règles d'adhésion sans base (App\Domain\Community\GroupMembershipRules).
 */

it('rend membre aussitôt dans un groupe public, crée une demande dans un groupe privé', function (): void {
    expect(Rules::joinStatus(GroupPrivacy::Public))->toBe(MembershipStatus::Active)
        ->and(Rules::joinStatus(GroupPrivacy::Private))->toBe(MembershipStatus::Requested);
});

it('retient la dernière admin', function (bool $isAdmin, int $others, bool $allowed): void {
    expect(Rules::canLeave($isAdmin, $others))->toBe($allowed);
})->with([
    'membre' => [false, 0, true],
    'dernière admin' => [true, 0, false],
    'admin avec une autre admin' => [true, 1, true],
]);

it('gère selon la hiérarchie : jamais la propriétaire, admin > modération > membre', function (bool $owner, ?GroupRole $actor, bool $targetOwner, GroupRole $target, bool $manage, bool $role): void {
    expect(Rules::canManage($owner, $actor, $targetOwner, $target))->toBe($manage)
        ->and(Rules::canChangeRole($owner, $actor, $targetOwner, $target))->toBe($role);
})->with([
    'propriétaire → admin' => [true, GroupRole::Admin, false, GroupRole::Admin, true, true],
    'admin → propriétaire' => [false, GroupRole::Admin, true, GroupRole::Admin, false, false],
    'admin → modérateur·rice' => [false, GroupRole::Admin, false, GroupRole::Moderator, true, true],
    'admin → admin' => [false, GroupRole::Admin, false, GroupRole::Admin, false, false],
    'modérateur·rice → membre' => [false, GroupRole::Moderator, false, GroupRole::Member, true, false],
    'modérateur·rice → modérateur·rice' => [false, GroupRole::Moderator, false, GroupRole::Moderator, false, false],
    'membre → membre' => [false, GroupRole::Member, false, GroupRole::Member, false, false],
    'hors groupe → membre' => [false, null, false, GroupRole::Member, false, false],
]);

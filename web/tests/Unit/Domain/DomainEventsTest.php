<?php

declare(strict_types=1);

use App\Domain\Alerts\Events\AlertCreated;
use App\Domain\Alerts\Events\SosTriggered;
use App\Domain\Auth\Events\PeerValidated;
use App\Domain\Feed\Events\PollVoted;
use App\Domain\Moderation\Events\ContentReported;
use App\Domain\Newsletter\Events\CampaignSent;
use App\Domain\Sync\Events\SyncConflictDetected;
use Illuminate\Database\Eloquent\Model;

/*
 * P3-08 : événements de domaine immuables (readonly), construits avec des identifiants et des valeurs simples, sans base
 * de données ni modèle Eloquent sérialisé.
 */

/**
 * @return array<string, object>
 */
function domainEvents(): array
{
    $at = new DateTimeImmutable('2026-10-18 15:00:00');

    return [
        'AlertCreated' => new AlertCreated(12, 'denonciation', 'haute', 8, 21, 3, 'role_zone', $at),
        'SosTriggered' => new SosTriggered(13, 42, 8, 21, true, null, $at),
        'PollVoted' => new PollVoted(5, 9, 42, [2, 3], $at),
        'CampaignSent' => new CampaignSent(7, 120, 118, $at),
        'PeerValidated' => new PeerValidated(42, 1),
        'ContentReported' => new ContentReported(4, 'publication', 'Insultes', null, $at),
        'SyncConflictDetected' => new SyncConflictDetected(6, 2, 'App\\Models\\CaseRecord', 12, 'status', $at),
    ];
}

it('instancie chaque événement avec ses identifiants et ne transporte aucun modèle Eloquent', function (): void {
    foreach (domainEvents() as $name => $event) {
        $reflection = new ReflectionClass($event);

        expect($reflection->isReadOnly())->toBeTrue($name)
            ->and($reflection->isFinal())->toBeTrue($name)
            ->and(method_exists($event, 'dispatch'))->toBeTrue($name);

        foreach ($reflection->getProperties() as $property) {
            $value = $property->getValue($event);
            $type = $property->getType();

            expect($value)->not->toBeInstanceOf(Model::class, $name.'::'.$property->getName())
                ->and($type instanceof ReflectionNamedType ? $type->getName() : '')->not->toContain('Models', $name.'::'.$property->getName());
        }
    }
});

it('refuse toute modification après construction', function (): void {
    $event = new PeerValidated(42, 1);

    expect(fn () => $event->peerId = 7)->toThrow(Error::class)
        ->and($event->peerId)->toBe(42)
        ->and($event->validatedById)->toBe(1);
});

it('porte les données attendues par les écouteurs', function (): void {
    $events = domainEvents();

    /** @var AlertCreated $alert */
    $alert = $events['AlertCreated'];
    /** @var SosTriggered $sos */
    $sos = $events['SosTriggered'];
    /** @var CampaignSent $campaign */
    $campaign = $events['CampaignSent'];
    /** @var PollVoted $vote */
    $vote = $events['PollVoted'];
    /** @var SyncConflictDetected $conflict */
    $conflict = $events['SyncConflictDetected'];

    expect($alert->isRouted())->toBeTrue()
        ->and((new AlertCreated(1, 'besoin', 'normale', null, null, null, 'first_active', new DateTimeImmutable))->isRouted())->toBeFalse()
        ->and($alert->occurredAt->format('Y-m-d H:i'))->toBe('2026-10-18 15:00')
        ->and($sos->isAnonymous())->toBeFalse()
        ->and((new SosTriggered(2, null, null, null, false, null, new DateTimeImmutable))->isAnonymous())->toBeTrue()
        ->and($sos->hasLocation)->toBeTrue()
        ->and($campaign->failedCount())->toBe(2)
        ->and((new CampaignSent(1, 10, 12, new DateTimeImmutable))->failedCount())->toBe(0)
        ->and($vote->optionIds)->toBe([2, 3])
        ->and($conflict->subjectType)->toBe('App\\Models\\CaseRecord')
        ->and($conflict->field)->toBe('status');
});

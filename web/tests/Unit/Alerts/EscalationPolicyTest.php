<?php

declare(strict_types=1);

use App\Domain\Alerts\Enums\EscalationAction;
use App\Domain\Alerts\EscalationPolicy;
use App\Domain\Alerts\ValueObjects\EscalationDelays;
use App\Domain\Alerts\ValueObjects\EscalationSubject;
use App\Enums\AlertType;

/*
 * P5-01 / P5-07 : politique RG-05 (SOS 5 min → escalade) et Q27 (signalement / besoin : rappel 24 h, escalade 48 h),
 * bornes incluses, une seule escalade, jamais de rappel après l'escalade. Sans base.
 */

function escalationPolicy(): EscalationPolicy
{
    return EscalationPolicy::fromArray([
        'sos' => ['remind_after_minutes' => null, 'escalate_after_minutes' => 5],
        'denonciation' => ['remind_after_minutes' => 1440, 'escalate_after_minutes' => 2880],
        'besoin' => ['remind_after_minutes' => 1440, 'escalate_after_minutes' => 2880],
    ]);
}

function escalationSubject(AlertType $type, array $overrides = []): EscalationSubject
{
    return new EscalationSubject(...[
        'type' => $type,
        'createdAt' => new DateTimeImmutable('2026-10-18 12:00:00'),
        ...$overrides,
    ]);
}

function escalationAt(string $offset): DateTimeImmutable
{
    return (new DateTimeImmutable('2026-10-18 12:00:00'))->modify($offset);
}

it('escalade un SOS à 5 min 00 s, pas à 4 min 59 s, et encore à 5 min 59 s', function (): void {
    $sos = escalationSubject(AlertType::Sos);

    expect(escalationPolicy()->decide($sos, escalationAt('+299 seconds')))->toBe(EscalationAction::None)
        ->and(escalationPolicy()->decide($sos, escalationAt('+300 seconds')))->toBe(EscalationAction::Escalate)
        ->and(escalationPolicy()->decide($sos, escalationAt('+359 seconds')))->toBe(EscalationAction::Escalate);
});

it('n’escalade jamais un SOS accusé, annulé, en sécurité, traité ou déjà escaladé', function (string $field): void {
    $overrides = $field === 'isTerminal' ? ['isTerminal' => true] : [$field => escalationAt('+1 minute')];

    expect(escalationPolicy()->decide(escalationSubject(AlertType::Sos, $overrides), escalationAt('+10 minutes')))->toBe(EscalationAction::None);
})->with(['acknowledgedAt', 'cancelledAt', 'safeAt', 'isTerminal', 'escalatedAt']);

it('rappelle un signalement à 24 h puis l’escalade à 48 h, sans jamais rappeler après l’escalade', function (AlertType $type): void {
    $report = escalationSubject($type);

    expect(escalationPolicy()->decide($report, escalationAt('+1439 minutes')))->toBe(EscalationAction::None)
        ->and(escalationPolicy()->decide($report, escalationAt('+1440 minutes')))->toBe(EscalationAction::Remind)
        ->and(escalationPolicy()->decide(escalationSubject($type, ['remindedAt' => escalationAt('+1440 minutes')]), escalationAt('+1500 minutes')))->toBe(EscalationAction::None)
        ->and(escalationPolicy()->decide(escalationSubject($type, ['remindedAt' => escalationAt('+1440 minutes')]), escalationAt('+2880 minutes')))->toBe(EscalationAction::Escalate)
        ->and(escalationPolicy()->decide($report, escalationAt('+2880 minutes')))->toBe(EscalationAction::Escalate)
        ->and(escalationPolicy()->decide(escalationSubject($type, ['escalatedAt' => escalationAt('+2880 minutes')]), escalationAt('+3000 minutes')))->toBe(EscalationAction::None);
})->with([AlertType::Report, AlertType::Need]);

it('ne fait rien pour un délai absent et donne le plus petit délai configuré', function (): void {
    $noEscalation = new EscalationPolicy(['sos' => new EscalationDelays(10, null)]);
    $nothing = new EscalationPolicy([]);

    expect($noEscalation->decide(escalationSubject(AlertType::Sos), escalationAt('+1 year')))->toBe(EscalationAction::Remind)
        ->and($noEscalation->decide(escalationSubject(AlertType::Sos, ['remindedAt' => escalationAt('+10 minutes')]), escalationAt('+1 year')))->toBe(EscalationAction::None)
        ->and($nothing->decide(escalationSubject(AlertType::Report), escalationAt('+1 year')))->toBe(EscalationAction::None)
        ->and(escalationPolicy()->earliestDelayMinutes())->toBe(5)
        ->and($nothing->earliestDelayMinutes())->toBeNull()
        ->and(escalationPolicy()->types())->toBe([AlertType::Sos, AlertType::Report, AlertType::Need])
        ->and(EscalationDelays::fromArray(['remind_after_minutes' => '0', 'escalate_after_minutes' => 'x']))->toEqual(EscalationDelays::none());

    expect(fn () => new EscalationDelays(0, 5))->toThrow(InvalidArgumentException::class);
});

it('compte les minutes écoulées depuis la création', function (): void {
    expect(escalationSubject(AlertType::Sos)->minutesSinceCreation(escalationAt('+359 seconds')))->toBe(5)
        ->and(escalationSubject(AlertType::Sos)->minutesSinceCreation(escalationAt('-1 minute')))->toBe(0);
});

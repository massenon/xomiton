<?php

declare(strict_types=1);

namespace App\Domain\Alerts;

use App\Domain\Alerts\Enums\EscalationAction;
use App\Domain\Alerts\ValueObjects\EscalationDelays;
use App\Domain\Alerts\ValueObjects\EscalationSubject;
use App\Enums\AlertType;
use DateTimeImmutable;

/**
 * Escalade RG-05 et relance Q27 (BUSINESS_ANALYSIS RG-05, FR-ALT-05 ; IMPLEMENTATION_PLAN Q27) : un SOS sans accusé
 * de réception après son délai (5 min par défaut) est escaladé au point focal national ; un signalement ou un besoin
 * sans accusé est d'abord rappelé au contact routé (24 h), puis escaladé (48 h). Délais par type, bornes incluses ;
 * le cron chaque minute donne une exécution entre N et N + 1 min (« ± 1 »). Une alerte n'est escaladée et rappelée
 * qu'une fois ; l'escalade échue l'emporte sur un rappel manqué. Pur : ni base, ni horloge, ni configuration.
 */
final class EscalationPolicy
{
    /** @var array<string, EscalationDelays> */
    private readonly array $delays;

    /**
     * @param  array<string, EscalationDelays>  $delays  indexé par AlertType->value
     */
    public function __construct(array $delays)
    {
        $this->delays = $delays;
    }

    /**
     * @param  array<string, array{remind_after_minutes?: int|string|null, escalate_after_minutes?: int|string|null}>  $config
     */
    public static function fromArray(array $config): self
    {
        $delays = [];

        foreach ($config as $type => $values) {
            if (AlertType::tryFrom((string) $type) !== null && is_array($values)) {
                $delays[(string) $type] = EscalationDelays::fromArray($values);
            }
        }

        return new self($delays);
    }

    public function decide(EscalationSubject $subject, DateTimeImmutable $now): EscalationAction
    {
        if ($subject->isSettled()) {
            return EscalationAction::None;
        }

        $delays = $this->delaysFor($subject->type);
        $elapsed = $now->getTimestamp() - $subject->createdAt->getTimestamp();

        if ($subject->escalatedAt === null && $delays->escalateAfterMinutes !== null && $elapsed >= $delays->escalateAfterMinutes * 60) {
            return EscalationAction::Escalate;
        }

        if ($subject->escalatedAt === null && $subject->remindedAt === null && $delays->remindAfterMinutes !== null && $elapsed >= $delays->remindAfterMinutes * 60) {
            return EscalationAction::Remind;
        }

        return EscalationAction::None;
    }

    public function delaysFor(AlertType $type): EscalationDelays
    {
        return $this->delays[$type->value] ?? EscalationDelays::none();
    }

    /** Plus petit délai configuré, tous types confondus (borne de la requête de sélection) ; null si aucun. */
    public function earliestDelayMinutes(): ?int
    {
        $values = array_filter(array_map(static fn (EscalationDelays $d): ?int => $d->shortest(), $this->delays), static fn (?int $v): bool => $v !== null);

        return $values === [] ? null : min($values);
    }

    /**
     * Types concernés par au moins un délai.
     *
     * @return list<AlertType>
     */
    public function types(): array
    {
        $types = [];

        foreach ($this->delays as $type => $delays) {
            if ($delays->shortest() !== null && ($case = AlertType::tryFrom($type)) !== null) {
                $types[] = $case;
            }
        }

        return $types;
    }
}

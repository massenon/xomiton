<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Événements de contenu comptés côté serveur, sans traceur tiers (P10-06, CONTENT_STRATEGY.md §1). Les autres lignes du
 * tableau « Contenu » (signalements complétés, délai SOS, inscriptions newsletter) se calculent sur les tables existantes.
 */
enum ContentEventKind: string
{
    case SignupClick = 'signup_click';
    case Download = 'download';
    case ReportClick = 'report_click';
    case EmergencyCall = 'emergency_call';
    case FaqNoResult = 'faq_no_result';

    public function label(): string
    {
        return (string) __('steering.content.metrics.'.$this->value);
    }
}

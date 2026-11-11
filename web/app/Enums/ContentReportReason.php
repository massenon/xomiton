<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Motif d'un signalement de contenu par un·e membre (P7-02, FR-SOC-06 ; prototype messaging.php l.100, motifs de
 * data/moderation_reports.json). L'API reçoit la valeur ; `moderation_reports.reason` garde le libellé, comme les
 * signalements importés du prototype (« Harcèlement », « Spam »…). `Automatic` est réservé au filtre de mots.
 */
enum ContentReportReason: string
{
    case Harassment = 'harcelement';
    case Discrimination = 'discrimination';
    case Threats = 'menaces';
    case PrivateData = 'donnees_personnelles';
    case Misinformation = 'desinformation';
    case Impersonation = 'usurpation';
    case Spam = 'spam';
    case Other = 'autre';
    case Automatic = 'filtre_automatique';

    public function label(): string
    {
        return (string) __('enums/feed.report_reason.'.$this->value);
    }

    /**
     * Motifs proposés aux membres (le motif automatique est exclu).
     *
     * @return list<string>
     */
    public static function selectable(): array
    {
        return array_values(array_map(
            static fn (self $reason): string => $reason->value,
            array_filter(self::cases(), static fn (self $reason): bool => $reason !== self::Automatic),
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Str;

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
     * Gravité du motif dans le score de priorité de la file de modération (P18-04, App\Domain\Moderation\ReportPriority) :
     * menaces et données personnelles (dont contenus intimes) d'abord, spam en dernier.
     */
    public function severity(): int
    {
        return match ($this) {
            self::Threats => 50,
            self::PrivateData => 45,
            self::Harassment => 40,
            self::Discrimination => 35,
            self::Impersonation => 25,
            self::Misinformation, self::Other, self::Automatic => 15,
            self::Spam => 5,
        };
    }

    /**
     * Motif d'après la valeur rangée dans `moderation_reports.reason` : le libellé (signalements des membres et du
     * filtre, comme ceux importés du prototype, « Harcèlement », « Spam »…) ou la valeur ; comparaison sans casse ni
     * accents. Null pour un libellé inconnu (traité comme « Autre » par le score).
     */
    public static function fromStored(?string $stored): ?self
    {
        $needle = self::normalize((string) $stored);

        if ($needle === '') {
            return null;
        }

        foreach (self::cases() as $reason) {
            if ($needle === self::normalize($reason->value) || $needle === self::normalize($reason->label())) {
                return $reason;
            }
        }

        return null;
    }

    private static function normalize(string $text): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($text))));
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

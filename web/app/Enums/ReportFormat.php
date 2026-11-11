<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Format de sortie d'un rapport (DATABASE_DICTIONARY.md §7 `generated_reports.format`). `apercu` = rendu
 * à l'écran sans fichier.
 */
enum ReportFormat: string
{
    case Pdf = 'pdf';
    case Xlsx = 'xlsx';
    case Docx = 'docx';
    case Preview = 'apercu';

    public function label(): string
    {
        return (string) __('enums/steering.report_format.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pdf => 'danger',
            self::Xlsx => 'success',
            self::Docx => 'primary',
            self::Preview => 'secondary',
        };
    }

    /** Un fichier est produit et stocké (`file_path`), contrairement à l'aperçu. */
    public function producesFile(): bool
    {
        return $this !== self::Preview;
    }

    public function extension(): ?string
    {
        return $this->producesFile() ? $this->value : null;
    }

    public function mimeType(): ?string
    {
        return match ($this) {
            self::Pdf => 'application/pdf',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Docx => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            self::Preview => null,
        };
    }

    /**
     * Formats téléchargeables (cases à cocher de la planification).
     *
     * @return list<self>
     */
    public static function downloadable(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $format): bool => $format->producesFile()));
    }
}

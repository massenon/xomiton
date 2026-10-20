<?php

declare(strict_types=1);

namespace App\Application\Steering\Reports;

use App\Support\SmallCellMask;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Excel par `openspout/openspout` (ADR-23) : écrit en flux, mémoire constante, quel que soit le nombre de lignes du
 * registre. Une feuille « Synthèse », une feuille par tableau, une feuille « Registre » ; une cellule masquée vaut
 * « données masquées ». Les chaînes sont écrites en cellules texte explicites (StringCell) : OpenSpout
 * (Cell::fromValue) transforme sinon toute chaîne commençant par « = » en formule, or le titre est une saisie libre.
 */
final class XlsxReportWriter implements ReportWriter
{
    private const SHEET_NAME_MAX = 31;

    public function write(string $path, ReportDataset $dataset, ?array $detailHead, iterable $detailRows): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $bold = new Style(fontBold: true);
        $masked = (string) __('steering.reports.masked');
        $used = [];

        $writer->getCurrentSheet()->setName($this->sheetName((string) __('steering.reports.summary'), $used));
        $writer->addRow($this->row([$dataset->title], $bold));
        $writer->addRow($this->row([(string) __('steering.reports.period'), $dataset->periodLabel]));
        $writer->addRow($this->row([(string) __('steering.reports.generated_at'), $dataset->generatedAt]));
        $writer->addRow($this->row([]));

        foreach ($dataset->kpis as $kpi) {
            $writer->addRow($this->row([$kpi['label'], $kpi['value'] ?? $masked]));
        }

        if ($dataset->hasMasked) {
            $writer->addRow($this->row([]));
            $writer->addRow($this->row([(string) __('steering.reports.masked_note', ['min' => SmallCellMask::MIN])]));
        }

        foreach ($dataset->tables as $table) {
            $writer->addNewSheetAndMakeItCurrent()->setName($this->sheetName($table['title'], $used));
            $writer->addRow($this->row($table['head'], $bold));

            foreach ($table['rows'] as $row) {
                $writer->addRow($this->row(array_map(static fn (mixed $cell): mixed => $cell ?? $masked, $row)));
            }
        }

        if ($detailHead !== null) {
            $writer->addNewSheetAndMakeItCurrent()->setName($this->sheetName((string) __('steering.reports.detail.sheet'), $used));
            $writer->addRow($this->row($detailHead, $bold));

            foreach ($detailRows as $row) {
                $writer->addRow($this->row($row));
            }
        }

        $writer->close();
    }

    /**
     * Ligne de cellules typées : une chaîne reste du texte (jamais une formule), un nombre reste un nombre.
     *
     * @param  array<array-key, mixed>  $values
     */
    private function row(array $values, ?Style $style = null): Row
    {
        $cells = [];

        foreach ($values as $value) {
            $cells[] = is_string($value) && $value !== ''
                ? new Cell\StringCell($value, $style)
                : Cell::fromValue(is_scalar($value) ? $value : null, $style);
        }

        return new Row($cells);
    }

    /**
     * Nom de feuille valide : 31 caractères au plus, sans `\ / ? * [ ] :`, unique.
     *
     * @param  array<string, true>  $used
     */
    private function sheetName(string $title, array &$used): string
    {
        $name = trim((string) preg_replace('/[\\\\\/?*\[\]:]+/u', ' ', $title));
        $name = mb_substr($name === '' ? 'Feuille' : $name, 0, self::SHEET_NAME_MAX);
        $candidate = $name;
        $suffix = 2;

        while (isset($used[mb_strtolower($candidate)])) {
            $tail = ' '.$suffix++;
            $candidate = mb_substr($name, 0, self::SHEET_NAME_MAX - mb_strlen($tail)).$tail;
        }

        $used[mb_strtolower($candidate)] = true;

        return $candidate;
    }
}

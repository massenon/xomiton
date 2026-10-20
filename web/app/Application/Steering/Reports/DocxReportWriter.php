<?php

declare(strict_types=1);

namespace App\Application\Steering\Reports;

use App\Support\SmallCellMask;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;

/**
 * Word par `phpoffice/phpword` (ADR-23) : un document .docx avec les chiffres clés, un tableau par section et, si
 * demandé, le registre détaillé (plafonné comme le PDF : le document se construit en mémoire).
 */
final class DocxReportWriter implements ReportWriter
{
    public function write(string $path, ReportDataset $dataset, ?array $detailHead, iterable $detailRows): void
    {
        Settings::setOutputEscapingEnabled(true);
        $masked = (string) __('steering.reports.masked');
        $word = new PhpWord;
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(10);
        $word->addTitleStyle(1, ['size' => 20, 'bold' => true, 'color' => '0433C3']);
        $word->addTitleStyle(2, ['size' => 13, 'bold' => true, 'color' => '0433C3']);
        $section = $word->addSection(['marginLeft' => 900, 'marginRight' => 900]);
        $section->addTitle($dataset->title, 1);
        $section->addText((string) __('steering.reports.period').' : '.$dataset->periodLabel.' — '.(string) __('steering.reports.generated_at').' : '.$dataset->generatedAt);

        if ($dataset->hasMasked) {
            $section->addText((string) __('steering.reports.masked_note', ['min' => SmallCellMask::MIN]), ['italic' => true]);
        }

        foreach ($dataset->tables as $table) {
            $section->addTitle($table['title'], 2);
            $this->table($section, $table['head'], array_map(static fn (array $row): array => array_map(static fn (mixed $cell): string => (string) ($cell ?? $masked), $row), $table['rows']));
        }

        if ($detailHead !== null) {
            $rows = [];

            foreach ($detailRows as $row) {
                $rows[] = array_map(static fn (mixed $cell): string => (string) ($cell ?? $masked), $row);
            }

            $section->addTitle((string) __('steering.reports.detail.sheet'), 2);
            $this->table($section, $detailHead, $rows);
        }

        IOFactory::createWriter($word, 'Word2007')->save($path);
    }

    /**
     * @param  list<string>  $head
     * @param  list<list<string>>  $rows
     */
    private function table(\PhpOffice\PhpWord\Element\Section $section, array $head, array $rows): void
    {
        $table = $section->addTable(['borderSize' => 4, 'borderColor' => '999999', 'cellMargin' => 50]);
        $table->addRow();

        foreach ($head as $label) {
            $table->addCell(null, ['bgColor' => 'E8EDFB'])->addText($label, ['bold' => true]);
        }

        foreach ($rows as $row) {
            $table->addRow();

            foreach ($row as $cell) {
                $table->addCell()->addText($cell);
            }
        }
    }
}

<?php

declare(strict_types=1);

use App\Support\CsvExport;

/*
 * P10-02 / ADR-33 : export CSV d'administration — UTF-8 avec BOM, séparateur « ; », formules neutralisées, colonnes
 * sensibles (récit, GPS, contact, notes, commentaires) refusées hors export complet.
 */

function csvBody(array $columns, array $rows, bool $full = false): string
{
    $response = CsvExport::download('test.csv', $columns, $rows, $full);
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

it('écrit un CSV UTF-8 avec BOM, séparateur point-virgule et cellules sur une ligne', function (): void {
    $body = csvBody(['code' => 'Code', 'commune' => 'Commune'], [
        ['code' => 'AL-001', 'commune' => "Abomey-\nCalavi"],
        ['code' => 'AL-002', 'commune' => 'Porto-Novo; centre'],
    ]);

    expect($body)->toStartWith("\u{FEFF}Code;Commune\n")
        ->toContain("AL-001;\"Abomey- Calavi\"\n")
        ->toContain('AL-002;"Porto-Novo; centre"');
});

it('neutralise les formules (injection CSV) sans toucher aux nombres', function (): void {
    expect(CsvExport::cell('=HYPERLINK("x")'))->toBe('\'=HYPERLINK("x")')
        ->and(CsvExport::cell('+229 01 00'))->toBe("'+229 01 00")
        ->and(CsvExport::cell('@SUM(A1)'))->toBe("'@SUM(A1)")
        ->and(CsvExport::cell('-12'))->toBe('-12')
        ->and(CsvExport::cell(null))->toBe('')
        ->and(CsvExport::cell(true))->toBe('1');
});

it('refuse par défaut récit, GPS, contact, notes et commentaires (ADR-33)', function (string $column): void {
    expect(fn () => CsvExport::download('x.csv', ['code' => 'Code', $column => 'Sensible'], []))
        ->toThrow(LogicException::class, 'ADR-33');
})->with(['description', 'lat', 'lng', 'contact', 'notes', 'comment', 'alerts.description']);

it('refuse par défaut chaque colonne chiffrée de la configuration, nue ou qualifiée', function (): void {
    $columns = config('xomiton.encrypted_columns');
    expect($columns)->toBeArray()->not->toBeEmpty();

    foreach ($columns as $table => $list) {
        foreach ($list as $column) {
            foreach ([$column, $table.'.'.$column] as $key) {
                expect(fn () => CsvExport::guard([$key]))->toThrow(LogicException::class, 'ADR-33');
            }
        }
    }

    expect(CsvExport::excludedColumns())->toContain('orientation', 'gender_identity', 'trusted_contact_phone', 'ally_status', 'facts_author', 'address', 'online_url', 'access_token', 'refresh_token', 'gps', 'lat', 'lng', 'contact');
    CsvExport::guard(['code', 'commune', 'category', 'created_at']);
});

it('autorise un export complet explicite et nomme le fichier par écran et par date', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 19));

    expect(csvBody(['description' => 'Récit'], [['description' => 'texte']], full: true))->toContain('texte')
        ->and(CsvExport::filename('journal'))->toBe('xomiton-journal-20261019.csv');
});

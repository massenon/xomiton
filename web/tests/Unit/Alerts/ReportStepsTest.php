<?php

declare(strict_types=1);

use App\Application\Alerts\Support\ReportSteps;

/*
 * P5-04 : étapes du formulaire /signaler (Q11 a) — champ → étape, étape rouverte après un envoi refusé, résumé
 * d'erreurs groupé et cibles des champs sans élément propre (position, clé d'idempotence).
 */

it('situe chaque champ de StoreReportRequest dans son étape', function (string $field, int $step): void {
    expect(ReportSteps::stepOf($field))->toBe($step);
})->with([
    'type' => ['type', 1],
    'catégorie' => ['category', 1],
    'récit' => ['description', 2],
    'date' => ['occurred_on', 2],
    'besoin indexé' => ['needs.0', 3],
    'département' => ['zone_id', 3],
    'position' => ['accuracy', 3],
    'preuve indexée' => ['attachments.2', 4],
    'contact' => ['contact', 5],
    'canal' => ['preferred_channel', 5],
    'clé d’idempotence' => ['idempotency_key', 6],
    'champ inconnu' => ['inconnu', 6],
]);

it('rouvre la première étape en erreur, la première sans erreur', function (): void {
    expect(ReportSteps::total())->toBe(6)
        ->and(ReportSteps::keys())->toBe(['type', 'facts', 'needs', 'evidence', 'contact', 'review'])
        ->and(ReportSteps::firstWithErrors([]))->toBe(1)
        ->and(ReportSteps::firstWithErrors(['contact', 'preferred_channel']))->toBe(5)
        ->and(ReportSteps::firstWithErrors(['contact', 'needs.1', 'description']))->toBe(2)
        ->and(ReportSteps::firstWithErrors(['needs']))->toBe(3);
});

it('groupe le résumé par champ de base et vise #f-position et #f-send pour les champs sans élément', function (): void {
    expect(ReportSteps::summary([
        'attachments.0' => ['a'],
        'attachments.1' => ['b'],
        'lat' => ['c'],
        'accuracy' => ['d'],
        'idempotency_key' => ['e'],
        'description' => ['f'],
    ]))->toBe([
        'attachments' => ['a', 'b'],
        'position' => ['c', 'd'],
        'send' => ['e'],
        'description' => ['f'],
    ]);
});

it('transmet au composant Alpine les étapes, leurs libellés et leurs champs', function (): void {
    $client = ReportSteps::forClient();

    expect($client)->toHaveCount(6)
        ->and($client[2])->toBe(['key' => 'needs', 'label' => __('alerts.report.steps.needs'), 'fields' => ReportSteps::FIELDS['needs']])
        ->and(ReportSteps::labels()[0])->toBe(__('alerts.report.steps.type'));
});

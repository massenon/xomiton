<?php

declare(strict_types=1);

use App\Application\Alerts\RouteAlert;
use App\Enums\DirectoryRole;
use App\Models\DirectoryContact;
use App\Models\OnCallShift;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Tests\Support\PrototypeDirectory;

/*
 * P5-01 : RouteAlert rejoue la table de vérité RG-04 (dataset partagé avec le test unitaire) sur un annuaire en base :
 * contacts inactifs et supprimés exclus, garde `focal` (OnCallRoster) au niveau 2, point focal national au niveau 3.
 */

beforeEach(function (): void {
    $this->seed(ReferenceDataSeeder::class);
});

it('route comme la table de vérité avec l’annuaire en base', function (array $needs, ?string $zone, array $directory, ?int $expected, ?string $fallback): void {
    foreach ($directory as $row) {
        $options = $row[3] ?? [];
        $contact = DirectoryContact::factory()->create([
            'id' => $row[0],
            // Noms dans l'ordre inverse des identifiants : un tri par nom (ordered()) ferait échouer R21 / R22.
            'name' => sprintf('Contact %03d', 999 - $row[0]),
            'role' => DirectoryRole::from($row[1]),
            'zone_id' => $row[2] === null ? null : PrototypeDirectory::zone($row[2])->id,
            'is_active' => ! in_array('inactive', $options, true),
            'is_national_focal' => in_array('national', $options, true),
            'user_id' => in_array('on_duty', $options, true) ? User::factory()->create()->id : null,
        ]);

        if (in_array('deleted', $options, true)) {
            $contact->delete();
        }

        if (in_array('on_duty', $options, true)) {
            OnCallShift::factory()->forUser($contact->user)->focal(PrototypeDirectory::zone((string) $row[2]))
                ->between(now()->subHour(), now()->addHour())->create();
        }
    }

    $decision = app(RouteAlert::class)($zone === null ? null : PrototypeDirectory::zone($zone)->id, $needs);

    expect($decision->contactId)->toBe($expected)
        ->and($decision->fallback?->value)->toBe($fallback);
})->with('route_alert_cases');

it('explique le routage pour l’aperçu de l’administration', function (): void {
    $littoral = PrototypeDirectory::zone('Littoral');
    $focal = DirectoryContact::factory()->focalZone($littoral)->create();

    $explanation = app(RouteAlert::class)->explain($littoral->id, ['hebergement']);

    expect($explanation->decision->contactId)->toBe($focal->id)
        ->and($explanation->decision->isFallback())->toBeTrue()
        ->and($explanation->retainedLevel()['level'])->toBe(2);
});

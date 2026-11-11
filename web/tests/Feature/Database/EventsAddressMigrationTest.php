<?php

declare(strict_types=1);

use App\Models\Event;
use Illuminate\Support\Facades\DB;

/*
 * Migrations du lot evenements (P8-04) : `2026_10_30_060000` coupe un lieu du prototype « Ville – lieu précis » en
 * `place` (ville) + `address` (chiffrée) et le recolle au retour arrière ; `2026_10_30_060100` chiffre `online_url`
 * et le déchiffre au retour arrière.
 */

function eventsMigration(string $name): object
{
    return require database_path('migrations/'.$name.'.php');
}

it('coupe « Ville – lieu » en ville + adresse chiffrée, et recolle l’adresse dans le lieu au retour arrière', function (): void {
    $split = Event::factory()->withAddress('Zogbo, rue 12')->create(['place' => 'Cotonou']);
    $onlyAddress = Event::factory()->withAddress('Maison des jeunes')->create(['place' => null]);
    $plain = Event::factory()->create(['place' => 'Parakou']);
    $online = eventsMigration('2026_10_30_060100_encrypt_online_url_on_events_table');
    $address = eventsMigration('2026_10_30_060000_add_address_and_is_public_to_events_table');

    $online->down();
    $address->down();

    expect(DB::table('events')->where('id', $split->id)->value('place'))->toBe('Cotonou – Zogbo, rue 12')
        ->and(DB::table('events')->where('id', $onlyAddress->id)->value('place'))->toBe('Maison des jeunes')
        ->and(DB::table('events')->where('id', $plain->id)->value('place'))->toBe('Parakou');

    $address->up();
    $online->up();

    expect(Event::query()->find($split->id))->place->toBe('Cotonou')->address->toBe('Zogbo, rue 12')
        ->and((string) DB::table('events')->where('id', $split->id)->value('address'))->not->toContain('Zogbo')
        ->and(Event::query()->find($plain->id))->place->toBe('Parakou')->address->toBeNull()
        // Sans « – », rien n'est coupé : le lieu reste tel quel.
        ->and(Event::query()->find($onlyAddress->id))->place->toBe('Maison des jeunes')->address->toBeNull();
});

it('chiffre les liens de connexion existants et les déchiffre au retour arrière', function (): void {
    $event = Event::factory()->create(['is_online' => true, 'online_url' => 'https://visio.example.org/atelier?code=42']);
    $online = eventsMigration('2026_10_30_060100_encrypt_online_url_on_events_table');

    $online->down();
    expect(DB::table('events')->where('id', $event->id)->value('online_url'))->toBe('https://visio.example.org/atelier?code=42');

    $online->up();
    expect((string) DB::table('events')->where('id', $event->id)->value('online_url'))->not->toContain('visio.example.org')
        ->and(Event::query()->find($event->id)->online_url)->toBe('https://visio.example.org/atelier?code=42');
});

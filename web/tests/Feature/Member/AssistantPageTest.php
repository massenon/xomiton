<?php

declare(strict_types=1);

use App\Application\Alerts\Queries\ListEmergencyContacts;
use App\Http\Resources\AssistantAnswerResource;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AlertFixtures;

/*
 * Page `/assistant` (P8-11, prototype assistant.php) : page Inertia `Assistant/Index` (plus l'écran provisoire) avec les
 * puces du prototype, de quoi répondre à une crise sans appel réseau (mots-clés normalisés, texte et liens de crise,
 * numéros de la même source que /urgences) et « Parler à un humain ».
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->member = AlertFixtures::member();
});

it('sert /assistant en page Assistant/Index avec les puces, les garde-fous de crise et l’escalade', function (): void {
    $this->actingAs($this->member)->get('/assistant')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Assistant/Index')
            ->where('title', 'Assistant')
            ->where('chips', fn ($chips): bool => count($chips) === 8 && $chips[0] === "Comment porter plainte\u{00A0}?")
            ->where('max_length', 500)
            // Mots-clés sous forme comparable (sans accent ni casse), sans doublon.
            ->where('crisis.keywords', fn ($keywords): bool => in_array('sequestree', $keywords->all(), true)
                && in_array('il va me tuer', $keywords->all(), true)
                && count($keywords) === count(array_unique($keywords->all())))
            ->where('crisis.text', fn (string $text): bool => str_contains($text, "Police secours\u{00A0}: 117")
                && str_contains($text, __('assistant.crisis.human'))
                && ! str_contains($text, route('sos')))
            ->where('crisis.links', AssistantAnswerResource::crisisLinks())
            ->where('offline.call.url', 'tel:117')
            ->where('escalation.channels.whatsapp', 'WhatsApp')
            ->where('escalation.message_max', 1000)
            ->where('links.sos', route('sos'))
            ->where('links.annuaire', route('annuaire'))
            ->missing('prototype'));
});

it('prend les numéros d’urgence à la même source que /urgences', function (): void {
    Settings::set('emergency_numbers', [['label' => 'Ligne verte', 'number' => '138']]);

    $services = app(ListEmergencyContacts::class)()->services;

    $this->actingAs($this->member)->get('/assistant')
        ->assertInertia(fn (Assert $page) => $page
            ->where('crisis.text', fn (string $text): bool => str_contains($text, "Ligne verte\u{00A0}: 138") && ! str_contains($text, '117'))
            ->where('offline.call.url', 'tel:138'));

    expect(collect($services)->pluck('number')->all())->toBe(['138']);
});

it('exige une session', function (): void {
    $this->get('/assistant')->assertRedirect();
});

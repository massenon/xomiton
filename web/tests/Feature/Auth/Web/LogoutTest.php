<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;

/* Lot ecrans-web : POST /deconnexion. */

it('déconnecte, invalide la session et journalise', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/deconnexion')
        ->assertRedirect(route('home'))
        ->assertSessionHas('toast', 'Tu es déconnecté·e. À bientôt.');

    $this->assertGuest();
    expect(AuditLog::query()->ofAction('auth.logout')->forSubject($user)->exists())->toBeTrue();
});

it('refuse la déconnexion à une personne non connectée', function (): void {
    $this->post('/deconnexion')->assertRedirect(route('connexion'));
    $this->postJson('/deconnexion')->assertUnauthorized();
});

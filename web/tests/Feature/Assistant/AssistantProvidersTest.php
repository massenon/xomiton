<?php

declare(strict_types=1);

use App\Application\Assistant\AssistantProvider;
use App\Infrastructure\Assistant\AssistantProviders;
use App\Infrastructure\Assistant\KnowledgeBaseProvider;
use Illuminate\Support\Facades\Log;

/*
 * Fournisseur de l'assistant (P8-11, Q7 non tranchée) : `kb` par défaut et seul pilote livré ; vide, `null` ou nom
 * inconnu → `kb` (un nom inconnu avec avertissement), jamais d'erreur de configuration visible.
 */

it('résout le pilote kb par défaut, pour une valeur vide ou « null »', function (mixed $value): void {
    config(['xomiton.assistant.provider' => $value]);
    $this->app->forgetInstance(AssistantProvider::class);

    expect(AssistantProviders::effective($value))->toBe('kb')
        ->and(app(AssistantProvider::class))->toBeInstanceOf(KnowledgeBaseProvider::class)
        ->and(app(AssistantProvider::class)->name())->toBe('kb');
})->with([
    'défaut' => ['kb'],
    'majuscules' => [' KB '],
    'absent' => [null],
    'vide' => [''],
    'chaîne null' => ['null'],
]);

it('retombe sur kb avec un avertissement pour un pilote inconnu', function (): void {
    Log::spy();
    config(['xomiton.assistant.provider' => 'openai']);
    $this->app->forgetInstance(AssistantProvider::class);

    expect(app(AssistantProvider::class))->toBeInstanceOf(KnowledgeBaseProvider::class);

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message): bool => str_contains($message, '« openai »'));
});

it('livre kb et gemini, ce dernier désactivé et sans clé par défaut', function (): void {
    expect(array_keys(AssistantProviders::DRIVERS))->toBe(['kb', 'gemini'])
        ->and(config('xomiton.assistant.llm_enabled'))->toBeFalse()
        ->and(config('xomiton.assistant.gemini.api_key'))->toBeNull();
});

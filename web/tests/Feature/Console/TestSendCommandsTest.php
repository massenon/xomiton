<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

/* Commandes de test d'envoi : confirmation exigée, aucune donnée réelle, pilotes simulés (Http::fake). */

beforeEach(fn () => Http::preventStrayRequests());

it('notifications:test-sms annule sans confirmation et envoie avec --yes', function (): void {
    config(['xomiton.sms.driver' => 'vonage', 'xomiton.sms.vonage.api_key' => 'k', 'xomiton.sms.vonage.api_secret' => 's']);
    Http::fake(['rest.nexmo.com/*' => Http::response(['messages' => [['status' => '0', 'message-id' => '1']]])]);

    $this->artisan('notifications:test-sms', ['to' => '+22997388847'])->expectsConfirmation('Envoyer un SMS de test (facturé par le fournisseur) ?', 'no')->assertFailed();
    Http::assertNothingSent();

    $this->artisan('notifications:test-sms', ['to' => '+22997388847', '--yes' => true])->assertSuccessful();
    Http::assertSentCount(1);
});

it('notifications:test-sms refuse un numéro invalide', function (): void {
    $this->artisan('notifications:test-sms', ['to' => 'abc', '--yes' => true])->assertExitCode(2);
});

it('notifications:test-push utilise le pilote log par défaut sans appel réseau', function (): void {
    $this->artisan('notifications:test-push', ['token' => 'device-token', '--yes' => true])->assertSuccessful();
    Http::assertNothingSent();
});

it('assistant:test-llm ne fait aucun appel tant que le drapeau est faux', function (): void {
    config(['xomiton.assistant.llm_enabled' => false, 'xomiton.assistant.gemini.api_key' => 'k']);

    $this->artisan('assistant:test-llm', ['--yes' => true])->assertFailed();
    Http::assertNothingSent();
});

it('assistant:test-llm appelle Gemini simulé quand il est activé', function (): void {
    config(['xomiton.assistant.llm_enabled' => true, 'xomiton.assistant.gemini.api_key' => 'k']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'Une appli d’entraide.']]]]]])]);

    $this->artisan('assistant:test-llm', ['--yes' => true])->assertSuccessful();
    Http::assertSentCount(1);
});

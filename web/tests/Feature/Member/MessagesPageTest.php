<?php

declare(strict_types=1);

use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\MessagingFixtures as M;

/*
 * Page `/messages` (P8-01, prototype messaging.php) : page Inertia `Member/Messages` (plus l'écran provisoire), première
 * page de la liste sous la forme de l'API, réglages du sondage court (4 s conversation ouverte, DECISIONS.md ; liste
 * 15 s), `?c=` et `?with=` validés, aucune promesse de chiffrement « de bout en bout » (Q32 b).
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->awa = M::member();
    $this->bea = M::member();
});

it('sert /messages en page Member/Messages avec la liste, le sondage court et les motifs de signalement', function (): void {
    $conversation = M::privateConversation($this->awa, $this->bea);
    M::send($this->bea, $conversation, 'Coucou !')->assertCreated();

    $this->actingAs($this->awa)->get('/messages?c='.$conversation->ulid.'&with='.$this->bea->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Member/Messages')
            ->where('title', 'Messages')
            ->where('selected', $conversation->ulid)
            ->where('with', null) // conversation déjà ouverte : sélectionnée, rien à confirmer
            ->where('withUnavailable', false)
            ->missing('conversations.0.participants')
            ->where('config.list_poll_idle_seconds', 30)
            ->where('conversations.0.id', $conversation->ulid)
            ->where('conversations.0.unread_count', 1)
            ->where('conversations.0.last_message.preview', 'Coucou !')
            ->where('conversationsMeta.current_page', 1)
            ->where('config.poll_seconds', 4)
            ->where('config.list_poll_seconds', 15)
            ->where('config.body_max_length', 1000)
            ->where('config.report_reasons.0.value', 'harcelement')
            ->missing('prototype'));
});

it('ignore un ?c= mal formé et un ?with= vers soi-même', function (): void {
    $this->actingAs($this->awa)->get('/messages?c=../../etc&with='.$this->awa->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Member/Messages')->where('selected', null)->where('with', null));
});

it('exige une session', function (): void {
    $this->get('/messages')->assertRedirect();
});

it('ne promet jamais un chiffrement « de bout en bout » ni une lecture réservée aux participant·es (Q32 b)', function (): void {
    $texts = mb_strtolower((string) json_encode(require lang_path('fr/community.php'), JSON_UNESCAPED_UNICODE));
    $catalog = mb_strtolower((string) file_get_contents(resource_path('js/lang/fr.json')));

    // Chiffrement au repos (clé serveur) et lecture du message signalé par la modération (P12-06) : aucune promesse
    // que seules les personnes de la conversation peuvent lire.
    foreach (['bout en bout', 'end-to-end', 'e2e', 'seules les personnes de la conversation', 'personne d’autre ne peut', 'personne ne peut lire'] as $promise) {
        expect($texts)->not->toContain($promise)->and($catalog)->not->toContain($promise);
    }

    expect(__('community.messages.encrypted_title'))->toBe('Messages chiffrés sur nos serveurs')
        ->and(__('community.messages.intro'))->toContain('en cas de signalement');
});

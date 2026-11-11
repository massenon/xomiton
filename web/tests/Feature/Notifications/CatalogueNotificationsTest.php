<?php

declare(strict_types=1);

use App\Application\Auth\Notifications\PeerAccountValidated;
use App\Models\User;
use App\Notifications\AlertRouted;
use App\Notifications\ContentReported;
use App\Notifications\EventCreatedInMyGroup;
use App\Notifications\EventReminder;
use App\Notifications\ForumReplyPosted;
use App\Notifications\GroupJoinAccepted;
use App\Notifications\GroupJoinRequested;
use App\Notifications\InvitationAccepted;
use App\Notifications\InvitationReceived;
use App\Notifications\NewMessage;
use App\Notifications\PollEnded;
use App\Notifications\ReactionGrouped;
use App\Notifications\SosTriggered;
use App\Notifications\SosUnacknowledged;
use App\Notifications\SyncConflict;
use App\Notifications\UserRegistered;
use App\Notifications\XomitonNotification;

/*
 * P3-02 : chaque classe du catalogue (NOTIFICATIONS §2 + compléments) déclare sa catégorie, son caractère sensible et un
 * contenu complet (titre, corps, lien, icône) ; les sensibles passent au texte neutre en mode discret.
 */

/**
 * @return array<string, array{0: XomitonNotification, 1: string, 2: bool, 3: string}>
 */
function catalogue(): array
{
    return [
        'UserRegistered' => [new UserRegistered, 'securite', false, 'Bienvenue sur Xomiton'],
        'PeerAccountValidated' => [new PeerAccountValidated(3), 'securite', false, 'Ton compte pair est validé'],
        'AlertRouted' => [new AlertRouted(1, 'REF', 'Signalement', 'Littoral', '2026-10-18 15:00:00'), 'alertes', true, 'Nouvelle alerte à prendre en charge'],
        'SosTriggered' => [new SosTriggered(1, 'CODE', 'Cotonou', 'Littoral', 'Awa_S'), 'sos', true, 'Alerte SOS'],
        'SosUnacknowledged' => [new SosUnacknowledged(1, 'CODE', 5, 'Littoral'), 'sos', true, 'SOS sans réponse depuis 5 min'],
        'NewMessage' => [new NewMessage(1, 2, 'Estelle', 'Coucou', 'Groupe soutien'), 'messages', true, 'Nouveau message'],
        'ReactionGrouped' => [new ReactionGrouped(4, ReactionGrouped::KIND_LIKE, 'Estelle', 4), 'communaute', false, 'Réactions à ta publication'],
        'EventReminder' => [new EventReminder(6, 'Atelier', '15 h', 'Cotonou', 'demain à 15 h'), 'evenements', false, 'Rappel : Atelier'],
        'PollEnded' => [new PollEnded(2, 9, 'Quel sujet ?', 12), 'communaute', false, 'Sondage terminé'],
        'ContentReported' => [new ContentReported(8, 'Publication', 'Insultes'), 'moderation', true, 'Contenu signalé'],
        'SyncConflict' => [new SyncConflict(5, 'CaseRecord #12', 'status'), 'systeme', false, 'Conflit de synchronisation'],
        'InvitationReceived' => [new InvitationReceived(1, 'Mariam A.'), 'communaute', false, 'Nouvelle invitation'],
        'InvitationAccepted' => [new InvitationAccepted(1, 2, 'Mariam A.'), 'communaute', false, 'Invitation acceptée'],
        'ForumReplyPosted' => [new ForumReplyPosted(1, 2, 'Nadia', 'Dire non'), 'communaute', false, 'Nouvelle réponse'],
        'GroupJoinRequested' => [new GroupJoinRequested(1, 2, 'Nadia', 'Cercle Cotonou'), 'communaute', true, 'Demande d’adhésion'],
        'GroupJoinAccepted' => [new GroupJoinAccepted(1, 'Cercle Cotonou'), 'communaute', true, 'Bienvenue dans le groupe'],
        'EventCreatedInMyGroup' => [new EventCreatedInMyGroup(6, 'Atelier', '18 octobre', '15 h', 'Cercle Cotonou'), 'communaute', false, 'Nouvel événement'],
    ];
}

it('déclare la catégorie, le caractère sensible et un contenu complet pour chaque classe du catalogue', function (): void {
    $user = User::factory()->withProfile()->create();

    foreach (catalogue() as $name => [$notification, $category, $sensitive, $title]) {
        $data = $notification->toArray($user);

        expect($notification)->toBeInstanceOf(XomitonNotification::class)
            ->and($notification->category())->toBe($category, $name)
            ->and($notification->isSensitive())->toBe($sensitive, $name)
            ->and($data['title'])->toBe($title, $name)
            ->and($data['body'])->not->toBe('', $name)
            ->and($data['url'])->toStartWith('http', $name)
            ->and($data['icon'])->not->toBe('', $name)
            ->and($data['category'])->toBe($category, $name)
            ->and($data['sensitive'])->toBe($sensitive, $name)
            ->and($notification->toPush($user)->data['category'])->toBe($category, $name);
    }
});

it('passe au texte neutre en mode discret pour les sensibles seulement, en gardant lien et identifiants', function (): void {
    $discreet = User::factory()->withProfile([], ['mask_sensitive_notifications' => true])->create();

    foreach (catalogue() as $name => [$notification, $category, $sensitive, $title]) {
        $data = $notification->toArray($discreet);

        if ($sensitive) {
            expect($notification->shouldMask($discreet))->toBeTrue($name)
                ->and($data['title'])->toBe('Xomiton', $name)
                ->and($data['body'])->not->toBe($notification->toArray(User::factory()->make())['body'], $name)
                ->and($data['url'])->toStartWith('http', $name);
        } else {
            expect($notification->shouldMask($discreet))->toBeFalse($name)
                ->and($data['title'])->toBe($title, $name);
        }
    }

    $sos = (new SosTriggered(1, 'CODE', 'Cotonou', 'Littoral'))->toArray($discreet);
    expect($sos['body'])->toBe('Alerte urgente')->and($sos['alert_id'])->toBe(1)->and($sos['code'])->toBe('CODE');
});

it('formule le SOS selon la position, le pseudo et le SMS de secours', function (): void {
    $user = User::factory()->withProfile()->create();

    expect((new SosTriggered(1, 'CODE', 'Cotonou', 'Littoral'))->toArray($user)['body'])->toContain('près de Cotonou')
        ->and((new SosTriggered(1, 'CODE', 'Cotonou', 'Littoral', 'Awa_S'))->toArray($user)['body'])->toStartWith('Awa_S a besoin d’aide')
        ->and((new SosTriggered(1, 'CODE', null, 'Littoral'))->toArray($user)['body'])->toContain('position indisponible, zone : Littoral')
        ->and((new SosTriggered(1, 'CODE', null, 'Littoral'))->toSms($user))->toContain('zone Littoral')
        ->and((new SosTriggered(1, 'CODE', 'Cotonou', 'Littoral'))->toSms($user))->toStartWith('Xomiton - Alerte SOS CODE près de Cotonou')
        ->and(mb_strlen((string) (new SosUnacknowledged(1, 'CODE', 5))->toSms($user)))->toBeLessThanOrEqual(160)
        ->and((new SosUnacknowledged(1, 'CODE', 5, 'Zou'))->toMail($user)->variables)->toMatchArray(['alert_reference' => 'CODE', 'alert_zone' => 'Zou']);
});

it('regroupe les réactions et refuse un type inconnu', function (): void {
    $user = User::factory()->withProfile()->create();

    expect((new ReactionGrouped(1, ReactionGrouped::KIND_LIKE, 'Estelle'))->toArray($user)['body'])->toBe('Estelle a aimé ta publication.')
        ->and((new ReactionGrouped(1, ReactionGrouped::KIND_LIKE, 'Estelle', 4))->toArray($user)['body'])->toBe('Estelle et 4 autres ont aimé ta publication.')
        ->and((new ReactionGrouped(1, ReactionGrouped::KIND_COMMENT, 'Estelle', 2))->toArray($user)['body'])->toBe('Estelle et 2 autres ont commenté ta publication.')
        ->and((new ReactionGrouped(1, ReactionGrouped::KIND_REPLY, 'Estelle'))->toArray($user)['body'])->toBe('Estelle a répondu à ton commentaire.')
        ->and((new ReactionGrouped(1, ReactionGrouped::KIND_LIKE, 'Estelle'))->via($user))->toBe(['database'])
        ->and(fn () => new ReactionGrouped(1, 'wave', 'Estelle'))->toThrow(InvalidArgumentException::class);
});

it('déclare un template e-mail là où le catalogue en prévoit un, et nulle part ailleurs', function (): void {
    $templates = [];

    foreach (catalogue() as $name => [$notification]) {
        $templates[$name] = $notification->mailTemplate();
    }

    expect($templates)->toBe([
        'UserRegistered' => 'bienvenue',
        'PeerAccountValidated' => 'compte-valide',
        'AlertRouted' => 'alerte-routee',
        'SosTriggered' => null,
        'SosUnacknowledged' => 'alerte-routee',
        'NewMessage' => null,
        'ReactionGrouped' => null,
        'EventReminder' => 'rappel-evenement',
        'PollEnded' => null,
        'ContentReported' => null,
        'SyncConflict' => null,
        'InvitationReceived' => null,
        'InvitationAccepted' => null,
        'ForumReplyPosted' => null,
        'GroupJoinRequested' => null,
        'GroupJoinAccepted' => null,
        'EventCreatedInMyGroup' => null,
    ]);
});

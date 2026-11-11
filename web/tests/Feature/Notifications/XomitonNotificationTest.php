<?php

declare(strict_types=1);

use App\Application\Auth\Notifications\PeerAccountValidated;
use App\Mail\TemplateMail;
use App\Models\MailLog;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\AlertRouted;
use App\Notifications\InvitationReceived;
use App\Notifications\NewMessage;
use App\Notifications\SosTriggered;
use App\Notifications\UserRegistered;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/*
 * P3-02 : socle App\Notifications\XomitonNotification — canaux calculés depuis user_settings.notification_prefs et les
 * abonnements push, catégories sécurité / SOS verrouillées, texte neutre en mode discret, e-mail par template en base,
 * charge push sans donnée sensible, colonnes category / is_sensitive remplies à l'insertion.
 */

beforeEach(function (): void {
    $this->seed([EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);
});

function memberWithPrefs(array $prefs = [], array $settings = []): User
{
    return User::factory()->withProfile([], ['notification_prefs' => $prefs] + $settings)->create(['phone' => '+229 01 97 38 88 47']);
}

it('calcule les canaux depuis les préférences : cloche, e-mail par template, puis Web Push et FCM selon les abonnements', function (): void {
    $user = memberWithPrefs();
    $notification = new AlertRouted(1, 'REF-1', 'Signalement', 'Littoral', now());

    expect($notification->via($user))->toBe(['database', 'mail']);

    PushSubscription::factory()->for($user)->create();
    expect($notification->via($user))->toBe(['database', 'mail', 'webpush']);

    PushSubscription::factory()->for($user)->fcm()->create();
    expect($notification->via($user))->toBe(['database', 'mail', 'webpush', 'fcm']);

    // Pas de template dans le catalogue → jamais de canal mail, même si la préférence e-mail est active.
    expect((new InvitationReceived(1, 'Mariam A.'))->via($user))->toBe(['database', 'webpush', 'fcm']);
});

it('respecte une catégorie désactivée et ne sert que ce que la personne a gardé', function (): void {
    $user = memberWithPrefs(['communaute' => ['in_app' => true, 'email' => false, 'push' => false]]);
    PushSubscription::factory()->for($user)->create();

    expect((new InvitationReceived(1, 'Mariam A.'))->via($user))->toBe(['database']);

    $user->settings->update(['notification_prefs' => ['communaute' => ['in_app' => false, 'email' => false, 'push' => false]]]);
    $user->refresh();

    expect((new InvitationReceived(1, 'Mariam A.'))->via($user))->toBe([]);
});

it('ne laisse jamais désactiver la sécurité du compte ni le SOS', function (): void {
    $user = memberWithPrefs([
        'securite' => ['in_app' => false, 'email' => false, 'push' => false],
        'sos' => ['in_app' => false, 'email' => false, 'push' => false],
    ]);

    expect((new PeerAccountValidated(1))->via($user))->toBe(['database', 'mail'])
        ->and((new PeerAccountValidated(1))->isMandatory())->toBeTrue()
        // SOS sans abonnement push : cloche + SMS de secours (téléphone du compte).
        ->and((new SosTriggered(1, 'ABCDEFGH12', 'Cotonou', 'Littoral'))->via($user))->toBe(['database', 'sms']);

    PushSubscription::factory()->for($user)->fcm()->create();
    // Avec un abonnement push, le SMS n'est plus un secours nécessaire.
    expect((new SosTriggered(1, 'ABCDEFGH12', 'Cotonou', 'Littoral'))->via($user->fresh()))->toBe(['database', 'fcm']);
});

it('écrit un texte neutre en base pour une notification sensible quand la personne masque le contenu sensible', function (): void {
    $discreet = memberWithPrefs([], ['mask_sensitive_notifications' => true]);
    $open = memberWithPrefs([], ['mask_sensitive_notifications' => false]);
    $message = new NewMessage(7, 42, 'Estelle', 'On se voit demain ?', null);

    $discreet->notify($message);
    $open->notify($message);

    $masked = $discreet->notifications()->first();
    $plain = $open->notifications()->first();

    expect($masked->data['title'])->toBe('Xomiton')
        ->and($masked->data['body'])->toBe('Nouveau message')
        ->and($masked->data['url'])->toBe(route('messages'))
        ->and($masked->data['conversation_id'])->toBe(7)
        ->and($masked->category)->toBe('messages')
        ->and($masked->is_sensitive)->toBeTrue()
        ->and(json_encode($masked->data))->not->toContain('On se voit demain')
        ->and($plain->data['body'])->toBe("Estelle\u{00A0}: On se voit demain ?")
        ->and($plain->data['title'])->toBe('Nouveau message');

    // Une notification non sensible garde son texte, mode discret ou pas.
    $this->travel(1)->seconds();
    $discreet->notify(new InvitationReceived(3, 'Mariam A.'));
    expect($discreet->notifications()->first()->data['body'])->toBe('Mariam A. t’a envoyé une invitation.');
});

it('remplit notifications.category et is_sensitive à l’insertion (canal database Xomiton)', function (): void {
    $user = memberWithPrefs();

    $user->notify(new AlertRouted(5, 'REF-5', 'Besoin', 'Ouémé', now()));

    $row = DB::table('notifications')->where('notifiable_id', $user->id)->first();

    expect($row->category)->toBe('alertes')
        ->and((bool) $row->is_sensitive)->toBeTrue()
        ->and($row->type)->toBe(AlertRouted::class)
        ->and(json_decode((string) $row->data, true)['icon'])->toBe('bell-fill');
});

it('envoie l’e-mail par le template du catalogue, jamais par un Mailable Markdown', function (): void {
    $user = memberWithPrefs();
    $user->forceFill(['name' => 'Awa Sossou'])->save();

    $user->notify(new AlertRouted(9, 'REF-9', 'Signalement', 'Littoral', now()));

    Mail::assertSent(TemplateMail::class, fn (TemplateMail $mail): bool => $mail->templateSlug === 'alerte-routee'
        && $mail->hasTo($user->email)
        && str_contains($mail->htmlBody, 'REF-9')
        && str_contains($mail->htmlBody, 'Littoral')
        && str_contains($mail->htmlBody, 'Bonjour Awa,'));

    expect(MailLog::query()->where('template_slug', 'alerte-routee')->value('context'))->toBe('alerte_routee');
});

it('construit une charge push sans donnée sensible : type, id, url, catégorie et texte neutre si masqué', function (): void {
    $user = memberWithPrefs([], ['mask_sensitive_notifications' => true]);
    $push = (new NewMessage(7, 42, 'Estelle', 'Secret partagé', null))->toPush($user);

    expect($push->title)->toBe('Xomiton')
        ->and($push->body)->toBe('Nouveau message')
        ->and(array_keys($push->data))->toBe(['type', 'id', 'url', 'category'])
        ->and($push->data['type'])->toBe('message')
        ->and($push->data['id'])->toBe(7)
        ->and($push->data['category'])->toBe('messages')
        ->and($push->toJson())->not->toContain('Secret');

    $open = memberWithPrefs();
    expect((new NewMessage(7, 42, 'Estelle', 'Bonjour', null))->toPush($open)->body)->toBe("Estelle\u{00A0}: Bonjour")
        ->and((new SosTriggered(1, 'CODE', 'Cotonou', 'Littoral'))->toPush($open)->isUrgent())->toBeTrue();
});

it('sert un destinataire sans compte par les seuls canaux qu’il route (SMS du contact de confiance, e-mail)', function (): void {
    $sms = Notification::route('sms', '+229 01 97 38 88 47');
    $mail = Notification::route('mail', 'contact@exemple.bj');

    expect((new SosTriggered(1, 'CODE', 'Cotonou', 'Littoral'))->via($sms))->toBe(['sms'])
        ->and((new AlertRouted(1, 'REF', 'Signalement', 'Littoral', now()))->via($mail))->toBe(['mail'])
        ->and((new AlertRouted(1, 'REF', 'Signalement', 'Littoral', now()))->via($sms))->toBe([]);
});

it('n’envoie que l’e-mail de bienvenue pour l’inscription (Q44), sans doublon ni cloche', function (): void {
    $user = memberWithPrefs();
    $notification = new UserRegistered;

    expect($notification->via($user))->toBe(['mail'])
        ->and($notification->toMail($user)->slug)->toBe('bienvenue')
        ->and($notification->toMail($user)->context)->toBe('inscription')
        ->and($notification->toMail($user)->variables['user_first_name'])->toBe($user->pseudo);

    $user->notify($notification);

    Mail::assertSent(TemplateMail::class, fn (TemplateMail $mail): bool => $mail->templateSlug === 'bienvenue' && $mail->hasTo($user->email));
    expect($user->notifications()->count())->toBe(0);
});

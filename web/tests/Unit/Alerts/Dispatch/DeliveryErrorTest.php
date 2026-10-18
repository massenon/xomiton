<?php

declare(strict_types=1);

use App\Application\Alerts\Dispatch\DeliveryError;

/*
 * P5-03 : `last_error` est affiché en administration et journalisé ; ni jeton push (chemin d'URL), ni adresse e-mail,
 * ni numéro de téléphone n'y figurent.
 */

it('réduit les URL à leur hôte et masque adresses e-mail et numéros', function (string $raw, string $masked): void {
    expect(DeliveryError::mask($raw))->toBe($masked);
})->with([
    'jeton FCM' => ['cURL error 28: timed out for https://fcm.googleapis.com/fcm/send/eKp9:APA91bH-abc?x=1', 'cURL error 28: timed out for https://fcm.googleapis.com/[masqué]'],
    'identifiants dans l’URL' => ['POST https://user:secret@sms.example.com:8443 refused', 'POST https://sms.example.com refused'],
    'SMTP' => ['550 5.1.1 <awa.s@exemple.bj>: Recipient address rejected', '550 5.1.1 <[masqué]>: Recipient address rejected'],
    'numéro' => ['Numéro +229 01 97 38 88 47 invalide', 'Numéro [masqué] invalide'],
    'neutre' => ['HTTP 500', 'HTTP 500'],
]);

it('préfixe la classe de l’exception et borne à 255 caractères', function (): void {
    $message = DeliveryError::fromThrowable(new RuntimeException('panne vers ops@xomiton.bj '.str_repeat('x', 300)));

    expect($message)->toStartWith('RuntimeException : panne vers [masqué]')
        ->and(mb_strlen($message))->toBe(255);
});

<?php

declare(strict_types=1);

use App\Application\Auth\OtpService;
use App\Domain\Auth\Exceptions\OtpInvalid;
use App\Domain\Auth\Exceptions\OtpRateLimited;
use App\Domain\Auth\Exceptions\OtpTooSoon;
use App\Enums\OtpPurpose;
use App\Mail\TemplateMail;
use App\Models\MailLog;
use App\Models\OtpCode;
use App\Models\User;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/*
 * P2-04 : codes à 6 chiffres hachés, 10 min, 5 essais, renvoi après 30 s, 5 envois par heure (Carbon::setTestNow).
 */

beforeEach(function (): void {
    $this->seed([EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);
    Carbon::setTestNow('2026-10-17 10:00:00');
    $this->service = new OtpService;
});

afterEach(fn () => Carbon::setTestNow());

/** Extrait le code à 6 chiffres du dernier e-mail code-verification envoyé. */
function sentOtpCode(): string
{
    $code = null;
    Mail::assertSent(TemplateMail::class, function (TemplateMail $mail) use (&$code): bool {
        if (preg_match('/>(\d{6})<\/h1>/', $mail->htmlBody, $m) === 1) {
            $code = $m[1];
        }

        return true;
    });

    return (string) $code;
}

it('émet un code haché à 6 chiffres, l’envoie par e-mail et le vérifie une seule fois', function (): void {
    $user = User::factory()->create(['email' => 'awa@exemple.bj', 'name' => 'Awa Sossou']);

    $record = $this->service->issue('Awa@Exemple.bj', OtpPurpose::Login, '41.79.1.1');
    $code = sentOtpCode();

    expect($code)->toMatch('/^\d{6}$/')
        ->and($record->email)->toBe('awa@exemple.bj')
        ->and($record->purpose)->toBe(OtpPurpose::Login)
        ->and($record->code_hash)->toBe(hash('sha256', $code))
        ->and($record->ip)->toBe('41.79.1.1')
        ->and($record->expires_at->toDateTimeString())->toBe('2026-10-17 10:10:00')
        ->and(MailLog::query()->forTemplate('code-verification')->where('context', 'otp:login')->count())->toBe(1);

    Mail::assertSent(TemplateMail::class, fn (TemplateMail $mail): bool => $mail->hasTo($user->email)
        && str_contains($mail->htmlBody, 'Bonjour Awa,')
        && str_contains($mail->htmlBody, 'valable 10 minutes'));

    expect($this->service->verify('awa@exemple.bj', OtpPurpose::Login, $code))->toBeTrue()
        ->and($record->fresh()?->isConsumed())->toBeTrue()
        ->and($this->service->verify('awa@exemple.bj', OtpPurpose::Login, $code))->toBeFalse();
});

it('refuse un code expiré et un code d’un autre usage', function (): void {
    OtpCode::factory()->withCode('123456')->create(['email' => 'awa@exemple.bj', 'purpose' => OtpPurpose::Signup]);

    expect($this->service->verify('awa@exemple.bj', OtpPurpose::Login, '123456'))->toBeFalse();

    Carbon::setTestNow('2026-10-17 10:10:01');

    expect($this->service->verify('awa@exemple.bj', OtpPurpose::Signup, '123456'))->toBeFalse()
        ->and($this->service->currentCode('awa@exemple.bj', OtpPurpose::Signup))->toBeNull();
});

it('compte les essais et annule le code au cinquième échec', function (): void {
    $record = OtpCode::factory()->withCode('123456')->create(['email' => 'awa@exemple.bj', 'purpose' => OtpPurpose::Reset]);

    foreach (range(1, 4) as $attempt) {
        expect($this->service->verify('awa@exemple.bj', OtpPurpose::Reset, '000000'))->toBeFalse()
            ->and($record->fresh()?->attempts)->toBe($attempt);
    }

    expect(fn () => $this->service->verify('awa@exemple.bj', OtpPurpose::Reset, '000000'))
        ->toThrow(OtpInvalid::class, 'Ce code est incorrect ou a expiré. Vérifie le dernier e-mail reçu ou demande un nouveau code.');

    // Le bon code ne passe plus : il faut en demander un nouveau.
    expect($record->fresh()?->isConsumed())->toBeTrue()
        ->and($this->service->verify('awa@exemple.bj', OtpPurpose::Reset, '123456'))->toBeFalse();
});

it('n’autorise un renvoi qu’après 30 secondes et annule alors le code précédent', function (): void {
    $first = $this->service->issue('awa@exemple.bj', OtpPurpose::Signup);

    expect(fn () => $this->service->issue('awa@exemple.bj', OtpPurpose::Signup))->toThrow(OtpTooSoon::class)
        ->and($this->service->canResendAt('awa@exemple.bj', OtpPurpose::Signup)?->toDateTimeString())->toBe('2026-10-17 10:00:30');

    Carbon::setTestNow('2026-10-17 10:00:30');

    expect($this->service->canResendAt('awa@exemple.bj', OtpPurpose::Signup))->toBeNull();

    $second = $this->service->issue('awa@exemple.bj', OtpPurpose::Signup);

    expect($first->fresh()?->isConsumed())->toBeTrue()
        ->and($second->isConsumed())->toBeFalse()
        ->and($this->service->currentCode('awa@exemple.bj', OtpPurpose::Signup)?->is($second))->toBeTrue();
});

it('plafonne à 5 envois par heure par adresse et par usage', function (): void {
    foreach (range(0, 4) as $i) {
        Carbon::setTestNow('2026-10-17 10:0'.$i.':00');
        $this->service->issue('awa@exemple.bj', OtpPurpose::Login);
    }

    Carbon::setTestNow('2026-10-17 10:30:00');

    expect(fn () => $this->service->issue('awa@exemple.bj', OtpPurpose::Login))->toThrow(OtpRateLimited::class)
        ->and($this->service->canResendAt('awa@exemple.bj', OtpPurpose::Login)?->toDateTimeString())->toBe('2026-10-17 11:00:00');

    // Un autre usage ou une autre adresse ne sont pas concernés.
    $this->service->issue('awa@exemple.bj', OtpPurpose::Reset);
    $this->service->issue('autre@exemple.bj', OtpPurpose::Login);

    Carbon::setTestNow('2026-10-17 11:00:01');

    expect($this->service->canResendAt('awa@exemple.bj', OtpPurpose::Login))->toBeNull()
        ->and($this->service->issue('awa@exemple.bj', OtpPurpose::Login))->toBeInstanceOf(OtpCode::class);
});

it('génère sans envoyer pour les e-mails composés ailleurs', function (): void {
    $issued = $this->service->generate('awa@exemple.bj', OtpPurpose::Reset);

    Mail::assertNothingSent();

    expect($issued->code)->toMatch('/^\d{6}$/')
        ->and($issued->record->matches($issued->code))->toBeTrue()
        ->and(OtpService::expiryLabel())->toBe('10 minutes');
});

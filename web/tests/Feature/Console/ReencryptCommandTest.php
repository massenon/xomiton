<?php

declare(strict_types=1);

use App\Application\Platform\ReencryptColumns;
use App\Enums\JobStatus;
use App\Models\Alert;
use App\Models\AlertNote;
use App\Models\AssistantMessage;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\CaseFollowup;
use App\Models\CaseForm;
use App\Models\CaseRecord;
use App\Models\Message;
use App\Models\Profile;
use App\Models\Setting;
use App\Models\SocialChannel;
use App\Models\SyncConflict;
use App\Support\Settings;
use Illuminate\Encryption\Encrypter;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/*
 * `xomiton:reencrypt` (P1-15) : chiffrement avec une clé A, bascule d'APP_KEY sur B (A dans APP_PREVIOUS_KEYS),
 * rotation, relecture avec B seule ; simulation ; refus sans sauvegarde récente ; échecs comptés ; reprise.
 */

/**
 * Bascule APP_KEY (et les clés précédentes) à chaud, comme le ferait un nouveau démarrage après `key:generate`.
 *
 * @param  list<string>  $previous
 */
function switchAppKey(string $key, array $previous = []): void
{
    config(['app.key' => $key, 'app.previous_keys' => $previous]);
    (new EncryptionServiceProvider(app()))->register();
    Crypt::clearResolvedInstance('encrypter');
}

function freshAppKey(): string
{
    return 'base64:'.base64_encode(random_bytes(32));
}

/**
 * Encrypter n'ayant que cette clé : prouve qu'une valeur est lisible sans les clés précédentes.
 */
function encrypterFor(string $key): Encrypter
{
    return new Encrypter(base64_decode(substr($key, 7)), (string) config('app.cipher'));
}

/**
 * Nombre de valeurs chiffrées non nulles en base (settings : secrets seulement).
 */
function encryptedValuesCount(): int
{
    $count = 0;

    foreach (config('xomiton.encrypted_columns') as $table => $columns) {
        foreach ($columns as $column) {
            $query = DB::table($table)->whereNotNull($column)->where($column, '!=', '');

            if ($table === 'settings') {
                $query->where('type', Setting::TYPE_SECRET);
            }

            $count += $query->count();
        }
    }

    return $count;
}

function recentBackup(): Backup
{
    return Backup::factory()->create(['created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2)]);
}

/**
 * Lance la commande et renvoie son code de sortie et sa sortie complète (plusieurs compteurs par ligne).
 *
 * @param  array<string, mixed>  $parameters
 * @return array{0: int, 1: string}
 */
function runReencrypt(array $parameters = []): array
{
    $code = Artisan::call('xomiton:reencrypt', $parameters);

    return [$code, Artisan::output()];
}

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 10, 17, 3));
    $this->keyA = (string) config('app.key');
    $this->keyB = freshAppKey();
});

it('rechiffre toutes les colonnes chiffrées avec la nouvelle clé et les laisse relisibles sans l’ancienne', function (): void {
    $profile = Profile::factory()->withSensitiveData()->create();
    $alert = Alert::factory()->sos()->located(6.3703, 2.3912)->withContact('+229 01 97 00 00 00')->create(['description' => 'Récit confidentiel du SOS.']);
    $note = AlertNote::factory()->create(['body' => 'Note interne de l’admin.']);
    $case = CaseRecord::factory()->create(['notes' => 'Récit confidentiel d’au moins vingt caractères.']);
    $form = CaseForm::factory()->create(['facts_author' => 'Conjoint', 'facts_description' => 'Description des faits.', 'services_note' => 'Note de services.', 'consent_signatory' => 'Signataire']);
    $followup = CaseFollowup::factory()->create(['comment' => 'Rappel prévu jeudi.']);
    $message = Message::factory()->create(['body' => 'Message privé.']);
    $assistant = AssistantMessage::factory()->create(['body' => 'Question posée à l’assistant.']);
    $channel = SocialChannel::factory()->connected()->create(['access_token' => 'tok_secret', 'refresh_token' => 'rtk_secret']);
    $setting = Setting::factory()->secret('mot-de-passe-smtp')->create(['key' => 'smtp_password']);
    $conflict = SyncConflict::factory()->create(['server_value' => 'ouvert', 'device_value' => 'clos']);
    recentBackup();

    $plain = [
        'gender' => $profile->gender_identity,
        'orientation' => $profile->orientation,
        'phone' => $profile->trusted_contact_phone,
        'lat' => $alert->lat,
        'lng' => $alert->lng,
    ];
    $expected = encryptedValuesCount();

    expect($expected)->toBeGreaterThanOrEqual(21);

    // Rotation : B devient APP_KEY, A reste lisible via APP_PREVIOUS_KEYS.
    switchAppKey($this->keyB, [$this->keyA]);

    [$code, $output] = runReencrypt();

    expect($code)->toBe(0)
        ->and($output)->toContain("{$expected} valeurs rechiffrées")
        ->toContain('0 échec')
        ->toContain('Ré-encryption terminée');

    // Relecture avec B seule : l'ancienne clé a été retirée d'APP_PREVIOUS_KEYS.
    switchAppKey($this->keyB);
    Settings::flush();

    expect($profile->fresh()->gender_identity)->toBe($plain['gender'])
        ->and($profile->fresh()->orientation)->toBe($plain['orientation'])
        ->and($profile->fresh()->trusted_contact_phone)->toBe($plain['phone'])
        ->and($alert->fresh()->description)->toBe('Récit confidentiel du SOS.')
        ->and($alert->fresh()->lat)->toBe($plain['lat'])
        ->and($alert->fresh()->lng)->toBe($plain['lng'])
        ->and($alert->fresh()->contact)->toBe('+229 01 97 00 00 00')
        ->and($note->fresh()->body)->toBe('Note interne de l’admin.')
        ->and($case->fresh()->notes)->toBe('Récit confidentiel d’au moins vingt caractères.')
        ->and($form->fresh()->facts_author)->toBe('Conjoint')
        ->and($form->fresh()->facts_description)->toBe('Description des faits.')
        ->and($form->fresh()->services_note)->toBe('Note de services.')
        ->and($form->fresh()->consent_signatory)->toBe('Signataire')
        ->and($followup->fresh()->comment)->toBe('Rappel prévu jeudi.')
        ->and($message->fresh()->body)->toBe('Message privé.')
        ->and($assistant->fresh()->body)->toBe('Question posée à l’assistant.')
        ->and($channel->fresh()->access_token)->toBe('tok_secret')
        ->and($channel->fresh()->refresh_token)->toBe('rtk_secret')
        ->and(Setting::get('smtp_password'))->toBe('mot-de-passe-smtp')
        ->and($conflict->fresh()->server_value)->toBe('ouvert')
        ->and($conflict->fresh()->device_value)->toBe('clos');

    // La charge brute est bien chiffrée avec B (et plus avec A).
    $rawNotes = (string) DB::table('cases')->where('id', $case->getKey())->value('notes');
    expect(encrypterFor($this->keyB)->decrypt($rawNotes, false))->toBe('Récit confidentiel d’au moins vingt caractères.')
        ->and(fn () => encrypterFor($this->keyA)->decrypt($rawNotes, false))->toThrow(Illuminate\Contracts\Encryption\DecryptException::class);

    expect(AuditLog::query()->ofAction(ReencryptColumns::AUDIT_ACTION)->count())->toBe(1);

    // Relance avec --from : tout est déjà à jour, rien n'est réécrit.
    [$code, $output] = runReencrypt(['--from' => $this->keyA]);

    expect($code)->toBe(0)
        ->and($output)->toContain('0 valeur rechiffrée')
        ->toContain("{$expected} déjà à jour");

    expect((string) DB::table('cases')->where('id', $case->getKey())->value('notes'))->toBe($rawNotes);
});

it('simule sans rien écrire avec --dry-run, sans exiger de sauvegarde', function (): void {
    $alert = Alert::factory()->withContact('+229 01 97 11 11 11')->create();
    $before = DB::table('alerts')->where('id', $alert->getKey())->first();

    switchAppKey($this->keyB, [$this->keyA]);

    [$code, $output] = runReencrypt(['--dry-run' => true]);

    expect($code)->toBe(0)
        ->and($output)->toContain('Simulation')
        ->toContain('2 valeurs à rechiffrer');

    $after = DB::table('alerts')->where('id', $alert->getKey())->first();

    expect($after->description)->toBe($before->description)
        ->and($after->contact)->toBe($before->contact)
        ->and(AuditLog::query()->ofAction(ReencryptColumns::AUDIT_ACTION)->exists())->toBeFalse();
});

it('refuse de tourner sans sauvegarde terminée de moins de 24 h, sauf --force', function (): void {
    $alert = Alert::factory()->create();
    $before = (string) DB::table('alerts')->where('id', $alert->getKey())->value('description');

    switchAppKey($this->keyB, [$this->keyA]);

    [$code, $output] = runReencrypt();

    expect($code)->toBe(1)
        ->and($output)->toContain('Aucune sauvegarde terminée depuis moins de 24 h');

    Backup::factory()->create(['created_at' => now()->subHours(25), 'updated_at' => now()->subHours(25)]);
    Backup::factory()->status(JobStatus::Failed)->create(['created_at' => now()->subHour(), 'updated_at' => now()->subHour()]);

    expect(runReencrypt()[0])->toBe(1)
        ->and((string) DB::table('alerts')->where('id', $alert->getKey())->value('description'))->toBe($before);

    [$code, $output] = runReencrypt(['--force' => true]);

    expect($code)->toBe(0)
        ->and($output)->toContain('1 valeur rechiffrée');

    expect((string) DB::table('alerts')->where('id', $alert->getKey())->value('description'))->not->toBe($before);
});

it('exige une ancienne clé (--from ou APP_PREVIOUS_KEYS) et une taille de lot valide', function (): void {
    recentBackup();
    switchAppKey($this->keyB);

    [$code, $output] = runReencrypt();

    expect($code)->toBe(2)
        ->and($output)->toContain('Aucune ancienne clé');

    [$code, $output] = runReencrypt(['--from' => $this->keyB]);

    expect($code)->toBe(2)
        ->and($output)->toContain('identique à APP_KEY')
        ->and(runReencrypt(['--from' => $this->keyA, '--chunk' => 0])[0])->toBe(2)
        ->and(runReencrypt(['--from' => 'base64:pas-une-clé'])[0])->toBe(2);
});

it('compte en échec, sans la modifier, une valeur indéchiffrable avec les deux clés', function (): void {
    $alert = Alert::factory()->withContact('+229 01 97 22 22 22')->create();
    $description = $alert->description;
    DB::table('alerts')->where('id', $alert->getKey())->update(['contact' => 'contact en clair']);
    recentBackup();

    switchAppKey($this->keyB, [$this->keyA]);

    [$code, $output] = runReencrypt();

    expect($code)->toBe(1)
        ->and($output)->toContain('1 valeur rechiffrée')
        ->toContain('1 échec')
        ->toContain('indéchiffrables');

    switchAppKey($this->keyB);

    expect(DB::table('alerts')->where('id', $alert->getKey())->value('contact'))->toBe('contact en clair')
        ->and($alert->fresh()->description)->toBe($description);
});

it('s’arrête proprement au temps imparti et reprend à l’appel suivant', function (): void {
    Profile::factory()->count(3)->withSensitiveData()->create();
    recentBackup();

    switchAppKey($this->keyB, [$this->keyA]);

    $first = app(ReencryptColumns::class)->run($this->keyA, chunkSize: 1, timeBudget: 0.0);

    expect($first->completed)->toBeFalse()
        ->and(array_keys($first->tables))->toBe(['profiles'])
        ->and($first->tables['profiles']['processed'])->toBe(3)
        ->and($first->processed())->toBe(3);

    $second = app(ReencryptColumns::class)->run($this->keyA, chunkSize: 1);

    expect($second->completed)->toBeTrue()
        ->and($second->tables['profiles']['processed'])->toBe(6)
        ->and($second->tables['profiles']['skipped'])->toBe(3)
        ->and($second->failed())->toBe(0);

    switchAppKey($this->keyB);

    expect(Profile::query()->get()->filter(fn (Profile $profile): bool => $profile->gender_identity !== null))->toHaveCount(3)
        ->and(AuditLog::query()->ofAction(ReencryptColumns::AUDIT_ACTION)->count())->toBe(2);
});

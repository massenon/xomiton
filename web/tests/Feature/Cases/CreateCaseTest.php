<?php

declare(strict_types=1);

use App\Application\Cases\AddFollowup;
use App\Application\Cases\CaseValidator;
use App\Application\Cases\CreateCase;
use App\Application\Cases\Data\CaseChanges;
use App\Application\Cases\Data\FollowupInput;
use App\Application\Cases\Results\CaseWarning;
use App\Application\Cases\UpdateCase;
use App\Domain\Cases\Exceptions\CaseLocked;
use App\Domain\Cases\Exceptions\ClientUuidConflict;
use App\Domain\Cases\Exceptions\ClientUuidReused;
use App\Domain\Cases\Exceptions\InvalidCaseTransition;
use App\Enums\AlertSource;
use App\Enums\CaseStatus;
use App\Enums\FollowupOutcome;
use App\Models\AuditLog;
use App\Models\CaseFollowup;
use App\Models\CaseRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CaseFixtures as C;
use Tests\Support\FeedFixtures as F;

/*
 * Cas d'usage des dossiers (P9-01, réutilisés par `sync/push` P9-06) : code de registre attribué par le serveur
 * (RG-06, compteur par nature), idempotence par `client_uuid` (ADR-32), notes chiffrées, audit sans contenu,
 * transitions de statut, avertissements de plausibilité (RG-07) et de doublons limités aux dossiers visibles.
 */

beforeEach(function (): void {
    C::seed();
    $this->peer = C::peer();
});

function createCaseFor(App\Models\User $user, array $overrides = [], ?string $uuid = null, AlertSource $source = AlertSource::Web): App\Application\Cases\Results\CaseWritten
{
    $payload = C::payload($overrides);

    return app(CreateCase::class)($user, app(CaseValidator::class)->newCase($payload), $uuid, $source);
}

it('attribue VBG-0001 puis VDH-0001 : un compteur par nature (RG-06)', function (): void {
    $first = createCaseFor($this->peer);
    $second = createCaseFor($this->peer);
    $vdh = createCaseFor($this->peer, ['kind' => 'VDH']);

    expect($first->case->code)->toBe('VBG-0001')
        ->and($second->case->code)->toBe('VBG-0002')
        ->and($vdh->case->code)->toBe('VDH-0001')
        ->and($first->case->status)->toBe(CaseStatus::Open)
        ->and((int) $first->case->collected_by)->toBe($this->peer->id)
        ->and($first->created)->toBeTrue();
});

it('chiffre les notes au repos et journalise la collecte sans leur contenu', function (): void {
    $case = createCaseFor($this->peer, ['notes' => 'Accueil réalisé, orientation vers une psychologue.'])->case;
    $raw = DB::table('cases')->where('id', $case->id)->value('notes');

    expect($raw)->not->toContain('psychologue')
        ->and($case->fresh()->notes)->toBe('Accueil réalisé, orientation vers une psychologue.');

    $log = AuditLog::query()->where('action', 'cases.created')->sole();

    expect($log->detail)->toContain('code=VBG-0001')->not->toContain('psychologue');
});

it('rejoue un même client_uuid sans doublon, et refuse celui d’un autre compte', function (): void {
    $uuid = (string) Str::uuid();
    $first = createCaseFor($this->peer, [], strtoupper($uuid), AlertSource::SyncOffline);
    $replay = createCaseFor($this->peer, [], $uuid, AlertSource::SyncOffline);

    expect($replay->created)->toBeFalse()
        ->and($replay->case->id)->toBe($first->case->id)
        ->and($first->case->client_uuid)->toBe($uuid)
        ->and($first->case->source)->toBe(AlertSource::SyncOffline)
        ->and(CaseRecord::query()->count())->toBe(1);

    createCaseFor(C::peer(), [], $uuid);
})->throws(ClientUuidConflict::class);

it('refuse la collecte à un compte sans cases.create', function (): void {
    createCaseFor(F::member());
})->throws(AuthorizationException::class);

it('avertit d’une date de plus de cinq ans sans refuser (RG-07)', function (): void {
    $old = createCaseFor($this->peer, ['occurred_on' => now()->subYears(6)->format('Y-m-d')]);
    $recent = createCaseFor($this->peer);

    expect(array_column($old->warningsArray(), 'code'))->toBe([CaseWarning::DATE_OLD])
        ->and($recent->warnings)->toBe([]);

    C::disable('plausibility');

    $again = createCaseFor($this->peer, ['occurred_on' => now()->subYears(6)->format('Y-m-d')]);

    expect(array_column($again->warningsArray(), 'code'))->not->toContain(CaseWarning::DATE_OLD);
});

it('signale les doublons suspects parmi ses propres dossiers seulement', function (): void {
    $other = C::peer();
    createCaseFor($other);
    $mine = createCaseFor($this->peer);

    expect($mine->warnings)->toBe([]);

    $twin = createCaseFor($this->peer, ['referral' => 'Juriste']);

    expect($twin->warningsArray()[0]['code'])->toBe(CaseWarning::DUPLICATE_SUSPECTED)
        ->and($twin->warningsArray()[0]['codes'])->toBe([$mine->case->code])
        ->and($twin->warningsArray()[0]['message'])->toContain($mine->case->code)->not->toContain('VBG-0001');

    C::disable('duplicates');

    expect(createCaseFor($this->peer)->warnings)->toBe([]);
});

it('clôt puis rouvre un dossier en posant puis effaçant closed_at, sans transition interdite', function (): void {
    $case = createCaseFor($this->peer)->case;
    $update = app(UpdateCase::class);

    $closed = $update($this->peer, $case, new CaseChanges([], CaseStatus::Closed))->case;

    expect($closed->status)->toBe(CaseStatus::Closed)->and($closed->closed_at)->not->toBeNull();

    $reopened = $update($this->peer, $closed, new CaseChanges(['notes' => 'Nouvelle rencontre, besoin d’un appui juridique.'], CaseStatus::InProgress))->case;

    expect($reopened->status)->toBe(CaseStatus::InProgress)
        ->and($reopened->closed_at)->toBeNull()
        ->and(AuditLog::query()->where('action', 'cases.updated')->latest('id')->value('detail'))
        ->toContain('champs=notes')->toContain('statut=clos→en_cours')->not->toContain('juridique');

    $update($this->peer, $reopened, new CaseChanges([], CaseStatus::Archived));
})->throws(AuthorizationException::class);

it('refuse la modification du dossier d’un autre pair', function (): void {
    $case = CaseRecord::factory()->collectedBy($this->peer)->create();

    app(UpdateCase::class)(C::peer(), $case, new CaseChanges(['referral' => 'Juriste']));
})->throws(AuthorizationException::class);

it('refuse la modification d’un dossier archivé', function (): void {
    $archived = CaseRecord::factory()->collectedBy($this->peer)->archived()->create();

    app(UpdateCase::class)($this->peer, $archived, new CaseChanges(['referral' => 'Juriste']));
})->throws(CaseLocked::class);

it('refuse une étape de suivi sur un dossier archivé', function (): void {
    $archived = CaseRecord::factory()->collectedBy($this->peer)->archived()->create();

    app(AddFollowup::class)($this->peer, $archived, new FollowupInput(now()->format('Y-m-d'), 'Accueil', FollowupOutcome::Done));
})->throws(CaseLocked::class);

it('refuse une étape de suivi sur un dossier anonymisé', function (): void {
    $anonymized = CaseRecord::factory()->collectedBy($this->peer)->create(['anonymized_at' => now()]);

    app(AddFollowup::class)($this->peer, $anonymized, new FollowupInput(now()->format('Y-m-d'), 'Accueil', FollowupOutcome::Done));
})->throws(CaseLocked::class);

it('refuse une transition hors de CaseStatus : ouvert → archivé, même pour l’administration', function (): void {
    $open = CaseRecord::factory()->collectedBy($this->peer)->create();

    app(UpdateCase::class)(F::member('admin'), $open, new CaseChanges([], CaseStatus::Archived));
})->throws(InvalidCaseTransition::class);

it('refuse le rejeu d’un client_uuid avec une autre saisie (idempotency_key_reused), sans rien écrire', function (): void {
    $uuid = (string) Str::uuid();
    $first = createCaseFor($this->peer, ['notes' => 'Accueil réalisé, orientation vers une psychologue.'], $uuid);

    expect(fn () => createCaseFor($this->peer, ['notes' => 'Autre récit, saisi par erreur sous la même clé.'], $uuid))
        ->toThrow(ClientUuidReused::class);

    expect(CaseRecord::query()->count())->toBe(1)
        ->and($first->case->fresh()->notes)->toBe('Accueil réalisé, orientation vers une psychologue.');
});

it('refuse le rejeu du client_uuid d’une étape de suivi avec une autre saisie', function (): void {
    $case = createCaseFor($this->peer)->case;
    $uuid = (string) Str::uuid();
    app(AddFollowup::class)($this->peer, $case, new FollowupInput(now()->format('Y-m-d'), 'Écoute', FollowupOutcome::Done), $uuid);

    expect(fn () => app(AddFollowup::class)($this->peer, $case, new FollowupInput(now()->format('Y-m-d'), 'Écoute', FollowupOutcome::Planned), $uuid))
        ->toThrow(ClientUuidReused::class)
        ->and(CaseFollowup::query()->count())->toBe(1);
});

it('attribue VBG-10000 après VBG-9999 en base, puis reprend après un code posé hors du compteur (RG-06)', function (): void {
    CaseRecord::factory()->vbg()->collectedBy($this->peer)->create(['code' => 'VBG-9999']);

    expect(createCaseFor($this->peer)->case->code)->toBe('VBG-10000')
        ->and(createCaseFor($this->peer)->case->code)->toBe('VBG-10001');

    CaseRecord::factory()->vbg()->collectedBy($this->peer)->create(['code' => 'VBG-10002']);

    expect(createCaseFor($this->peer)->case->code)->toBe('VBG-10003')
        ->and(createCaseFor($this->peer, ['kind' => 'VDH'])->case->code)->toBe('VDH-0001')
        ->and(DB::table('case_code_counters')->where('kind', 'VBG')->value('last_sequence'))->toBe(10003);
});

it('ajoute une étape de suivi idempotente par client_uuid, commentaire chiffré', function (): void {
    $case = createCaseFor($this->peer)->case;
    $uuid = (string) Str::uuid();
    $input = new FollowupInput(now()->format('Y-m-d'), 'Écoute', FollowupOutcome::Done, now()->addDays(30)->format('Y-m-d'), 'Échange apaisé, rendez-vous fixé.');

    $first = app(AddFollowup::class)($this->peer, $case, $input, $uuid);
    $replay = app(AddFollowup::class)($this->peer, $case, $input, $uuid);

    expect($first->created)->toBeTrue()
        ->and($replay->created)->toBeFalse()
        ->and($replay->followup->id)->toBe($first->followup->id)
        ->and(CaseFollowup::query()->count())->toBe(1)
        ->and(DB::table('case_followups')->value('comment'))->not->toContain('apaisé');

    $otherCase = createCaseFor($this->peer, ['kind' => 'VDH'])->case;
    app(AddFollowup::class)($this->peer, $otherCase, $input, $uuid);
})->throws(ClientUuidConflict::class);

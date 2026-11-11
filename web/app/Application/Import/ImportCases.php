<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\AgeRange;
use App\Enums\AlertSource;
use App\Enums\CaseKind;
use App\Enums\CaseStatus;
use App\Enums\MemberType;
use App\Enums\ProfileVisibility;
use App\Enums\Sex;
use App\Enums\UserStatus;
use App\Models\CaseRecord;
use App\Models\Population;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\ViolenceType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * cases.json → cases (date → occurred_on, sexe → sex, age → age_range, type → violence_type_id,
 * pair → collected_by, referral conservé, notes chiffré). Le prototype numérote tout en `VBG-00xx` :
 * les dossiers VDH reçoivent un code `VDH-0000` via CaseRecord::nextCode (RG-06), la correspondance
 * code du prototype → code réel est journalisée et conservée pour suivi.json et sync_conflicts.json.
 * Les pair·es absent·es de users.json (Esther Koto, Mireille Tognifode) reçoivent un compte pair
 * au mot de passe aléatoire, journalisé. Idempotence : client_uuid dérivé du code du prototype.
 */
final class ImportCases extends AbstractImporter
{
    public static function collection(): string
    {
        return 'cases';
    }

    public static function isDemo(): bool
    {
        return true;
    }

    protected function importRow(array $row, int $index): void
    {
        $prototypeCode = mb_strtoupper($this->required($row['code'] ?? null, 'code', 10));
        $kind = CaseKind::tryFrom(mb_strtoupper((string) ($row['kind'] ?? '')))
            ?? $this->reject(sprintf('nature inconnue « %s »', (string) ($row['kind'] ?? '')));
        $occurredOn = $this->ctx->date($row['date'] ?? null) ?? $this->reject('date de l’événement absente ou invalide');
        $status = CaseStatus::tryFrom((string) ($row['status'] ?? ''))
            ?? $this->reject(sprintf('statut inconnu « %s »', (string) ($row['status'] ?? '')));
        $zoneId = $this->ctx->zoneId($row['zone'] ?? null) ?? $this->reject('département manquant');
        $communeId = $this->ctx->communeId($row['commune'] ?? null, $zoneId);
        $collectedBy = $this->collectorId($this->required($row['pair'] ?? null, 'pair', 120));

        $uuid = ImportKeys::caseUuid($prototypeCode);
        $case = CaseRecord::query()->where('client_uuid', $uuid)->first();

        if ($case === null) {
            $case = new CaseRecord(['client_uuid' => $uuid, 'code' => $this->codeFor($kind, $prototypeCode)]);
        }

        $case->fill([
            'kind' => $kind,
            'occurred_on' => $occurredOn,
            'zone_id' => $zoneId,
            'commune_id' => $communeId,
            'population_id' => $this->populationId($row['population'] ?? null),
            'sex' => $this->sex($row['sexe'] ?? null),
            'age_range' => AgeRange::tryFrom((string) ($row['age'] ?? '')),
            'violence_type_id' => $this->violenceTypeId($row['type'] ?? null),
            'status' => $status,
            'collected_by' => $collectedBy,
            'referral' => $this->text($row['referral'] ?? null, 80),
            'notes' => $this->text($row['notes'] ?? null, 2000),
            'source' => AlertSource::tryFrom((string) ($row['source'] ?? 'web')) ?? AlertSource::Web,
        ]);

        if ($status === CaseStatus::Closed) {
            $case->closed_at ??= now();
        } elseif ($status === CaseStatus::Archived) {
            $case->archived_at ??= now();
        }

        $case->save();

        if ($case->wasRecentlyCreated && $case->code !== $prototypeCode) {
            $detail = sprintf('%s → %s', $prototypeCode, $case->code);
            $this->ctx->note('dossier renuméroté '.$detail);
            $this->ctx->audit(ImportContext::AUDIT_CASE_RENUMBERED, $detail, $case);
        }

        $this->ctx->remember('cases', $prototypeCode, (int) $case->getKey());
        $this->track($case);
    }

    /**
     * Code réel d'un nouveau dossier : un VBG garde son code du prototype s'il est libre et bien préfixé ;
     * sinon (VDH, collision, préfixe incohérent) le prochain code de la nature (RG-06).
     */
    private function codeFor(CaseKind $kind, string $prototypeCode): string
    {
        $keepsCode = str_starts_with($prototypeCode, $kind->codePrefix())
            && preg_match('/^[A-Z]{3}-\d{4}$/', $prototypeCode) === 1
            && ! CaseRecord::query()->where('code', $prototypeCode)->exists();

        return $keepsCode ? $prototypeCode : CaseRecord::nextCode($kind);
    }

    /** Compte pair qui a collecté le dossier, créé s'il n'existe pas (dictionnaire : comptes pair manquants). */
    private function collectorId(string $pairName): int
    {
        $userId = $this->ctx->userIdByName($pairName);

        if ($userId !== null) {
            return $userId;
        }

        $contact = $this->ctx->contactByName($pairName);
        $email = mb_strtolower((string) ($contact?->email ?? ''));

        if ($email === '' || $this->ctx->userIdByEmail($email) !== null) {
            $email = Str::slug($pairName, '.').'@import.xomiton.invalid';
        }

        $user = new User([
            'pseudo' => $this->uniquePseudo(Str::slug($pairName, '_')),
            'name' => $pairName,
            'email' => $email,
            'phone' => $contact?->phone,
            'password' => Str::password(24),
            'status' => UserStatus::Active,
            'zone_id' => $contact?->zone_id,
            'commune_id' => $contact?->commune_id,
            'email_verified_at' => now(),
        ]);
        $user->save();

        Profile::query()->create([
            'user_id' => $user->getKey(),
            'member_type' => MemberType::Peer,
            'visibility' => ProfileVisibility::Community,
            'completion' => 40,
        ]);
        UserSetting::query()->create([
            'user_id' => $user->getKey(),
            'notification_prefs' => UserSetting::defaultNotificationPrefs(),
            'notification_digest' => UserSetting::DIGEST_WEEKLY,
        ]);

        if (Role::query()->where('name', 'pair')->where('guard_name', RolesAndPermissionsSeeder::GUARD)->exists()) {
            $user->syncRoles(['pair']);
        }

        if ($contact !== null && $contact->user_id === null) {
            $contact->forceFill(['user_id' => $user->getKey()])->save();
        }

        $detail = sprintf('compte pair « %s » (%s) créé pour les registres, mot de passe aléatoire à réinitialiser', $pairName, $email);
        $this->ctx->note($detail);
        $this->ctx->audit(ImportContext::AUDIT_ACCOUNT_CREATED, $detail, $user);
        $this->ctx->rememberUser($user);

        return (int) $user->getKey();
    }

    private function uniquePseudo(string $base): string
    {
        $base = $base !== '' ? mb_substr($base, 0, 36) : 'pair';
        $pseudo = $base;

        for ($i = 2; User::withTrashed()->where('pseudo', $pseudo)->exists(); $i++) {
            $pseudo = $base.'_'.$i;
        }

        return $pseudo;
    }

    private function populationId(mixed $label): ?int
    {
        $label = $this->text($label, 80);

        if ($label === null) {
            return null;
        }

        $id = Population::query()->where('slug', Str::slug($label))->orWhere('label', $label)->value('id');

        if ($id === null) {
            $this->ctx->note(sprintf('population « %s » inconnue du référentiel : dossier importé sans population', $label));
        }

        return $id === null ? null : (int) $id;
    }

    private function violenceTypeId(mixed $slug): ?int
    {
        $slug = $this->text($slug, 40);

        if ($slug === null) {
            return null;
        }

        $id = ViolenceType::query()->where('slug', $slug)->value('id');

        if ($id === null) {
            $this->ctx->note(sprintf('type de violence « %s » inconnu : dossier importé sans type', $slug));
        }

        return $id === null ? null : (int) $id;
    }

    /** Libellé du prototype (`Femme`, `Homme`, `Autre / non-binaire`) → Sex. */
    private function sex(mixed $label): ?Sex
    {
        $label = mb_strtolower(trim((string) $label));

        if ($label === '') {
            return null;
        }

        foreach (Sex::cases() as $case) {
            if ($label === $case->value || $label === mb_strtolower($case->label())) {
                return $case;
            }
        }

        return str_starts_with($label, 'autre') ? Sex::Autre : null;
    }
}

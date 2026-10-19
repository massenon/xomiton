<?php

declare(strict_types=1);

namespace App\Application\Cases;

use App\Application\Cases\Data\CaseInput;
use App\Application\Cases\Results\CaseWritten;
use App\Domain\Cases\Exceptions\ClientUuidConflict;
use App\Domain\Cases\Exceptions\ClientUuidReused;
use App\Enums\AlertSource;
use App\Enums\CaseStatus;
use App\Models\CaseRecord;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Collecte d'un dossier VBG / VDH (P9-01, P9-02 ; prototype collecte.php l.33-38, api.php `sync` l.53-59) : code de
 * registre attribué par le serveur (RG-06, CaseRecord::nextCode dans la transaction de l'insertion, nouvel essai si
 * deux saisies simultanées obtiennent le même code), statut `ouvert`, collecte au nom du compte, notes chiffrées.
 *
 * **Idempotent par `client_uuid`** (ADR-32, réutilisé par `sync/push`, P9-06) : un rejeu du même uuid par la même
 * personne avec la même saisie rend le dossier d'origine (`created = false`, 200), jamais un doublon ni un 409 ; même
 * uuid avec une autre saisie → 422 `idempotency_key_reused` (CaseInput::matches, ADR-32 précision P5-09) ; un uuid
 * porté par le dossier d'un autre compte → 422 `client_uuid_conflict`, sans rien en dire. Autorisation : `CaseRecordPolicy::create`
 * (`cases.create`). Journal d'audit « cases.created » (code, nature, source ; jamais les notes). Avertissements non
 * bloquants : CaseQuality (date ancienne, doublons suspects).
 */
final class CreateCase
{
    /** Essais d'attribution du code en cas de collision (deux collectes simultanées de la même nature). */
    private const CODE_ATTEMPTS = 3;

    public function __construct(private readonly CaseQuality $quality) {}

    public function __invoke(
        User $collector,
        CaseInput $input,
        ?string $clientUuid = null,
        AlertSource $source = AlertSource::Web,
        ?int $deviceId = null,
    ): CaseWritten {
        Gate::forUser($collector)->authorize('create', CaseRecord::class);

        $clientUuid = $clientUuid === null || trim($clientUuid) === '' ? null : Str::lower(trim($clientUuid));

        if ($clientUuid !== null && ($existing = $this->replay($collector, $input, $clientUuid)) !== null) {
            return new CaseWritten($existing, created: false, warnings: $this->quality->warnings($existing, $collector));
        }

        $case = null;

        for ($attempt = 1; $case === null; $attempt++) {
            try {
                $case = DB::transaction(static function () use ($collector, $input, $clientUuid, $source, $deviceId): CaseRecord {
                    return CaseRecord::query()->create($input->toAttributes() + [
                        'code' => CaseRecord::nextCode($input->kind),
                        'status' => CaseStatus::Open,
                        'collected_by' => $collector->getKey(),
                        'source' => $source,
                        'client_uuid' => $clientUuid,
                        'device_id' => $deviceId,
                    ]);
                });
            } catch (UniqueConstraintViolationException $e) {
                // Rejeu concurrent du même envoi : le premier a gagné.
                if ($clientUuid !== null && ($existing = $this->replay($collector, $input, $clientUuid)) !== null) {
                    return new CaseWritten($existing, created: false, warnings: $this->quality->warnings($existing, $collector));
                }

                if ($attempt >= self::CODE_ATTEMPTS) {
                    throw $e;
                }
            }
        }

        Audit::log('cases.created', $case, sprintf('code=%s ; nature=%s ; source=%s', $case->code, $input->kind->value, $source->value), $collector);

        return new CaseWritten($case, created: true, warnings: $this->quality->warnings($case, $collector));
    }

    private function replay(User $collector, CaseInput $input, string $clientUuid): ?CaseRecord
    {
        $existing = CaseRecord::query()->where('client_uuid', $clientUuid)->first();

        if ($existing === null) {
            return null;
        }

        if ((int) $existing->collected_by !== (int) $collector->getKey()) {
            throw new ClientUuidConflict;
        }

        if (! $input->matches($existing)) {
            throw new ClientUuidReused;
        }

        return $existing;
    }
}

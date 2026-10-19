<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Sync;

use App\Application\Alerts\Data\ReportData;
use App\Application\Alerts\Data\SosData;
use App\Application\Cases\CaseValidator;
use App\Application\Cases\Data\CaseInput;
use App\Application\Sync\Conflicts\ApplyCaseChanges;
use App\Application\Sync\Push\CaseChanges;
use App\Application\Sync\Push\ItemDecoder;
use App\Application\Sync\Push\ItemRejected;
use App\Application\Sync\Push\MessageDraft;
use App\Application\Sync\Push\PushItem;
use App\Enums\AlertSource;
use App\Http\Middleware\HandleIdempotencyKey;
use App\Http\Requests\Alerts\StoreReportRequest;
use App\Http\Requests\Alerts\StoreSosRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * Lecture des charges utiles de `POST sync/push` (P9-06) avec **les Form Requests des routes dédiées** (Q11 a : une
 * seule charge utile web et mobile) : StoreReportRequest et StoreSosRequest sont instanciées sur la charge utile de
 * l'élément (compte de la requête, clé d'idempotence = uuid de l'élément, jamais l'en-tête du lot). Un SOS refusé sur
 * un champ repart sans ce champ (RG-01 : jamais refusé). Dossier collecté : CaseValidator::newCase (règles de
 * `POST pair/cases`, catalogue activable P9-01). Message : règles de `POST conversations/{ulid}/messages` ;
 * modification de dossier : enveloppe et champs d'ApplyCaseChanges::FIELDS (valeurs validées par ApplyCaseChanges).
 */
final class SyncItemDecoder implements ItemDecoder
{
    /** Essais d'un SOS avant le repli sans aucun champ (position et repli manuel retirés ensemble). */
    private const SOS_ATTEMPTS = 3;

    public function __construct(
        private readonly Request $request,
        private readonly CaseValidator $cases,
    ) {}

    public function report(PushItem $item, int $deviceId): ReportData
    {
        if (self::anonymous($item->payload)) {
            throw ItemRejected::anonymous();
        }

        $request = $this->formRequest(StoreReportRequest::class, $item->payload, $item->uuid);

        try {
            $request->validateResolved();
        } catch (ValidationException $exception) {
            throw ItemRejected::invalid($exception->errors());
        }

        return $request->toData(AlertSource::SyncOffline, [], $deviceId);
    }

    public function sos(PushItem $item, int $deviceId): SosData
    {
        $payload = $item->payload;

        for ($attempt = 0; $attempt < self::SOS_ATTEMPTS; $attempt++) {
            $request = $this->formRequest(StoreSosRequest::class, $payload, $item->uuid);

            try {
                $request->validateResolved();

                return $request->toData(AlertSource::SyncOffline, $deviceId);
            } catch (ValidationException $exception) {
                $payload = Arr::except($payload, self::refused(array_keys($exception->errors())));
            }
        }

        $request = $this->formRequest(StoreSosRequest::class, [], $item->uuid);
        $request->validateResolved();

        return $request->toData(AlertSource::SyncOffline, $deviceId);
    }

    public function message(PushItem $item): MessageDraft
    {
        $validator = Validator::make($item->payload, [
            'conversation' => ['required', 'string', 'max:26'],
            'body' => ['required', 'string', 'max:'.(int) config('xomiton.messaging.body_max_length', 1000)],
        ], [], [
            'conversation' => (string) __('sync.fields.conversation'),
            'body' => (string) __('sync.fields.body'),
        ]);

        $validator->after(static function ($validator) use ($item): void {
            if (! $validator->errors()->has('body') && trim((string) ($item->payload['body'] ?? '')) === '') {
                $validator->errors()->add('body', (string) __('community.messages.errors.empty'));
            }
        });

        if ($validator->fails()) {
            throw ItemRejected::invalid($validator->errors()->toArray());
        }

        return new MessageDraft((string) $item->payload['conversation'], (string) $item->payload['body']);
    }

    public function caseInput(PushItem $item): CaseInput
    {
        // `client_uuid` = uuid de l'élément (CaseItemHandler), jamais celui de la charge utile.
        try {
            return $this->cases->newCase(Arr::except($item->payload, ['client_uuid']));
        } catch (ValidationException $exception) {
            throw ItemRejected::invalid($exception->errors());
        }
    }

    public function caseChanges(PushItem $item): CaseChanges
    {
        $payload = $item->payload;
        $changes = is_array($payload['changes'] ?? null) ? $payload['changes'] : [];
        $base = is_array($payload['base'] ?? null) ? $payload['base'] : [];

        // Enveloppe seulement : les valeurs sont validées par ApplyCaseChanges avec CaseValidator::changes (règles de
        // `PATCH pair/cases/{code}`), une fois le dossier trouvé (commune ↔ département du dossier).
        $validator = Validator::make($payload, [
            'server_id' => ['required_without:code', 'nullable', 'integer', 'min:1'],
            'code' => ['required_without:server_id', 'nullable', 'string', 'max:10'],
            'changes' => ['required', 'array', 'min:1'],
            'base' => ['present', 'array'],
        ], [], [
            'changes' => (string) __('sync.fields.changes'),
            'base' => (string) __('sync.fields.base'),
        ]);

        $validator->after(static function ($validator) use ($changes, $base): void {
            foreach (array_keys($changes) as $field) {
                if (! in_array($field, ApplyCaseChanges::FIELDS, true)) {
                    $validator->errors()->add('changes.'.$field, (string) __('sync.items.field_locked'));
                } elseif (! array_key_exists($field, $base)) {
                    $validator->errors()->add('base.'.$field, (string) __('sync.items.base_missing'));
                }
            }
        });

        if ($validator->fails()) {
            throw ItemRejected::invalid($validator->errors()->toArray());
        }

        $id = $payload['server_id'] ?? null;
        $code = $payload['code'] ?? null;

        return new CaseChanges(
            is_numeric($id) ? (int) $id : null,
            is_string($code) && $code !== '' ? $code : null,
            $changes,
            $base,
        );
    }

    /**
     * Anonyme sauf refus explicite (`anonymous: false`), comme StoreReportRequest (RG-01, RG-02).
     *
     * @param  array<string, mixed>  $payload
     */
    private static function anonymous(array $payload): bool
    {
        $value = $payload['anonymous'] ?? null;

        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false;
    }

    /**
     * Champs racine à retirer d'un SOS refusé ; position et repli manuel vont par paires.
     *
     * @param  list<string>  $errors
     * @return list<string>
     */
    private static function refused(array $errors): array
    {
        $fields = array_values(array_unique(array_map(static fn (string $key): string => Str::before($key, '.'), $errors)));

        if (array_intersect($fields, ['lat', 'lng', 'accuracy']) !== []) {
            array_push($fields, 'lat', 'lng', 'accuracy');
        }

        if (array_intersect($fields, ['zone_id', 'commune_id']) !== []) {
            array_push($fields, 'zone_id', 'commune_id');
        }

        return array_values(array_unique($fields));
    }

    /**
     * Form Request d'une route dédiée posée sur la charge utile d'un élément : même compte (résolveur de la requête du
     * lot), sans l'en-tête `Idempotency-Key` du lot, clé d'idempotence = uuid de l'élément. Sacs d'entrée propres
     * (`createFrom` partage le sac JSON de la requête du lot : `replace()` y écrirait la charge utile de l'élément).
     *
     * @template T of FormRequest
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $payload
     * @return T
     */
    private function formRequest(string $class, array $payload, string $uuid): FormRequest
    {
        $request = $class::createFrom($this->request, new $class);
        $request->headers->remove(HandleIdempotencyKey::HEADER);
        $request->query->replace([]);
        $input = ['idempotency_key' => strtolower($uuid)] + Arr::except($payload, ['idempotency_key']);
        $request->setJson(new InputBag($input));
        $request->request = new InputBag($input);
        $request->setContainer(app())->setRedirector(app(Redirector::class));

        return $request;
    }
}

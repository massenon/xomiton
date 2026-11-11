<?php

declare(strict_types=1);

namespace App\Http\Requests\Alerts;

use App\Application\Alerts\Data\SosData;
use App\Enums\AlertSource;
use App\Http\Requests\Alerts\Concerns\ReadsAlertPayload;
use App\Rules\CommuneInZone;
use App\Rules\PhoneBj;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST sos` (P5-06, P5-05, simulateur.html l.876) : aucun champ obligatoire hormis la clé d'idempotence (RG-01 : un
 * SOS part toujours) ; position facultative et ignorée si `share_location = false` ; repli manuel département /
 * commune (sos.php l.62-69) ; téléphone béninois facultatif ; message libre ≤ 1000 caractères.
 */
final class StoreSosRequest extends FormRequest
{
    use ReadsAlertPayload;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeAlertPayload(['share_location']);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:'.(int) config('xomiton.alerts.sos_message_max', 1000)],
            'phone' => ['nullable', 'string', 'max:40', new PhoneBj],
            'share_location' => ['nullable', 'boolean'],
            ...$this->locationRules(),
            'zone_id' => ['nullable', 'integer', Rule::exists('zones', 'id')->where('is_active', true)],
            'commune_id' => ['nullable', 'integer', new CommuneInZone('zone_id')],
            'needs' => ['nullable', 'array', 'max:10'],
            ...$this->needsRules(),
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function toData(AlertSource $source = AlertSource::Web, ?int $deviceId = null): SosData
    {
        $user = $this->currentUser();

        return new SosData(
            idempotencyKey: (string) $this->input('idempotency_key'),
            source: $source,
            userId: $user === null ? null : (int) $user->getKey(),
            shareLocation: $this->has('share_location') && $this->input('share_location') !== null ? $this->boolean('share_location') : null,
            lat: $this->floatOrNull('lat'),
            lng: $this->floatOrNull('lng'),
            accuracy: $this->intOrNull('accuracy'),
            zoneId: $this->intOrNull('zone_id'),
            communeId: $this->intOrNull('commune_id'),
            message: $this->stringOrNull('message'),
            phone: $this->stringOrNull('phone'),
            needs: $this->needSlugs(),
            deviceId: $deviceId,
        );
    }
}

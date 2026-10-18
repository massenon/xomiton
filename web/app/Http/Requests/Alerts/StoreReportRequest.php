<?php

declare(strict_types=1);

namespace App\Http\Requests\Alerts;

use App\Application\Alerts\Data\ReportData;
use App\Application\Shared\StoredMedia;
use App\Enums\AlertSource;
use App\Enums\AlertType;
use App\Enums\ContactChannel;
use App\Http\Requests\Alerts\Concerns\ReadsAlertPayload;
use App\Models\AlertAttachment;
use App\Rules\CommuneInZone;
use App\Rules\PhoneOrEmail;
use App\Rules\UploadAllowed;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * `POST signalements` (P5-06) et formulaire /signaler (P5-04) : une seule charge utile web et mobile (Q11 a). `type`
 * vaut `report` ou `need` (Q29 ; le SOS a sa propre requête). Besoins obligatoires pour un besoin (FR-ALT-02), type
 * de violence pour un signalement, récit de 20 à 4000 caractères, date des faits jamais future, contact exigé seulement
 * si la personne accepte d'être recontactée, `Idempotency-Key` UUID obligatoire (ADR-32).
 */
final class StoreReportRequest extends FormRequest
{
    use ReadsAlertPayload;

    /** Valeurs API (Q29) → types stockés. */
    public const TYPES = ['report' => AlertType::Report, 'need' => AlertType::Need];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeAlertPayload(['anonymous', 'contactable'], ['anonymous' => true, 'contactable' => false]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(array_keys(self::TYPES))],
            'category' => ['nullable', 'required_if:type,report', 'string', Rule::exists('violence_types', 'slug')->where('is_active', true)],
            'description' => ['required', 'string', 'min:'.(int) config('xomiton.alerts.description_min', 20), 'max:'.(int) config('xomiton.alerts.description_max', 4000)],
            'occurred_on' => ['nullable', 'date', 'before_or_equal:today'],
            'needs' => ['nullable', 'array', 'max:10', 'required_if:type,need'],
            ...$this->needsRules(),
            'zone_id' => ['required', 'integer', Rule::exists('zones', 'id')->where('is_active', true)],
            'commune_id' => ['nullable', 'integer', new CommuneInZone('zone_id')],
            ...$this->locationRules(),
            'anonymous' => ['boolean'],
            'contactable' => ['boolean'],
            'contact' => ['exclude_unless:contactable,true', 'required', 'string', 'max:190', new PhoneOrEmail],
            'preferred_channel' => ['exclude_unless:contactable,true', 'required', Rule::enum(ContactChannel::class)],
            'attachments' => ['nullable', 'array', 'max:'.(int) config('xomiton.alerts.attachments_max', 5)],
            'attachments.*' => ['file', $this->attachmentRule()],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'needs.required_if' => (string) __('alerts.report.fields.needs_error'),
            'category.required_if' => (string) __('alerts.report.fields.category_error'),
            'occurred_on.before_or_equal' => (string) __('alerts.report.fields.date_future'),
            'description.min' => (string) __('alerts.report.fields.description_min', ['min' => (int) config('xomiton.alerts.description_min', 20)]),
        ];
    }

    /**
     * Données du cas d'usage CreateReport ; `$attachments` = fichiers déjà passés par MediaUploader (disque privé).
     *
     * @param  list<StoredMedia>  $attachments
     */
    public function toData(AlertSource $source = AlertSource::Web, array $attachments = [], ?int $deviceId = null): ReportData
    {
        $occurredOn = $this->stringOrNull('occurred_on');
        $contactable = $this->boolean('contactable');
        $user = $this->currentUser();

        return new ReportData(
            type: self::TYPES[(string) $this->input('type')],
            idempotencyKey: (string) $this->input('idempotency_key'),
            source: $source,
            userId: $user === null ? null : (int) $user->getKey(),
            anonymous: $this->boolean('anonymous', true),
            category: $this->stringOrNull('category'),
            description: (string) $this->input('description'),
            occurredOn: $occurredOn === null ? null : CarbonImmutable::parse($occurredOn)->startOfDay(),
            needs: $this->needSlugs(),
            zoneId: $this->intOrNull('zone_id'),
            communeId: $this->intOrNull('commune_id'),
            lat: $this->floatOrNull('lat'),
            lng: $this->floatOrNull('lng'),
            accuracy: $this->intOrNull('accuracy'),
            contactable: $contactable,
            contact: $contactable ? $this->stringOrNull('contact') : null,
            preferredChannel: $contactable ? ContactChannel::tryFrom((string) $this->input('preferred_channel')) : null,
            attachments: $attachments,
            deviceId: $deviceId,
        );
    }

    /** Chaque preuve est contrôlée dans la collection de son type réel (image, PDF, audio ; MediaUploader). */
    private function attachmentRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! $value instanceof UploadedFile) {
                $fail((string) __('uploads.rejected.not_a_file'));

                return;
            }

            (new UploadAllowed(AlertAttachment::kindForMime((string) $value->getMimeType())))->validate($attribute, $value, $fail);
        };
    }
}

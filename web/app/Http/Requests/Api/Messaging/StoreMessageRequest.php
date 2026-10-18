<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Messaging;

use App\Application\Messaging\SendMessage;
use App\Application\Shared\Exceptions\MediaRejected;
use App\Application\Shared\MediaUploader;
use App\Http\Requests\Api\Messaging\Concerns\MessagingRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * POST conversations/{ulid}/messages (P8-01) — JSON ou multipart : `{body, client_uuid, attachment?}`. Texte obligatoire
 * sauf pièce jointe seule, ≤ `body_max_length` (1000, prototype messaging.php l.80) ; `client_uuid` = UUID du client
 * (boîte d'envoi : même valeur que l'en-tête Idempotency-Key, rejouée à l'identique) ; pièce jointe : image
 * (`uploads.collections.images`) ou PDF (`documents`), type réel lu dans le contenu.
 */
final class StoreMessageRequest extends FormRequest
{
    use MessagingRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('sendMessage', $this->conversation());
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('client_uuid') && is_string($this->header('Idempotency-Key'))) {
            $this->merge(['client_uuid' => $this->header('Idempotency-Key')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required_without:attachment', 'nullable', 'string', 'max:'.(int) config('xomiton.messaging.body_max_length', 1000)],
            'client_uuid' => ['required', 'uuid'],
            'attachment' => ['nullable', 'file'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $file = $this->file('attachment');

            if (! $file instanceof UploadedFile || $validator->errors()->has('attachment')) {
                return;
            }

            try {
                app(MediaUploader::class)->inspect($file, SendMessage::collectionFor($file));
            } catch (MediaRejected $rejected) {
                $validator->errors()->add('attachment', $rejected->getMessage());
            }
        }, function (Validator $validator): void {
            $body = trim((string) $this->input('body'));

            if ($body === '' && ! $this->file('attachment') instanceof UploadedFile && ! $validator->errors()->has('body')) {
                $validator->errors()->add('body', (string) __('community.messages.errors.empty'));
            }
        }];
    }

    public function body(): ?string
    {
        $body = $this->validated('body');

        return is_string($body) ? $body : null;
    }

    public function clientUuid(): string
    {
        return strtolower((string) $this->validated('client_uuid'));
    }

    public function attachment(): ?UploadedFile
    {
        $file = $this->file('attachment');

        return $file instanceof UploadedFile ? $file : null;
    }
}

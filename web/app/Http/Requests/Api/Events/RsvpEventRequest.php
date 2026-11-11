<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Events;

use App\Http\Requests\Api\Events\Concerns\EventRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST events/{ulid}/rsvp `{going: bool, reminder?: bool}` (P8-04, simulateur.html l.1162) : « Je participe » ou
 * annuler ; `reminder` (oui par défaut) garde ou retire le rappel de la veille. L'accès à l'événement est vérifié par le
 * contrôleur (404 s'il est invisible, jamais 403).
 */
final class RsvpEventRequest extends FormRequest
{
    use EventRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('social.access');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'going' => ['required', 'boolean'],
            'reminder' => ['sometimes', 'boolean'],
        ];
    }

    public function going(): bool
    {
        return (bool) filter_var($this->validated('going'), FILTER_VALIDATE_BOOL);
    }

    public function reminder(): bool
    {
        return ! $this->has('reminder') || (bool) filter_var($this->validated('reminder'), FILTER_VALIDATE_BOOL);
    }
}

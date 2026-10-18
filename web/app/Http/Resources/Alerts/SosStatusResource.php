<?php

declare(strict_types=1);

namespace App\Http\Resources\Alerts;

use App\Application\Alerts\Dispatch\NotifiedPeople;
use App\Application\Alerts\Dispatch\NotifiedPerson;
use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * État d'un SOS vu par la personne qui l'a envoyé (`POST sos` 201, `GET sos/{id}`, `POST sos/{id}/cancel|safe`, P5-06) :
 * AlertResource (statut, contact routé, repentir, `sos_token` à la création d'un SOS anonyme) + `notified[]` (personnes
 * prévenues : NotifiedPeople et NotifiedPersonResource du lot P5-03) + `primary_contact` (personne à appeler en premier,
 * null → « Appeler le 117 »). `meta.poll_interval_seconds` : rythme de sondage de `GET sos/{id}` tant que le SOS est
 * ouvert et récent (`xomiton.alerts.sos_poll_seconds`, 10 s, pendant `sos_poll_minutes`, 30 min, après l'envoi), null
 * ensuite (annulé, en sécurité, traité, clos ou trop ancien).
 *
 * @property Alert $resource
 */
final class SosStatusResource extends JsonResource
{
    /**
     * @param  Collection<int, NotifiedPerson>  $notified
     */
    public function __construct(Alert $alert, private readonly Collection $notified, private readonly ?string $sosToken = null)
    {
        parent::__construct($alert);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $primary = NotifiedPeople::primaryContact($this->notified);

        return [
            ...AlertResource::make($this->resource)->withSosToken($this->sosToken)->resolve($request),
            'notified' => NotifiedPersonResource::collection($this->notified)->resolve($request),
            'primary_contact' => $primary === null ? null : NotifiedPersonResource::make($primary)->resolve($request),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        $alert = $this->resource;
        $since = now()->subMinutes((int) config('xomiton.alerts.sos_poll_minutes', 30));
        $polling = ! $alert->isCancelled() && ! $alert->isSafe() && $alert->status->isOpen()
            && ($alert->created_at === null || $alert->created_at->greaterThan($since));

        return ['meta' => ['poll_interval_seconds' => $polling ? (int) config('xomiton.alerts.sos_poll_seconds', 10) : null]];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Alerts\AcknowledgeAlert;
use App\Application\Shared\Referentials;
use App\Enums\AlertStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Alerts\AcknowledgeAlertRequest;
use App\Models\Alert;
use App\Models\DirectoryContact;
use Illuminate\Contracts\View\View;

/**
 * Accusé de réception par lien signé pour un contact d'annuaire sans compte (P5-02, gabarit sobre, vous). Le `GET`
 * n'écrit rien (les aperçus de liens des messageries ne doivent pas accuser réception à la place du contact : un faux
 * accusé arrêterait l'escalade d'un SOS) ; le `POST` (même URL signée, CSRF) pose l'accusé et révèle alors le moyen de
 * contact et, pour un SOS, la position — au seul destinataire vérifié. Revenue sur la page, la fiche qui a accusé
 * réception retrouve ces coordonnées par un nouveau `POST` (jamais au `GET`).
 */
final class AlertAcknowledgementController extends Controller
{
    public function __construct(private readonly Referentials $referentials) {}

    public function show(AcknowledgeAlertRequest $request, Alert $alert): View
    {
        /** @var DirectoryContact $contact */
        $contact = $request->contact();

        return $this->page($alert, self::state($alert, $contact));
    }

    /**
     * Pose l'accusé ; la fiche qui l'a déjà posé retrouve les coordonnées par ce même POST (état `mine` du GET).
     */
    public function store(AcknowledgeAlertRequest $request, Alert $alert, AcknowledgeAlert $acknowledge): View
    {
        /** @var DirectoryContact $contact */
        $contact = $request->contact();
        $result = $acknowledge->bySignedLink($alert, $contact);

        return $this->page($result->alert, match (true) {
            $result->closed => 'closed',
            $result->alreadyAcknowledged && ! $result->alert->wasAcknowledgedBy($contact) => 'already',
            default => 'done',
        });
    }

    private static function state(Alert $alert, DirectoryContact $contact): string
    {
        return match (true) {
            $alert->isCancelled() || $alert->status === AlertStatus::Closed => 'closed',
            $alert->wasAcknowledgedBy($contact) => 'mine',
            $alert->isAcknowledged() => 'already',
            default => 'confirm',
        };
    }

    private function page(Alert $alert, string $state): View
    {
        $alert->loadMissing(['zone', 'commune']);

        return view('alerts.acknowledge', [
            'alert' => $alert,
            'state' => $state,
            'needs' => $this->needLabels($alert),
        ]);
    }

    /**
     * @return list<string>
     */
    private function needLabels(Alert $alert): array
    {
        $labels = collect($this->referentials->needs())->pluck('label', 'slug');

        return array_values(array_map(static fn (string $slug): string => (string) ($labels[$slug] ?? $slug), $alert->needSlugs()));
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Assistant;

use App\Application\Alerts\CreateReport;
use App\Application\Alerts\Data\ReportData;
use App\Application\Alerts\RequestCallback;
use App\Application\Alerts\Results\CreatedAlert;
use App\Application\Shared\Referentials;
use App\Enums\AlertType;
use App\Models\User;

/**
 * « Parler à un humain » depuis l'assistant membre (P8-11, garde-fou de crise « escalade vers un humain ») : la demande
 * devient une alerte « besoin » ordinaire (CreateReport, P5-02) — routage RG-04 depuis le département et la commune du
 * compte, notification du contact routé (AlertRouted), accusé et escalade des alertes (P5-03), suivi dans
 * `/admin/alertes` onglet Besoins — avec le moyen de contact donné (obligatoire : une personne formée recontacte, sous
 * 24 h, VOICE_AND_TONE.md §3 règle 6). Besoin `xomiton.assistant.escalation_need` (repli `autre`), message facultatif
 * (texte neutre sinon), pseudo transmis seulement si la personne le souhaite (`anonymous = false`). Le contenu de la
 * conversation n'est **jamais** copié dans l'alerte ; une conversation enregistrée (`conversation_id`, à la personne,
 * 404 sinon) est marquée pour relecture et escaladée (`escalated_at`, écran P12-07) **seulement si le pseudo est
 * transmis** : la conversation porte le compte, la dater au moment d'une alerte anonyme permettrait de les rapprocher
 * (choix d'anonymat annulé) ; `conversation_id` est donc ignoré d'une demande anonyme. Idempotence ADR-32 : un rejeu
 * renvoie la même alerte sans toucher à nouveau la conversation.
 */
final class EscalateToHuman
{
    public function __construct(
        private readonly CreateReport $createReport,
        private readonly Referentials $referentials,
    ) {}

    public function __invoke(EscalationData $data): CreatedAlert
    {
        $user = User::query()->findOrFail($data->userId);
        $conversation = $data->anonymous || $data->conversationId === null
            ? null
            : AskAssistant::conversationOf($user, $data->conversationId);
        $message = trim((string) $data->message);

        $result = ($this->createReport)(new ReportData(
            type: AlertType::Need,
            idempotencyKey: $data->idempotencyKey,
            source: $data->source,
            userId: (int) $user->getKey(),
            anonymous: $data->anonymous,
            category: null,
            description: $message === '' ? (string) __('assistant.escalation.default_message') : $message,
            occurredOn: null,
            needs: [$this->need()],
            zoneId: $user->zone_id === null ? null : (int) $user->zone_id,
            communeId: $user->commune_id === null ? null : (int) $user->commune_id,
            contactable: true,
            contact: $data->contact,
            preferredChannel: $data->preferredChannel,
            deviceId: $data->anonymous ? null : $data->deviceId,
        ));

        if (! $result->duplicate && $conversation !== null) {
            if (! $conversation->is_flagged) {
                $conversation->flag((string) __('assistant.escalation.flag_reason'));
            }

            $conversation->escalate();
        }

        return $result;
    }

    /** Besoin configuré s'il existe encore parmi les besoins actifs, sinon « Autre besoin ». */
    private function need(): string
    {
        $wanted = (string) config('xomiton.assistant.escalation_need', '');
        $slugs = array_column($this->referentials->needs(), 'slug');

        return in_array($wanted, $slugs, true) ? $wanted : RequestCallback::FALLBACK_NEED;
    }
}

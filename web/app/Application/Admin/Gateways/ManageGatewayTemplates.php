<?php

declare(strict_types=1);

namespace App\Application\Admin\Gateways;

use App\Models\GatewayTemplate;
use App\Models\User;
use App\Support\Audit;

/**
 * Modèles de messages clés des passerelles (P12-08, permission `gateways.publish`) : titre de 3 à 80 caractères, texte de
 * 20 à 500, actif / inactif. Un modèle inactif n'est plus proposé à la planification ; les publications déjà planifiées
 * gardent leur texte.
 */
final class ManageGatewayTemplates
{
    /**
     * @param  array{title: string, body: string, is_active: bool}  $data
     */
    public function save(?GatewayTemplate $template, array $data, User $actor): GatewayTemplate
    {
        $template ??= new GatewayTemplate;
        $creating = ! $template->exists;

        $template->fill([
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'is_active' => $data['is_active'],
        ]);

        if ($creating) {
            $template->position = (int) GatewayTemplate::query()->max('position') + 1;
        }

        $template->save();
        Audit::log($creating ? 'gateway.template_created' : 'gateway.template_updated', $template, $template->title, $actor);

        return $template;
    }

    public function delete(GatewayTemplate $template, User $actor): void
    {
        $template->delete();
        Audit::log('gateway.template_deleted', $template, $template->title, $actor);
    }
}

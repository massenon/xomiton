<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch;

use App\Application\Alerts\Dispatch\Recipients\RecipientTarget;
use App\Application\Alerts\Support\AcknowledgeLink;
use App\Domain\Alerts\Dispatch\RecipientKind;
use App\Models\Alert;
use Illuminate\Support\Facades\Route;

/**
 * Lien d'ouverture d'une notification d'alerte selon la personne (P5-03) : fiche d'annuaire sans compte → lien signé
 * d'accusé de réception (P5-02, seul moyen d'accuser sans compte ; il n'ouvre jamais la position avant le POST) ;
 * compte avec accès à l'administration → page admin des alertes ; autre compte lié (pair·e) → espace pair ;
 * auteur·e → page SOS ou suivi du signalement.
 */
final class AlertLinks
{
    private function __construct() {}

    public static function for(Alert $alert, RecipientTarget $target): string
    {
        if ($target->kind === RecipientKind::Author) {
            return $alert->isSos() ? self::route('sos', '/sos') : self::route('signaler', '/signaler').'#suivi';
        }

        if (! $target->hasAccount() && $target->contact !== null) {
            // Lien signé réservé au contact routé et aux cibles d'escalade (AcknowledgeLink::matches) : un autre point
            // focal prévenu en copie reçoit l'annuaire d'urgence, et un SMS sans « Accusez réception » (sms_copy).
            return self::canAcknowledge($alert, $target)
                ? AcknowledgeLink::url($alert, $target->contact)
                : self::route('urgences', '/urgences');
        }

        if ($target->user !== null && $target->user->can('admin.access')) {
            return self::route('admin.alertes', '/admin/alertes');
        }

        return self::route('espace-pair', '/espace-pair');
    }

    /**
     * La personne peut-elle accuser réception depuis sa notification ? Toujours avec un compte ; une fiche sans compte
     * seulement si AcknowledgeLink::matches (contact routé, cible d'escalade). Élargir aux fiches prévenues en copie
     * (focal de zone, focal national de repli) relève de P5-02 (Q57).
     */
    public static function canAcknowledge(Alert $alert, RecipientTarget $target): bool
    {
        if ($target->hasAccount() || $target->contact === null || $target->kind === RecipientKind::Trusted) {
            return true;
        }

        return AcknowledgeLink::matches($alert, $target->contact);
    }

    private static function route(string $name, string $fallback): string
    {
        return Route::has($name) ? route($name) : url($fallback);
    }
}

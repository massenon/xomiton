<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mode discret (2026-10-28) : plus de code, référence, zone, titre ni date dans l'objet ou le pré-en-tête des
     * modèles système. Seuls les textes encore identiques aux anciens défauts sont remplacés (les modèles édités
     * en administration ne sont pas touchés).
     *
     * @var array<string, array{0: string, 1: ?string, 2: string, 3: ?string}>
     */
    private const CHANGES = [
        'reinitialisation-mot-de-passe' => ['{{site_name}} : ton code pour choisir un nouveau mot de passe', 'Code {{otp_code}}, valable {{otp_expiry}}.', '{{site_name}} : choisis un nouveau mot de passe', 'Ton code et ton lien sont à l’intérieur.'],
        'code-verification' => ['Ton code {{site_name}} : {{otp_code}}', 'Code valable {{otp_expiry}}.', 'Ton code de vérification {{site_name}}', 'Ouvre ce message pour le voir.'],
        'alerte-routee' => ['[{{site_name}}] Nouvelle alerte {{alert_reference}} — {{alert_zone}}', null, '[{{site_name}}] Nouvelle alerte à traiter', null],
        'rappel-evenement' => ['Rappel : {{event_title}}', '{{event_date}}', 'Rappel : un événement t’attend demain', 'Ouvre ce message pour les détails.'],
        'newsletter-confirmation' => ['Confirmez votre inscription à la newsletter {{site_name}}', null, 'Confirmez votre adresse e-mail', null],
        'rapport-genere' => ['{{site_name}} : {{report_title}}', 'Période {{report_period}}, format {{report_format}}.', '{{site_name}} : votre rapport est prêt', 'Le détail est dans le message.'],
    ];

    public function up(): void
    {
        foreach (self::CHANGES as $slug => [$oldSubject, $oldPre, $newSubject, $newPre]) {
            DB::table('email_templates')->where('slug', $slug)->where('subject', $oldSubject)->update(['subject' => $newSubject]);

            if ($oldPre !== null) {
                DB::table('email_templates')->where('slug', $slug)->where('preheader', $oldPre)->update(['preheader' => $newPre]);
            }
        }
    }

    public function down(): void {}
};

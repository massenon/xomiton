<?php

declare(strict_types=1);

use App\Models\Setting;

/*
 * Paramètres par défaut de la plateforme (lot 11) : les 11 clés de prototype_social/data/settings.json et les clés
 * écrites par les écrans d'administration du prototype (parametres.php, inscriptions.php, emails.php,
 * synchronisation.php, assistant.php, mises-a-jour.php), avec leur type et leur groupe.
 * Format : clé => [groupe, type, valeur par défaut, public ?].
 */
$assistantPrompt = "Tu es l’assistant de Xomiton, un espace sûr pour les filles, les femmes et les personnes LBTQ+ au Bénin.\n\n"
    ."TON : bienveillant, chaleureux, sans jugement, en français simple. Tutoie ou vouvoie selon l’interlocutrice. Phrases courtes.\n\n"
    .'SUJETS AUTORISÉS : violences basées sur le genre (VBG), santé sexuelle et reproductive, contraception, IST/VIH, consentement, '
    ."hygiène menstruelle, droits et lois du Bénin, santé mentale, orientation vers l’annuaire Xomiton.\n\n"
    ."GARDE-FOUS :\n"
    ."- Ne pose jamais de diagnostic médical ni psychologique et ne prescris aucun médicament.\n"
    ."- Ne donne pas de conseil juridique définitif : explique la loi et oriente vers un·e juriste de l’annuaire.\n"
    ."- Ne demande jamais d’informations permettant d’identifier la personne.\n"
    ."- Refuse poliment les sujets hors périmètre.\n\n"
    .'ESCALADE : si la personne évoque un danger immédiat, des idées suicidaires ou une violence en cours, réponds avec empathie, '
    .'rappelle le bouton SOS et propose de contacter un·e humain·e de l’annuaire.';

return [

    // data/settings.json (11 clés)
    'site_name' => [Setting::GROUP_GENERAL, Setting::TYPE_STRING, 'Xomiton', true],
    'contact_email' => [Setting::GROUP_GENERAL, Setting::TYPE_STRING, 'contact@xomiton.bj', true],
    'platform_version' => [Setting::GROUP_GENERAL, Setting::TYPE_STRING, '1.4.2', true],
    'last_update' => [Setting::GROUP_GENERAL, Setting::TYPE_STRING, '2026-09-28', false],
    'anonymous_reports' => [Setting::GROUP_GENERAL, Setting::TYPE_BOOL, true, true],
    'gps_sos' => [Setting::GROUP_GENERAL, Setting::TYPE_BOOL, true, true],
    'offline_sync' => [Setting::GROUP_SYNC, Setting::TYPE_BOOL, true, true],
    'assistant_enabled' => [Setting::GROUP_ASSISTANT, Setting::TYPE_BOOL, true, true],
    'maintenance' => [Setting::GROUP_MAINTENANCE, Setting::TYPE_BOOL, false, true],
    'registrations_open' => [Setting::GROUP_REGISTRATIONS, Setting::TYPE_BOOL, true, true],
    'manual_validation' => [Setting::GROUP_REGISTRATIONS, Setting::TYPE_BOOL, false, false],

    // admin/parametres.php — généraux
    'timezone' => [Setting::GROUP_GENERAL, Setting::TYPE_STRING, 'Africa/Porto-Novo', true],
    'languages' => [Setting::GROUP_GENERAL, Setting::TYPE_JSON, ['fr'], true],
    'default_lang' => [Setting::GROUP_GENERAL, Setting::TYPE_STRING, 'fr', true],

    // admin/parametres.php — sécurité
    'password_policy' => [Setting::GROUP_SECURITY, Setting::TYPE_JSON, ['min_len' => 8, 'upper' => true, 'digit' => true, 'symbol' => false, 'expiry_days' => 0], false],
    'session_minutes' => [Setting::GROUP_SECURITY, Setting::TYPE_INT, 120, false],
    'twofa' => [Setting::GROUP_SECURITY, Setting::TYPE_JSON, ['on' => false, 'method' => 'sms', 'admins_only' => true], false],

    // admin/emails.php — serveur SMTP (x_smtp_config() : une clé par champ, mot de passe chiffré)
    'smtp_host' => [Setting::GROUP_SMTP, Setting::TYPE_STRING, '', false],
    'smtp_port' => [Setting::GROUP_SMTP, Setting::TYPE_INT, 587, false],
    'smtp_encryption' => [Setting::GROUP_SMTP, Setting::TYPE_STRING, 'tls', false],
    'smtp_username' => [Setting::GROUP_SMTP, Setting::TYPE_STRING, '', false],
    'smtp_password' => [Setting::GROUP_SMTP, Setting::TYPE_SECRET, null, false],
    'smtp_from_email' => [Setting::GROUP_SMTP, Setting::TYPE_STRING, 'contact@xomiton.bj', false],
    'smtp_from_name' => [Setting::GROUP_SMTP, Setting::TYPE_STRING, 'Xomiton', false],
    'smtp_reply_to' => [Setting::GROUP_SMTP, Setting::TYPE_STRING, '', false],

    // admin/synchronisation.php
    'sync_settings' => [Setting::GROUP_SYNC, Setting::TYPE_JSON, ['interval' => 15, 'encrypt' => true, 'max_mb' => 5, 'wifi_only' => false], false],

    // admin/inscriptions.php
    'reg_closed_msg' => [Setting::GROUP_REGISTRATIONS, Setting::TYPE_STRING, 'Les inscriptions sont momentanément fermées. Revenez bientôt ou contactez-nous.', true],
    'reg_reopen_at' => [Setting::GROUP_REGISTRATIONS, Setting::TYPE_STRING, null, true],
    'reg_window_on' => [Setting::GROUP_REGISTRATIONS, Setting::TYPE_BOOL, false, true],
    'reg_window_start' => [Setting::GROUP_REGISTRATIONS, Setting::TYPE_STRING, '08:00', true],
    'reg_window_end' => [Setting::GROUP_REGISTRATIONS, Setting::TYPE_STRING, '20:00', true],

    // admin/mises-a-jour.php — maintenance (la fenêtre détaillée vit dans maintenance_windows)
    'maint_start' => [Setting::GROUP_MAINTENANCE, Setting::TYPE_STRING, null, false],
    'maint_end' => [Setting::GROUP_MAINTENANCE, Setting::TYPE_STRING, null, false],
    'maint_msg' => [Setting::GROUP_MAINTENANCE, Setting::TYPE_STRING, 'Xomiton est en maintenance. Nous revenons dès que possible.', true],

    // admin/mises-a-jour.php — tâches d'optimisation (date et résultat de la dernière exécution par tâche)
    'optim_state' => [Setting::GROUP_OPTIMIZATION, Setting::TYPE_JSON, [], false],

    // admin/assistant.php
    'assistant_prompt' => [Setting::GROUP_ASSISTANT, Setting::TYPE_STRING, $assistantPrompt, false],
    'assistant_guardrails' => [Setting::GROUP_ASSISTANT, Setting::TYPE_JSON, [
        'no_diagnosis' => true, 'no_pii' => true, 'topic_filter' => true, 'sos_banner' => true, 'log_anon' => true, 'disclaimer' => true,
    ], false],
    'assistant_escalation' => [Setting::GROUP_ASSISTANT, Setting::TYPE_JSON, [
        'on' => true, 'keywords' => 'suicide, me tuer, il va me tuer, danger, urgence, frappée', 'target' => 'Point focal de la zone',
    ], false],

];

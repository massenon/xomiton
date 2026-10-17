<?php

declare(strict_types=1);

/*
 * Notifications (NOTIFICATIONS_INSTRUCTION.md §2-§4) : titre et corps courts par événement, pour la cloche, le push
 * et le SMS de secours. Les e-mails passent par les templates en base (email_templates) et ne sont pas ici.
 * Registre (Q36) : destinataire membre → tu ; destinataire de l'annuaire ou de l'équipe (`alert_routed`, `sos_*`,
 * `admin`) → vous. Discrétion (§4.1) : chaque événement a une variante `discreet` sans donnée sensible, utilisée sur
 * écran verrouillé ou si « masquer le contenu sensible » est actif ; le verbe reprend celui du bouton (§4.6).
 */
return [

    'discreet' => [
        'title' => 'Xomiton',
        'body' => 'Nouvelle notification',
    ],

    'account_created' => [
        'title' => 'Bienvenue sur Xomiton',
        'body' => 'Ton compte est créé. Confirme ton e-mail pour profiter de tout ton espace.',
        'discreet' => 'Compte créé',
    ],

    'otp_requested' => [
        'title' => 'Ton code de vérification',
        'body' => 'Un code vient d’être envoyé à :email. Il est valable :minutes minutes.',
        'discreet' => 'Code envoyé',
    ],

    'password_reset' => [
        'title' => 'Réinitialisation du mot de passe',
        'body' => 'Si un compte correspond à cette adresse, un code vient d’être envoyé. Si tu n’as rien demandé, ignore ce message.',
        'discreet' => 'Sécurité du compte',
    ],

    'password_changed' => [
        'title' => 'Mot de passe modifié',
        'body' => 'Ton mot de passe a été changé le :date. Si ce n’est pas toi, réinitialise-le tout de suite.',
        'discreet' => 'Sécurité du compte',
    ],

    // Compte pair validé : titre et corps dans auth.peer_validated (ne pas dupliquer).

    'alert_routed' => [
        'title' => 'Nouvelle alerte à prendre en charge',
        'body' => ':type · :zone · reçue à :time. Ouvrez l’alerte pour accuser réception.',
        'discreet' => 'Nouvelle alerte',
    ],

    'sos_triggered' => [
        'title' => 'Alerte SOS',
        'body' => 'Une personne a besoin d’aide près de :place. Accusez réception puis appelez-la.',
        'body_with_pseudo' => ':pseudo a besoin d’aide près de :place. Accusez réception puis appelez-la.',
        'body_no_position' => 'Une personne a besoin d’aide (position indisponible, zone : :zone). Accusez réception puis appelez-la.',
        'discreet' => 'Alerte urgente',
        'sms' => 'Xomiton – Alerte SOS :code près de :place. Accusez réception : :url',
        'sms_no_position' => 'Xomiton – Alerte SOS :code, zone :zone. Accusez réception : :url',
    ],

    'sos_unacknowledged' => [
        'title' => 'SOS sans réponse depuis :minutes min',
        'body' => 'Aucun accusé de réception pour l’alerte :code. Prenez le relais.',
        'discreet' => 'Alerte urgente',
        'sms' => 'Xomiton – SOS :code sans réponse depuis :minutes min. Prenez le relais : :url',
    ],

    'sos_cancelled' => [
        'title' => 'Fausse alerte',
        'body' => 'L’alerte :code a été annulée par la personne qui l’avait envoyée. Aucune action n’est attendue.',
        'discreet' => 'Alerte annulée',
    ],

    'sos_safe' => [
        'title' => 'Personne en sécurité',
        'body' => 'La personne à l’origine de l’alerte :code indique être en sécurité.',
        'discreet' => 'Mise à jour d’alerte',
    ],

    'alert_status_changed' => [
        'title' => 'Ton signalement a avancé',
        'body' => 'Statut du signalement :code : :status.',
        'discreet' => 'Mise à jour',
    ],

    'new_message' => [
        'title' => 'Nouveau message',
        'body' => ':name : :preview',
        'body_group' => ':name dans :conversation : :preview',
        'discreet' => 'Nouveau message',
        'count' => '{1} :count nouveau message|[2,*] :count nouveaux messages',
    ],

    'reaction' => [
        'title' => 'Réactions à ta publication',
        'liked_one' => ':name a aimé ta publication.',
        'liked_many' => ':name et :count autres ont aimé ta publication.',
        'commented_one' => ':name a commenté ta publication.',
        'commented_many' => ':name et :count autres ont commenté ta publication.',
        'replied' => ':name a répondu à ton commentaire.',
        'discreet' => 'Nouvelle activité',
    ],

    'event_reminder' => [
        'title' => 'Rappel : :title',
        'body' => 'C’est demain à :time, :place.',
        'body_online' => 'C’est demain à :time, en ligne.',
        'discreet' => 'Rappel d’événement',
    ],

    'event_created' => [
        'title' => 'Nouvel événement',
        'body' => ':title, le :date à :time.',
        'discreet' => 'Nouvel événement',
    ],

    'poll_closed' => [
        'title' => 'Sondage terminé',
        'body' => '« :question » : :votes votes. Découvre les résultats.',
        'discreet' => 'Sondage terminé',
    ],

    'invitation_received' => [
        'title' => 'Nouvelle invitation',
        'body' => ':name t’a envoyé une invitation.',
        'discreet' => 'Nouvelle invitation',
    ],

    'invitation_accepted' => [
        'title' => 'Invitation acceptée',
        'body' => ':name a accepté ton invitation. Vous êtes maintenant connecté·es.',
        'discreet' => 'Invitation acceptée',
    ],

    'new_follower' => [
        'title' => 'Nouvel abonnement',
        'body' => ':name suit maintenant tes publications.',
        'discreet' => 'Nouvelle activité',
    ],

    'forum_reply' => [
        'title' => 'Nouvelle réponse',
        'body' => ':name a répondu au sujet « :title ».',
        'discreet' => 'Nouvelle réponse',
    ],

    'group_request' => [
        'title' => 'Demande d’adhésion',
        'body' => ':name demande à rejoindre « :group ».',
        'discreet' => 'Demande d’adhésion',
    ],

    'group_accepted' => [
        'title' => 'Bienvenue dans le groupe',
        'body' => 'Tu fais maintenant partie de « :group ».',
        'discreet' => 'Groupe rejoint',
    ],

    'page_approved' => [
        'title' => 'Page approuvée',
        'body' => 'Ta page « :page » est visible par la communauté.',
        'discreet' => 'Page approuvée',
    ],

    'content_moderated' => [
        'title' => 'Contenu masqué',
        'body' => 'Ta publication du :date a été masquée par la modération : :reason. Tu peux contacter l’équipe.',
        'discreet' => 'Mise à jour de modération',
    ],

    'new_resource' => [
        'title' => 'Nouvelle fiche',
        'body' => '« :title » est disponible dans :theme.',
        'discreet' => 'Nouvelle ressource',
    ],

    'digest' => [
        'daily_title' => 'Ton résumé du jour',
        'weekly_title' => 'Ton résumé de la semaine',
        'body' => ':posts nouvelles publications, :messages messages non lus, :events événements à venir.',
        'empty' => 'Rien de nouveau cette fois. À bientôt sur Xomiton.',
    ],

    'system' => [
        'title' => 'Xomiton',
        'update' => 'Mise à jour de la plateforme : :summary',
        'security_reminder' => 'Rappel sécurité : active le code PIN dans tes paramètres.',
        'maintenance' => 'Maintenance prévue le :date de :from à :to. Tes brouillons restent sur ton appareil.',
    ],

    // Cloche de l'administration (NOTIFICATIONS_INSTRUCTION.md §3, prototype xa_notifications()) : vous.
    'admin' => [
        'new_report' => 'Nouveau signalement · :category',
        'new_sos' => 'Alerte SOS · :zone',
        'new_need' => 'Nouvelle demande · :category',
        'alert_handled' => 'Alerte traitée · :category',
        'registration_pending' => 'Inscription à valider · :name',
        'moderation_pending' => 'Signalement à modérer · :reason',
        'sync_conflict' => 'Conflit de synchronisation · :code',
        'import_done' => 'Import terminé · :count lignes',
        'report_ready' => 'Rapport prêt · :title',
        'backup_done' => 'Sauvegarde terminée · :date',
        'queue_failed' => 'Échec d’envoi · :count e-mails en attente',
    ],

    // Page /notifications et cloche membre (P8-10, prototype notifications.php).
    'page' => [
        'title' => 'Notifications',
        'subtitle' => 'Ton activité récente sur Xomiton.',
        'unread_badge' => '{1} :count non lue|[2,*] :count non lues',
        'new_badge' => '{1} :count nouvelle|[2,*] :count nouvelles',
        'filters' => [
            'all' => 'Toutes',
            'invitations' => 'Invitations',
            'events' => 'Événements',
            'exchanges' => 'Échanges',
            'system' => 'Système',
        ],
        'mark_read' => 'Marquer comme lu',
        'mark_all_read' => 'Tout marquer comme lu',
        'all_read' => 'Toutes les notifications sont marquées comme lues.',
        'empty' => 'Aucune notification.',
        'empty_filter' => 'Aucune notification dans cette catégorie.',
        'see_all' => 'Voir toute l’activité',
        'clear' => 'Tout effacer',
    ],

    // Préférences (NOTIFICATIONS_INSTRUCTION.md §4.3 : sécurité du compte et SOS non désactivables).
    'preferences' => [
        'title' => 'Notifications',
        'intro' => 'Choisis ce qui te prévient. La sécurité du compte et le SOS restent toujours actifs.',
        'categories' => [
            'invitations' => 'Invitations et nouvelles connexions',
            'messages' => 'Nouveaux messages',
            'reactions' => 'Réactions et commentaires',
            'events' => 'Rappels d’événements',
            'polls' => 'Sondages terminés',
            'resources' => 'Nouvelles fiches et ressources',
            'security' => 'Sécurité du compte (toujours actif)',
            'sos' => 'SOS (toujours actif)',
        ],
        'channels' => [
            'push' => 'Notifications dans l’application',
            'email' => 'Notifications par e-mail',
            'sound' => 'Son et vibration',
        ],
        'digest' => 'Résumé d’activité',
        'digest_options' => [
            'never' => 'Jamais',
            'daily' => 'Quotidien',
            'weekly' => 'Hebdomadaire',
        ],
        'mask_sensitive' => 'Masquer le contenu sensible dans les notifications',
        'mask_sensitive_help' => 'Sur l’écran verrouillé, seul « Nouveau message » s’affiche.',
    ],

];

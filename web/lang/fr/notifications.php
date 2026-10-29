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
        // Rappel à 24 h sans accusé (Q27, P5-03).
        'reminder_title' => 'Rappel : alerte à prendre en charge',
    ],

    'sos_triggered' => [
        'title' => 'Alerte SOS',
        'body' => 'Une personne a besoin d’aide près de :place. Accusez réception puis appelez-la.',
        'body_with_pseudo' => ':pseudo a besoin d’aide près de :place. Accusez réception puis appelez-la.',
        'body_no_position' => 'Une personne a besoin d’aide (position indisponible, zone : :zone). Accusez réception puis appelez-la.',
        'discreet' => 'Alerte urgente',
        'sms' => 'Xomiton – Alerte SOS :code près de :place. Accusez réception : :url',
        'sms_no_position' => 'Xomiton – Alerte SOS :code, zone :zone. Accusez réception : :url',
        // Fiche d'annuaire prévenue en copie, sans accusé possible (AcknowledgeLink::matches faux : focal de zone distinct
        // du contact routé, focal national de repli) : ni « Accusez réception » ni lien, l'équipe coordonne (Q57).
        'sms_copy' => 'Xomiton – Alerte SOS :code (:where). L’équipe Xomiton coordonne la réponse : restez joignable, elle peut vous solliciter.',
        // Contact de confiance (personne sans compte) : vous, sans lien d'administration, consigne d'appel.
        // Textes SMS : lettres de l'alphabet GSM-7 (pas de « ê » ni de point médian), typographie ramenée par SmsText.
        'sms_trusted' => 'Xomiton – SOS de :pseudo (:where), qui compte sur vous comme contact de confiance : appelez-la tout de suite. Réf. :code',
        'sms_trusted_unnamed' => 'Xomiton – SOS (:where) d’une personne qui compte sur vous comme contact de confiance. Appelez-la tout de suite. Réf. :code',
        'where_unknown' => 'position indisponible',
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
        'sms' => 'Xomiton – Fausse alerte : l’alerte :code a été annulée par la personne. Aucune action attendue.',
    ],

    'sos_safe' => [
        'title' => 'Personne en sécurité',
        'body' => 'La personne à l’origine de l’alerte :code indique être en sécurité.',
        'discreet' => 'Mise à jour d’alerte',
        'sms' => 'Xomiton – La personne à l’origine de l’alerte :code se dit en sécurité.',
    ],

    // Retour à l'auteur·e d'un SOS quand une personne prévenue accuse réception (P5-03) : tu.
    'sos_acknowledged' => [
        'title' => 'Ton alerte a été reçue',
        'body' => ':name a bien reçu ton alerte et va t’appeler.',
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
        'liked_many' => '{1} :name et :count autre personne ont aimé ta publication.|[2,*] :name et :count autres ont aimé ta publication.',
        'commented_one' => ':name a commenté ta publication.',
        'commented_many' => '{1} :name et :count autre personne ont commenté ta publication.|[2,*] :name et :count autres ont commenté ta publication.',
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
        'body_group' => ':title (:group), le :date à :time.',
        'discreet' => 'Nouvel événement',
    ],

    'poll_closed' => [
        'title' => 'Sondage terminé',
        'body' => '{0} « :question » : aucun·e participant·e. Découvre les résultats.|{1} « :question » : :count participant·e. Découvre les résultats.|[2,*] « :question » : :count participant·es. Découvre les résultats.',
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

    // Cloche de l'administration (P3-02 ContentReported, SyncConflict) : vous.
    'content_reported' => [
        'title' => 'Contenu signalé',
        'body' => ':kind à modérer · motif : :reason.',
        'discreet' => 'Signalement à modérer',
    ],

    // Rôles donnés ou retirés, demande de pair refusée (un seul compte, rôles cumulables) : tu. Le motif est écrit pour la personne.
    'role' => [
        'no_reason' => 'non précisé',
        'peer_granted' => [
            'title' => 'Tu es pair éducateur·rice',
            'body' => 'L’équipe Xomiton t’a donné le rôle de pair éducateur·rice. Tu restes membre : l’espace pair s’ajoute à ton compte.',
        ],
        'peer_revoked' => [
            'title' => 'Ton rôle de pair a été retiré',
            'body' => 'L’espace pair n’est plus disponible sur ton compte. Tu restes membre de la communauté.',
        ],
        'peer_refused' => [
            'title' => 'Ta demande de rôle de pair n’a pas été retenue',
            'body' => 'L’équipe n’a pas retenu ta demande : :reason. Tu restes membre de la communauté.',
        ],
        'admin_granted' => [
            'title' => 'Tu rejoins l’équipe d’administration',
            'body' => 'L’espace d’administration s’ajoute à ton compte. Tu restes membre de la communauté.',
        ],
        'admin_revoked' => [
            'title' => 'Ton accès à l’administration a été retiré',
            'body' => 'L’espace d’administration n’est plus disponible sur ton compte. Tu restes membre de la communauté.',
        ],
    ],

    // Cloche de l'administration (D-MOB-15) : vous. Jamais le motif de la personne.
    'role_requested' => [
        'title' => 'Nouvelle demande de rôle de pair·e',
        'body' => ':name demande le rôle de pair éducateur·rice. Ouvrez la file des inscriptions pour répondre.',
    ],

    // Décisions de l'équipe adressées à la personne (P12-01, P12-06) : tu. Le motif est celui écrit par l'équipe pour elle.
    'moderation' => [
        'discreet' => 'Message de l’équipe',
        'no_reason' => 'non précisé',
        'warning' => [
            'title' => 'Avertissement de l’équipe',
            'body' => 'L’équipe de modération t’adresse un avertissement : :reason. Relis la charte pour que la communauté reste un espace sûr.',
        ],
        'content_hidden' => [
            'title' => 'Un de tes contenus a été masqué',
            'body' => 'L’équipe de modération a masqué un de tes contenus, car il ne respecte pas la charte. Tu peux nous écrire si tu penses à une erreur.',
        ],
        'suspended' => [
            'title' => 'Ton compte est suspendu',
            'body' => 'Ton compte est suspendu : :reason. Écris-nous si tu penses à une erreur.',
        ],
        'reactivated' => [
            'title' => 'Ton compte est réactivé',
            'body' => 'L’équipe a réactivé ton compte. Tu peux de nouveau t’en servir.',
        ],
        'approved' => [
            'title' => 'Ton inscription est validée',
            'body' => 'L’équipe Xomiton a validé ton inscription : bienvenue dans la communauté.',
        ],
    ],

    // Limiteur `sos` en mode observateur (P2-08, App\Application\Shared\SosBurstWatch) : aucun SOS n'est bloqué.
    'sos_burst' => [
        'title' => 'SOS répétés depuis une même adresse',
        'body' => ':count SOS en une minute depuis une même adresse (empreinte :fingerprint). Aucun envoi n’a été bloqué : vérifiez les alertes récentes.',
        'discreet' => 'SOS répétés à vérifier',
    ],

    'sync_conflict' => [
        'title' => 'Conflit de synchronisation',
        'body' => ':subject · champ « :field » : deux versions à arbitrer.',
        'discreet' => 'Conflit de synchronisation',
    ],

    // Catégories stockées (`notifications.category`, `user_settings.notification_prefs`, config xomiton.notifications.categories)
    // et canaux de préférence (`in_app`, `email`, `push`) : libellés pour l'API et l'écran Paramètres.
    'categories' => [
        'securite' => 'Sécurité du compte',
        'sos' => 'SOS',
        'alertes' => 'Alertes routées',
        'messages' => 'Messages',
        'communaute' => 'Communauté',
        'evenements' => 'Événements',
        'moderation' => 'Modération',
        'systeme' => 'Système',
    ],
    'channels' => [
        'in_app' => 'Cloche dans l’application',
        'email' => 'E-mail',
        'push' => 'Push (navigateur et mobile)',
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
        // P8-10 (lot parametres-notifications) : navigation des filtres, état non lu, suite de la liste.
        'filters_label' => 'Filtrer les notifications',
        'unread' => 'Non lue',
        'mark_read_label' => 'Marquer comme lue : :title',
        'load_more' => 'Afficher plus de notifications',
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

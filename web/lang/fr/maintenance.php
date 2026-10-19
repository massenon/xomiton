<?php

declare(strict_types=1);

/*
 * Mode maintenance applicatif (RG-14, P2-11 ; P13-06 écran d'administration, P13-07 page publique, P13-08 commande).
 * Registre (VOICE_AND_TONE.md §3, Q36) : page publique 503 (pages d'erreur du site public) et administration → vous,
 * y compris le message d'une session fermée (un seul registre par écran). Exclu du catalogue Vue
 * (xomiton.lang_export.vue_exclude) : l'écran membre XmMaintenance tutoie et lit microcopy.maintenance ; le bandeau
 * d'annonce partagé (neutre) vit dans components.maintenance_notice.
 */
return [

    'page_title' => 'Maintenance en cours',
    'meta_description' => 'Xomiton est momentanément en maintenance. Le SOS, le signalement et les contacts d’urgence restent disponibles.',
    'skip' => 'Aller au contenu',
    'eyebrow' => 'Maintenance en cours',
    'title_lead' => 'Une pause pour',
    'title_mark' => 'mieux vous accueillir',
    'default_message' => 'Xomiton est en maintenance. Nous revenons dès que possible.',
    'disconnected' => 'Pour protéger votre compte, votre session a été fermée. Vos brouillons restent sur votre appareil : reconnectez-vous dès la fin de la maintenance.',

    'status' => [
        'title' => 'État de la maintenance',
        'ends' => 'Reprise prévue',
        'remaining' => 'Temps restant',
        'started' => 'Commencée',
        'soon' => 'Bientôt',
        'progress' => 'Avancement estimé de la maintenance',
        'check' => 'Cette page vérifie la réouverture chaque minute et se recharge toute seule.',
        'check_nojs' => 'Actualisez la page pour vérifier si la plateforme a rouvert.',
        'reopened' => 'Xomiton est de nouveau disponible. Chargement de la page…',
        'overdue' => 'La reprise prend un peu plus de temps que prévu. Merci de votre patience.',
        'remaining_live' => 'Reprise prévue dans :minutes minutes.',
    ],

    // Phrases en clair (lisibles sans JavaScript) : « Reprise prévue aujourd’hui à 16 h 30. »
    'since' => 'Maintenance commencée :when.',
    'until' => 'Reprise prévue :when.',
    'until_unknown' => 'Nous n’avons pas encore d’heure de reprise à annoncer. Réessayez dans quelques minutes.',
    'when' => [
        'today' => 'aujourd’hui à :time',
        'tomorrow' => 'demain à :time',
        'date' => 'le :date à :time',
    ],
    'day_first' => '1er',

    'always_available' => [
        'title' => 'En danger ou besoin d’aide maintenant ?',
        'text' => 'Le SOS, le signalement et les contacts d’urgence restent disponibles pendant la maintenance, avec ou sans compte.',
        'nav' => 'Accès toujours disponibles',
        'sos' => 'SOS',
        'report' => 'Signaler',
        'emergency' => 'Urgences',
        'numbers_title' => 'Numéros d’urgence',
        'numbers_text' => 'En danger ? Appelez directement :',
        // Préfixe lu par les lecteurs d'écran : « Appeler Police secours 117 » contient le texte visible (WCAG 2.5.3).
        'call_prefix' => 'Appeler',
        'all_contacts' => 'Voir tous les contacts d’urgence',
    ],

    'reassure' => [
        'title' => 'Pendant la maintenance',
        'data_title' => 'Vos données restent protégées.',
        'data_text' => 'La mise à jour respecte vos réglages de confidentialité.',
        'drafts_title' => 'Vos brouillons restent sur votre appareil.',
        'drafts_text' => 'Ce que vous aviez commencé vous attend à la réouverture.',
        'crisis_title' => 'L’aide d’urgence reste ouverte.',
        'crisis_text' => 'SOS, signalement et contacts d’urgence fonctionnent, avec ou sans compte.',
    ],

    // Erreur 503 hors maintenance (panne, base injoignable : errors/503) : rien n'assure que le SOS en ligne réponde.
    'generic' => [
        'page_title' => 'Service momentanément indisponible',
        'meta_description' => 'Xomiton est momentanément indisponible. En danger, appelez directement les numéros d’urgence.',
        'eyebrow' => 'Incident technique',
        'title_lead' => 'Xomiton est',
        'title_mark' => 'momentanément indisponible',
        'message' => 'Nous faisons le nécessaire pour rétablir le service au plus vite.',
        'help_text' => 'Si le SOS en ligne ne répond pas, appelez directement le :first ou le :second.',
        'help_text_one' => 'Si le SOS en ligne ne répond pas, appelez directement le :first.',
        'help_text_none' => 'Si le SOS en ligne ne répond pas, appelez directement les secours.',
        'until' => 'Nous ne savons pas encore quand le service sera rétabli. Actualisez la page dans quelques minutes.',
    ],

    'refresh' => 'Actualiser la page',
    'admin_login' => 'Espace administration',
    'tagline' => 'Votre espace safe d’apprentissage et d’appui holistique',
    'logo_alt' => 'Xomiton',

    // Écran d'administration /admin/maintenance (P13-06), vous, neutre et précis.
    'admin' => [
        'title' => 'Mode maintenance',
        'intro' => 'Fermez temporairement la plateforme au public pendant une mise à jour importante. L’administration garde l’accès complet ; le SOS, le signalement et les urgences restent toujours ouverts.',
        'view_as_visitor' => 'Voir comme un visiteur',
        'states' => [
            'inactive' => 'Inactif',
            'announced' => 'Annoncé',
            'ongoing' => 'En cours',
            'ended' => 'Terminé',
        ],
        'headlines' => [
            'inactive' => 'La plateforme est ouverte',
            'announced' => 'Maintenance annoncée',
            'ongoing' => 'Maintenance en cours',
            'ended' => 'Maintenance terminée',
        ],
        'state_text' => [
            'inactive' => 'La plateforme est ouverte à tout le monde.',
            'announced' => 'Une maintenance est annoncée : un bandeau prévient les visiteurs et les membres.',
            'ongoing' => 'Le public voit la page de maintenance. Seuls les comptes admin et superadmin peuvent se connecter.',
            'ended' => 'La dernière maintenance est terminée. La plateforme est de nouveau ouverte.',
        ],
        'kpi' => [
            'state' => 'État',
            'ends' => 'Fin prévue',
            'remaining' => 'Temps restant',
            'next' => 'Prochaine fenêtre',
            'none' => 'Aucune',
            'unknown' => 'Non annoncée',
            'ended_at' => 'Terminée le :date',
            'since' => 'Depuis le :date',
            'starts_in' => 'Commence le :date',
            'hours' => 'De :from à :to',
            'ends_on' => 'Fin prévue : :date',
            'announced_count' => '{0} Aucune annoncée en ce moment|{1} Dont 1 annoncée en ce moment|[2,*] Dont :count annoncées en ce moment',
        ],
        'live' => [
            'title' => 'Activation immédiate',
            'text' => 'Ferme la plateforme au public tout de suite. Votre session reste ouverte.',
            'enable' => 'Activer la maintenance',
            'disable' => 'Désactiver la maintenance',
            'disable_text' => 'Rouvre la plateforme à tout le monde en un clic. Les fenêtres planifiées à venir sont conservées.',
            'estimate_hint' => 'Fin affichée au public, à titre indicatif : la plateforme ne rouvre qu’à la désactivation.',
        ],
        'confirm' => [
            'title' => 'Activer le mode maintenance ?',
            'text' => 'Le public verra la page de maintenance et les membres connectés seront déconnectés. Les comptes admin et superadmin gardent l’accès.',
            'acknowledge' => 'Je confirme : seuls les comptes admin et superadmin garderont l’accès.',
            'action' => 'Activer maintenant',
        ],
        'fields' => [
            'reason' => 'Motif interne',
            'reason_hint' => 'Visible uniquement dans le journal d’audit. Exemple : migration de la base de données.',
            'duration' => 'Durée estimée',
            'message' => 'Message public',
            'message_hint' => 'Texte court affiché sur la page de maintenance (280 caractères au plus).',
            'message_editor' => 'Texte affiché aux visiteurs',
            'starts_at' => 'Début',
            'ends_at' => 'Fin',
            'announce_hours' => 'Annonce préalable',
            'announce_hint' => 'Un bandeau prévient visiteurs et membres pendant ce délai avant le début.',
            'window_message' => 'Message public de la fenêtre',
            'window_reason' => 'Motif interne',
            'cancel_reason' => 'Motif de l’annulation',
            'timezone_hint' => 'Heure de :zone (UTC:offset), comme sur la page publique.',
        ],
        'durations' => [
            '15' => '15 minutes',
            '30' => '30 minutes',
            '60' => '1 heure',
            '120' => '2 heures',
            '240' => '4 heures',
            'unknown' => 'Inconnue (aucune heure affichée)',
        ],
        'announce' => [
            '0' => 'Aucune annonce',
            '1' => '1 heure avant',
            '6' => '6 heures avant',
            '24' => '24 heures avant',
            '48' => '48 heures avant',
            '72' => '3 jours avant',
            '168' => '7 jours avant',
        ],
        'message' => [
            'title' => 'Message public',
            'save' => 'Enregistrer le message',
            'preview' => 'Aperçu en direct',
            'preview_hint' => 'Rendu de la page publique sur mobile, mis à jour pendant la saisie.',
            'light' => 'Clair',
            'dark' => 'Sombre',
        ],
        'schedule' => [
            'title' => 'Planifier une fenêtre',
            'text' => 'La fenêtre s’ouvre et se ferme toute seule aux heures choisies. Deux fenêtres ne peuvent pas se chevaucher.',
            'submit' => 'Planifier la fenêtre',
            'list_title' => 'Fenêtres planifiées',
            'empty' => 'Aucune fenêtre planifiée. Planifiez la prochaine avec le formulaire.',
            'cancel' => 'Annuler la fenêtre',
            'cancel_confirm' => 'Annuler la fenêtre du :date ? Le bandeau d’annonce disparaît aussitôt.',
            'announced_badge' => 'Annoncée',
            'planned_badge' => 'Planifiée',
            'announce_from' => 'Annonce à partir du :date',
            'no_announce' => 'Sans annonce préalable',
            'by' => 'Par :name',
        ],
        'whitelist' => [
            'title' => 'Toujours accessible (RG-01)',
            'text' => 'Ces parcours restent ouverts pendant la maintenance. La liste est fixée par le code et ne peut pas être modifiée ici (Q28).',
            'locked' => 'Verrouillé',
            'groups' => [
                'crisis' => 'Parcours de crise',
                'access' => 'Accès de l’administration',
                'system' => 'Système et applications',
            ],
            'items' => [
                'sos' => 'SOS (page et API)',
                'report' => 'Signalement et suivi par code (page et API)',
                'urgences' => 'Contacts d’urgence (page et API)',
                'referentials' => 'Référentiels du signalement : zones, types de violence, besoins (API en lecture seule)',
                'acknowledge' => 'Accusé de réception d’une alerte par lien signé',
                'newsletter' => 'Newsletter (inscription et désinscription)',
                'login' => 'Connexion et code de vérification (admin et superadmin seulement)',
                'password' => 'Mot de passe oublié et réinitialisation (aucun accès : la connexion reste réservée à admin et superadmin)',
                'status' => 'État de la plateforme (GET /api/v1/status) et santé (/up)',
                'sync' => 'Synchronisation des appareils (sync/push) et signal de vie (heartbeat)',
                'cron' => 'Tâches planifiées, file d’attente et heartbeat:ping (hors HTTP)',
            ],
        ],
        'safeguards' => [
            'title' => 'Garde-fous anti-verrouillage',
            'session' => 'Votre session reste ouverte : l’administration n’est jamais bloquée.',
            'login' => 'La connexion admin et superadmin reste ouverte pendant la maintenance.',
            'password' => 'Mot de passe oublié : la réinitialisation reste ouverte, sans rouvrir la plateforme au public.',
            'command' => 'Commande de secours (cPanel, SSH ou cron) :',
        ],
        'history' => [
            'title' => 'Historique',
            'empty' => 'Aucune action de maintenance enregistrée.',
            'reason' => 'Motif : :reason',
            'no_reason' => 'Sans motif',
            // Clé = action du journal sans le préfixe « maintenance. ».
            'actions' => [
                'enabled' => 'Maintenance activée',
                'disabled' => 'Maintenance désactivée',
                'scheduled' => 'Fenêtre planifiée',
                'window_cancelled' => 'Fenêtre annulée',
                'message_updated' => 'Message public modifié',
            ],
        ],
        'toasts' => [
            'enabled' => 'Mode maintenance activé : seule l’administration garde l’accès.',
            'disabled' => 'Mode maintenance désactivé : la plateforme est de nouveau ouverte.',
            'scheduled' => 'Fenêtre planifiée du :from au :to.',
            'cancelled' => 'Fenêtre du :date annulée.',
            'message_saved' => 'Message public enregistré.',
        ],
        'preview_ribbon' => 'Aperçu de la page publique : rien n’est publié.',
        'banner_active' => 'Mode maintenance actif : le public voit la page de maintenance. Le SOS, le signalement et les urgences restent ouverts.',
        'banner_manage' => 'Gérer la maintenance',
    ],

    'validation' => [
        'reason_required' => 'Indiquez le motif interne de la maintenance.',
        'acknowledge_required' => 'Cochez la case pour confirmer l’activation.',
        'message_max' => 'Le message public ne doit pas dépasser :max caractères.',
        'date_required' => 'Indiquez la date et l’heure.',
        'date_invalid' => 'Indiquez une date et une heure valides.',
    ],

    'errors' => [
        'overlap' => 'Cette fenêtre chevauche la maintenance du :from au :to. Choisissez d’autres horaires.',
        'window_past' => 'Le début de la fenêtre est déjà passé. Choisissez une date à venir.',
        'window_order' => 'La fin doit suivre le début.',
        'window_too_long' => 'Une fenêtre dure :hours heures au plus.',
        'window_closed' => 'Cette fenêtre est déjà terminée ou annulée.',
        'lockout' => 'Votre compte perdrait l’accès pendant la maintenance : seuls les comptes admin et superadmin peuvent l’activer.',
        'already_active' => 'La maintenance est déjà active : rien n’a été modifié. Rechargez l’écran pour voir son état.',
    ],

    // Commande de secours xomiton:maintenance on|off|status (cPanel, SSH, cron).
    'command' => [
        'unknown_action' => 'Action inconnue « :action » : utilisez on, off ou status.',
        'enabled' => 'Mode maintenance activé.',
        'already_active' => 'La maintenance est déjà active : rien n’a été modifié (utilisez « off » pour la lever).',
        'disabled' => 'Mode maintenance désactivé (:count fenêtre(s) en cours close(s)).',
        'active' => 'Maintenance : ACTIVE',
        'inactive' => 'Maintenance : inactive',
        'message' => 'Message public : :message',
        'starts' => 'Début : :date',
        'ends' => 'Fin prévue : :date',
        'ends_unknown' => 'Fin prévue : non annoncée',
        'upcoming' => 'Fenêtres planifiées : :count',
        'upcoming_line' => '  - du :from au :to (annonce :hours h avant)',
        'whitelist' => 'Toujours servis (RG-01) : :paths',
        'invalid_minutes' => 'La durée --minutes doit être un entier positif.',
    ],

];

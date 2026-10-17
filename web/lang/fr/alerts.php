<?php

declare(strict_types=1);

/*
 * Signalement, besoins, SOS et contacts d'urgence (MICROCOPY.md §7, prototype signalement.php, sos.php, urgences.php).
 * Registre : tu, avec ou sans compte (VOICE_AND_TONE.md §3 : parcours de crise). Ton calme et direct, aucun adjectif
 * alarmiste, aucun néologisme. À l'écran, une alerte de type « dénonciation » est un **signalement** (Q29) ; les
 * libellés des types, statuts et priorités viennent de enums/alerts.php et ne sont pas répétés ici.
 */
return [

    'anonymous' => 'Anonyme',
    'anonymous_person' => 'Personne anonyme',
    'tracking_code' => 'Code de suivi',

    // Formulaire /signaler (P5-04 : stepper, preuves facultatives, contact, position).
    'report' => [
        'title' => 'Signaler',
        'badge' => 'Sans compte · sans trace',
        'intro' => 'Signale une violence ou exprime un besoin. Ton message est transmis uniquement aux personnes ciblées de l’annuaire (point focal, juriste, psychologue…). Aucune adresse IP ni identité n’est demandée.',
        'reassurance' => 'Tu peux rester anonyme. Rien n’est envoyé avant la dernière étape.',
        'tabs' => [
            'report' => 'Signaler une violence',
            'need' => 'Exprimer un besoin',
            'sos' => 'SOS (danger immédiat)',
        ],
        'help' => [
            'report' => 'Tu signales une violence subie ou dont tu es témoin. Les informations sur les faits sont essentielles.',
            'need' => 'Tu exprimes un besoin d’aide (soutien, hébergement, accompagnement…). Coche au moins un besoin.',
        ],
        'step' => 'Étape :current sur :total',
        'steps' => [
            'type' => 'Type',
            'facts' => 'Les faits',
            'needs' => 'Besoins et lieu',
            'evidence' => 'Preuves',
            'contact' => 'Contact',
            'review' => 'Vérification',
        ],
        'fields' => [
            'category' => 'Type de violence',
            'category_placeholder' => 'Choisir…',
            'date' => 'Date des faits',
            'date_help' => 'Approximative si tu ne sais plus. Jamais dans le futur.',
            'zone' => 'Département',
            'commune' => 'Commune',
            'description' => 'Description',
            'description_help' => 'Ce qui s’est passé, quand, et si tu es toujours en danger.',
            'description_placeholder' => 'Décris ce qui s’est passé ou ce dont tu as besoin (:min caractères minimum). Évite de donner ton nom si tu souhaites rester anonyme.',
            'description_min' => 'Décris avec au moins :min caractères.',
            'needs' => 'Besoins spécifiques',
            'needs_required' => '(obligatoire)',
            'needs_optional' => '(facultatif)',
            'needs_error' => 'Choisis au moins un besoin.',
            'evidence' => 'Preuves (facultatif)',
            'evidence_help' => 'Image, PDF ou audio, :max Mo maximum par fichier. Les métadonnées (lieu, appareil) sont supprimées avant stockage.',
            'evidence_added' => '{1} :count fichier joint|[2,*] :count fichiers joints',
            'anonymous' => 'Rester anonyme',
            'anonymous_help' => 'Recommandé. Si tu veux être recontacté·e, désactive l’interrupteur et indique un moyen de te joindre.',
            'contact' => 'Comment te recontacter ? (téléphone ou e-mail)',
            'contact_placeholder' => '+229 01 … ou nom@exemple.bj',
            'contact_invalid' => 'Numéro de téléphone ou e-mail invalide.',
            'position' => 'Joindre ma position',
            'position_help' => 'Facultatif : aide à te localiser pour l’intervention.',
            'position_locating' => 'Localisation en cours…',
            'position_attached' => 'Position jointe : :lat, :lng (± :accuracy m)',
            'position_unavailable' => 'Position indisponible (accès refusé ou non pris en charge). Tu peux envoyer sans.',
            'related_post' => 'Signalement de la publication n° :id',
        ],
        'review' => [
            'title' => 'Vérifie avant d’envoyer',
            'text' => 'Rien n’est encore parti. Tu peux revenir sur chaque étape.',
            'anonymous_yes' => 'Envoi anonyme',
            'anonymous_no' => 'Recontact possible : :contact',
            'position_yes' => 'Position jointe',
            'position_no' => 'Sans position',
        ],
        'online_hint' => 'Hors connexion, ton message est mis en attente puis envoyé automatiquement.',
        'done' => [
            'title' => 'Ton signalement est envoyé',
            'text' => 'Une personne formée le lira sous 24 h.',
            'tracking' => 'Ton code de suivi',
            'tracking_help' => 'Conserve ce code : il te permettra d’obtenir des nouvelles sans donner ton identité.',
            'routed' => 'Transmis à',
            'routed_to' => ':name — :role',
            'received' => 'Ton signalement a été reçu par l’équipe Xomiton, qui le dirigera vers la bonne personne.',
            'queued_title' => 'Signalement enregistré hors connexion',
            'queued' => 'Tu es hors connexion : ton signalement est enregistré sur cet appareil et partira automatiquement au retour du réseau.',
            'pending_title' => 'Signalement mis en attente',
            'pending' => 'Le réseau n’a pas répondu : ton signalement est conservé sur cet appareil et sera renvoyé automatiquement.',
            'duplicate' => 'Ce signalement avait déjà été reçu : aucun doublon n’a été créé.',
        ],
        'tracking' => [
            'title' => 'Suivre mon signalement',
            'intro' => 'Saisis ton code de suivi pour connaître l’avancement. Aucune autre information n’est demandée.',
            'code' => 'Code de suivi',
            'code_placeholder' => 'Ex. : SIG-A7K2M9P4QX',
            'submit' => 'Voir l’avancement',
            'not_found' => 'Aucun signalement ne correspond à ce code, ou il a été clos depuis plus de :days jours.',
            'status' => 'Statut : :status',
            'updated' => 'Dernière mise à jour : :date',
            'received_on' => 'Reçu le :date',
            'handled_by' => 'Pris en charge par une personne de l’annuaire',
        ],
        // Phrases de suivi par statut (enums/alerts.alert_status donne le libellé court).
        'status_text' => [
            'nouveau' => 'Reçu. Une personne formée va le lire.',
            'pris_en_charge' => 'Pris en charge par une personne de l’annuaire.',
            'en_cours' => 'En cours de traitement.',
            'traite' => 'Traité. Si tu as besoin d’autre chose, tu peux faire un nouveau signalement.',
            'clos' => 'Clos.',
        ],
        'from_post' => 'Tu signales une publication. Décris ce qui pose problème ; l’équipe de modération la relira.',
    ],

    // Page /sos (P5-05 : compte à rebours local, repentir 60 s, Je suis en sécurité).
    'sos' => [
        'title' => 'SOS – Alerte d’urgence',
        'intro' => 'Une alerte avec ta position est envoyée à la personne de l’annuaire la plus proche (point focal, juriste, psychologue…).',
        'button' => 'SOS',
        'button_help' => 'Maintiens le bouton 2 secondes pour envoyer ton alerte.',
        'press' => 'Appuyer pour alerter',
        'consent' => 'Consentement : j’accepte de partager ma position GPS et mon message avec les personnes ciblées de l’annuaire, uniquement pour recevoir de l’aide. Mes données ne sont pas utilisées à d’autres fins.',
        'consent_required' => 'Coche le consentement pour partager ta position, ou appelle le 117.',
        'phone' => 'Téléphone (facultatif)',
        'phone_help' => 'Pour te rappeler.',
        'message' => 'Message (facultatif)',
        'message_placeholder' => 'Où tu es, ce qui se passe…',
        'countdown_hint' => 'Après l’appui, tu as :seconds secondes pour annuler en cas d’erreur.',
        'locating' => 'Récupération de ta position…',
        'sending' => 'Envoi de l’alerte…',
        'manual' => [
            'title' => 'Position GPS indisponible.',
            'text' => 'Indique ta zone pour que l’alerte soit routée vers la bonne personne.',
        ],
        'done' => [
            'title' => 'Alerte envoyée',
            'queued_title' => 'Alerte enregistrée hors connexion',
            'routed' => 'Alerte envoyée à :name, :role',
            'received' => 'Alerte reçue par l’équipe Xomiton.',
            'queued' => 'Pas de réseau : l’alerte partira automatiquement dès que la connexion reviendra. Appelle le 117 si possible.',
            'position' => 'Ta position',
            'accuracy' => 'Précision : ± :meters m',
            'maps' => 'Voir sur Google Maps',
            'while_waiting' => 'En attendant l’aide',
            'steps' => [
                1 => 'Reste, si possible, dans un lieu public ou éclairé, loin de la personne qui te menace.',
                2 => 'Garde ton téléphone chargé et allumé : la personne contactée va t’appeler.',
                3 => 'En danger de mort, appelle aussi le 117.',
                4 => 'Ne supprime rien : messages, photos et traces peuvent servir de preuves.',
            ],
            'undo' => 'Fausse alerte ? Tu peux encore annuler pendant :seconds s.',
            'undone' => 'Alerte annulée. Les personnes prévenues savent qu’il s’agissait d’une fausse alerte.',
            'safe_confirmed' => 'Merci. Les personnes prévenues savent que tu es en sécurité.',
            'acknowledged' => ':name a bien reçu ton alerte et va t’appeler.',
        ],
        'doubt' => 'En cas de doute, appelle le 117',
        'fab_title' => 'Envoyer une alerte SOS avec ma position',
    ],

    // Accusé de réception par lien signé (P5-02 : contact d'annuaire sans compte) : vous.
    'acknowledge' => [
        'title' => 'Accuser réception de l’alerte :code',
        'text' => 'En confirmant, vous indiquez prendre cette alerte en charge : l’escalade vers le point focal national s’arrête.',
        'done' => 'Réception confirmée. Merci : appelez la personne dès que possible.',
        'already' => 'Cette alerte a déjà été prise en charge le :date.',
        'invalid' => 'Ce lien n’est plus valide. Connectez-vous à l’administration pour retrouver l’alerte.',
    ],

    // Page /urgences (prototype urgences.php) : tu, comme tout le parcours de crise.
    'emergency' => [
        'banner_title' => 'En danger immédiat ?',
        'banner_text' => 'Appelle le 117 ou envoie une alerte SOS avec ta position.',
        'title' => 'Contacts d’urgence',
        'intro' => 'Appelle directement depuis ton téléphone. Appels gratuits ou à tarif réduit selon l’opérateur.',
        'services' => [
            'police' => 'Police secours',
            'fire' => 'Pompiers / secours',
            'gendarmerie' => 'Gendarmerie nationale',
            'hotline' => 'Ligne d’écoute Xomiton',
        ],
        'directory_title' => 'Psychologues et juristes de l’annuaire',
        'directory_all' => 'Tout l’annuaire',
        'directory_empty' => 'Aucun contact disponible pour le moment. Appelle le 117 en cas de danger.',
        'availability' => 'Disponibilité : :hours',
        'places_title' => 'Où aller ?',
        'places_intro' => 'Lieux d’accueil, de soins et de dépôt de plainte.',
        'plan' => [
            'title' => 'Mon plan de sécurité',
            'intro' => 'Coche ce qui est prêt. La liste reste sur ton appareil.',
            'progress' => ':done/:total',
            'reset' => 'Réinitialiser',
            'local_only' => 'Cette liste reste sur ton appareil (aucune donnée envoyée). Imprime-la ou garde-la en lieu sûr.',
            'items' => [
                1 => 'J’ai repéré un lieu sûr où aller (amie, famille, association).',
                2 => 'J’ai mémorisé 2 numéros d’urgence importants.',
                3 => 'Mes documents (pièce d’identité, acte de naissance, carnet de santé) sont dans un endroit accessible ou en copie chez une personne de confiance.',
                4 => 'J’ai de l’argent (même un peu) et de quoi me déplacer.',
                5 => 'J’ai prévenu une personne de confiance de ma situation.',
                6 => 'Je sais comment partir rapidement (sortie, transport).',
                7 => 'J’ai convenu d’un mot-code avec une personne de confiance pour demander de l’aide.',
                8 => 'Je sauvegarde mes preuves (captures, certificats) en lieu sûr.',
            ],
        ],
        'feed_card' => [
            'title' => 'En cas d’urgence',
            'text' => 'Ligne d’écoute, juristes et psychologues de l’annuaire. Réponse humaine sous 24 h ; en danger immédiat, appelle le 117.',
        ],
    ],

    // Modale « Demander à être rappelé·e » de l'annuaire (P8-12 → RequestCallback).
    'callback' => [
        'title' => 'Demander à être rappelé·e',
        'intro' => ':name te rappellera au numéro ou à l’adresse que tu indiques.',
        'contact' => 'Comment te joindre ?',
        'contact_help' => 'Téléphone ou e-mail. Obligatoire pour être rappelé·e.',
        'message' => 'Ton message',
        'message_help' => ':max caractères maximum. Dis simplement de quoi tu as besoin.',
        'sent' => 'Demande envoyée. :name te recontactera.',
    ],

    // Signalement d'un contenu de la communauté à la modération (prototype messaging.php, render.php).
    'moderation' => [
        'title' => 'Signaler à la modération',
        'intro' => 'Ton signalement est transmis à l’équipe de modération, de façon confidentielle.',
        'reason' => 'Motif',
        'reasons' => [
            'harassment' => 'Harcèlement ou insultes',
            'threat' => 'Menaces ou chantage',
            'discrimination' => 'Contenu discriminatoire (homophobie, transphobie)',
            'privacy' => 'Partage d’informations privées',
            'sexual' => 'Contenu sexuel non sollicité',
            'spam' => 'Publicité ou arnaque',
            'other' => 'Autre',
        ],
        'details' => 'Précisions (facultatif)',
        'sent' => 'Signalement transmis à la modération. Merci.',
        'danger_hint' => 'En danger ? Utilise le signalement anonyme ou le SOS.',
    ],

];

<?php

declare(strict_types=1);

/*
 * Page `/urgences` (P4-06 ; prototype_social/urgences.php). Registre : tu (parcours de crise, VOICE_AND_TONE.md §3,
 * Q47). Aucun numéro ici : ils viennent du paramètre `emergency_numbers` et de l'annuaire (ListEmergencyContacts).
 * Lieux sûrs par défaut (prototype l.15-20) : remplacés par le paramètre `safe_places` dès qu'il est renseigné.
 */
return [

    'title' => 'Contacts d’urgence',
    'intro' => 'Appelle directement depuis ton téléphone. Appels gratuits ou à tarif réduit selon l’opérateur.',

    // Bandeau (prototype l.42-46) : seul accès rouge de la page, vers le SOS.
    'banner' => [
        'title' => 'En danger immédiat ?',
        'text' => 'Appelle le :number ou envoie une alerte SOS avec ta position.',
        'text_no_number' => 'Envoie une alerte SOS avec ta position.',
        'sos' => 'SOS',
        'sos_hint' => 'envoyer une alerte',
    ],

    // Boutons d'appel (prototype l.53-63)
    'services' => [
        'title' => 'Numéros d’urgence',
        'empty' => 'Aucun numéro n’est encore renseigné. En danger, envoie une alerte SOS.',
    ],

    // Fiches de l'annuaire (prototype l.66-90) : point focal, juriste, psychologue (Q59).
    'contacts' => [
        'title' => 'Points focaux, psychologues et juristes',
        'intro' => 'Des personnes de l’annuaire Xomiton, à joindre directement.',
        'zone_label' => 'Ton département',
        'zone_all' => 'Tous les départements',
        'zone_submit' => 'Afficher',
        'zone_current' => 'Contacts du département :zone d’abord, puis contacts nationaux.',
        'national' => 'National',
        'call' => 'Appeler',
        'whatsapp' => 'WhatsApp',
        'empty' => 'Aucun contact disponible pour le moment. Appelle un des numéros d’urgence ci-dessus.',
        'truncated' => 'D’autres contacts existent : choisis ton département pour voir les plus proches.',
        'directory_all' => 'Tout l’annuaire',
    ],

    // Lieux sûrs (prototype l.92-101)
    'places' => [
        'title' => 'Où aller ?',
        'intro' => 'Lieux d’accueil, de soins et de dépôt de plainte.',
        'examples' => 'exemples',
        'items' => [
            ['name' => 'Office central de protection des mineurs (OCPM)', 'where' => 'Cotonou', 'text' => 'Pour les victimes de moins de 18 ans.'],
            ['name' => 'Centre de promotion sociale (CPS)', 'where' => 'Dans chaque commune', 'text' => 'Accueil, écoute, orientation et hébergement temporaire.'],
            ['name' => 'Brigade de protection de la famille', 'where' => 'Cotonou et chefs-lieux', 'text' => 'Dépôt de plainte pour violences domestiques.'],
            ['name' => 'Centre de santé partenaire', 'where' => 'Selon ta zone', 'text' => 'Soins, certificat médical, PEP, contraception d’urgence.'],
        ],
    ],

    // Plan de sécurité (prototype l.104-122) : cases mémorisées sur l'appareil seulement (localStorage `xm_plan_v1`). Clés 0 à 7 comme le prototype (`data-i`) : un état
    // déjà enregistré par le prototype coche les mêmes étapes.
    'plan' => [
        'title' => 'Mon plan de sécurité',
        'intro' => 'Coche ce qui est déjà prêt.',
        'progress' => ':done/:total',
        'progress_live' => 'Étapes prêtes : :done sur :total.',
        'print' => 'Imprimer',
        'reset' => 'Réinitialiser',
        'reset_done' => 'Ton plan de sécurité est réinitialisé.',
        'local_only' => 'Cette liste reste sur ton appareil (aucune donnée envoyée). Imprime-la ou garde-la en lieu sûr.',
        'shared_device' => 'Sur un téléphone partagé, réinitialise la liste après usage.',
        'items' => [
            0 => 'J’ai repéré un lieu sûr où aller (amie, famille, association).',
            1 => 'J’ai mémorisé 2 numéros d’urgence importants.',
            2 => 'Mes documents (pièce d’identité, acte de naissance, carnet de santé) sont dans un endroit accessible ou en copie chez une personne de confiance.',
            3 => 'J’ai de l’argent (même un peu) et de quoi me déplacer.',
            4 => 'J’ai prévenu une personne de confiance de ma situation.',
            5 => 'Je sais comment partir rapidement (sortie, transport).',
            6 => 'J’ai convenu d’un mot-code avec une personne de confiance pour demander de l’aide.',
            7 => 'Je sauvegarde mes preuves (captures, certificats) en lieu sûr.',
        ],
    ],

    // Autres actions (prototype l.124-128)
    'actions' => [
        'label' => 'Autres façons d’agir',
        'report' => 'Signaler',
        'assistant' => 'Assistant',
        'sign_in' => 'Se connecter',
    ],
];

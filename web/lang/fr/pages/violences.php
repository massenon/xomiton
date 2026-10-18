<?php

declare(strict_types=1);

/*
 * Comprendre les violences `/violences` (P4-04 ; prototype_social/violences.php). Registre : vous (page publique
 * institutionnelle « Apprendre », VOICE_AND_TONE.md §3, Q36) ; formulations épicènes ou point médian là où le prototype
 * accordait au féminin (« seule », « menacée », « forcée » : VOICE §4). Les 5 formes viennent de `violence_types`
 * (Referentials) ; seuls les signes à repérer, rangés par slug, sont ici. Auto-évaluation : 100 % dans le navigateur,
 * rien n'est enregistré ni transmis (plan §7).
 */
return [

    'title' => 'Comprendre les violences',

    // Héro (prototype l.47-60)
    'hero' => [
        'badge' => 'Comprendre pour agir',
        'text' => 'Une violence basée sur le genre (VBG) vise une personne en raison de son genre, de son orientation ou de son identité. Vous n’êtes pas seul·e, et ce n’est jamais votre faute.',
        'actions_label' => 'Agir maintenant',
        'sos' => 'SOS',
        'sos_hint' => 'envoyer une alerte avec votre position',
        'report' => 'Signaler',
        'assistant' => 'Parler à l’assistant',
    ],

    // Les formes de violences (prototype l.63-80) : libellé, icône et description depuis `violence_types`.
    'forms' => [
        'title' => '{1} La forme de violence|[2,*] Les :count formes de violences',
        'signs_title' => 'Signes à repérer',
        'signs' => [
            'physique' => ['Blessures inexpliquées ou cachées', 'Peur de rentrer chez soi', 'Isolement physique, séquestration'],
            'psychologique' => ['Insultes, humiliations, dévalorisation constante', 'Menaces de révéler l’orientation (outing)', 'Contrôle des fréquentations'],
            'sexuelle' => ['Rapports imposés ou « correctifs »', 'Attouchements non consentis', 'Chantage sexuel en échange d’un service'],
            'economique' => ['Privation d’argent ou de documents', 'Interdiction de travailler ou d’étudier', 'Expulsion du domicile'],
            'numerique' => ['Diffusion de photos sans accord', 'Harcèlement sur les réseaux', 'Surveillance du téléphone, doxxing'],
        ],
    ],

    // Le cycle de la violence (prototype l.82-99)
    'cycle' => [
        'title' => 'Le cycle de la violence',
        'intro' => 'Un schéma qui se répète et s’accélère souvent avec le temps. Le reconnaître aide à en sortir.',
        'step_label' => 'Étape :number',
        'steps' => [
            ['title' => 'Tension', 'icon' => 'cloud-lightning', 'tone' => 'warning', 'text' => 'L’atmosphère devient pesante, on « marche sur des œufs ».'],
            ['title' => 'Agression', 'icon' => 'exclamation-triangle-fill', 'tone' => 'danger', 'text' => 'Explosion : violence verbale, physique, sexuelle ou numérique.'],
            ['title' => 'Justification', 'icon' => 'chat-left-dots', 'tone' => 'info', 'text' => '« C’est ta faute », excuses, minimisation, culpabilisation.'],
            ['title' => 'Lune de miel', 'icon' => 'heart-half', 'tone' => 'primary', 'text' => 'Promesses, cadeaux, calme… jusqu’à la prochaine tension.'],
        ],
        'loop' => '… puis retour à la tension : le cycle recommence.',
    ],

    // Que faire, que ne pas faire (prototype l.101-122)
    'do' => [
        'title' => 'Que faire ?',
        'items' => [
            'Vous mettre en sécurité et prévenir une personne de confiance.',
            'Garder des preuves : captures d’écran, certificats médicaux, dates.',
            'Consulter un centre de santé (soins, contraception d’urgence, traitement préventif du VIH sous 72 h).',
        ],
        'contact_member' => 'Contacter une personne de l’:link ou faire un :report.',
        'contact_guest' => 'Joindre une personne formée parmi les :link ou faire un :report.',
        'directory' => 'annuaire',
        'emergency_contacts' => 'contacts d’urgence',
        'report' => 'signalement anonyme',
        'plan' => 'Préparer un :link.',
        'plan_link' => 'plan de sécurité',
    ],
    'dont' => [
        'title' => 'Que ne pas faire ?',
        'items' => [
            'Vous blâmer : la responsabilité est toujours celle de l’auteur·e.',
            'Payer un chantage ou négocier seul·e avec la personne violente.',
            'Supprimer les preuves ou les messages.',
            'Confronter l’auteur·e sans être en sécurité.',
            'Garder le silence en pensant que « ça passera ».',
        ],
    ],

    // Que dit la loi ? (prototype l.124-134)
    'law' => [
        'title' => 'Que dit la loi ?',
        'text' => 'Au Bénin, la :law réprime les violences faites aux femmes : harcèlement sexuel, violences conjugales, viol, mariage forcé. Le Code pénal prévoit des circonstances aggravantes, et la Constitution garantit l’égalité devant la loi.',
        'law_name' => 'loi n° 2011-26 du 9 janvier 2012',
        'cta' => 'Lire les lois et codes du Bénin',
    ],

    // Auto-évaluation confidentielle (prototype l.136-158 et script l.167-185)
    'check' => [
        'title' => 'Auto-évaluation confidentielle',
        'intro' => ':count questions pour faire le point. Vos réponses restent sur cet écran : rien n’est enregistré ni transmis. Ce n’est pas un diagnostic.',
        'private' => 'Rien n’est envoyé',
        'no_js' => 'Le résultat s’affiche quand JavaScript est activé. Vos réponses ne quittent jamais cet écran.',
        'question_label' => 'Question :number sur :total',
        'yes' => 'Oui',
        'no' => 'Non',
        'questions' => [
            'Avez-vous peur de la réaction d’une personne de votre entourage ?',
            'Quelqu’un contrôle-t-il votre téléphone, vos messages ou vos sorties ?',
            'Quelqu’un menace-t-il de révéler votre orientation ou votre identité ?',
            'Quelqu’un vous humilie-t-il, vous insulte-t-il ou vous rabaisse-t-il régulièrement ?',
            'Quelqu’un vous a-t-il imposé un acte sexuel ou un contact non voulu ?',
            'Vous prive-t-on d’argent, de documents ou de logement ?',
            'Avez-vous déjà subi des coups, des bousculades ou un enfermement ?',
            'Vous sentez-vous coupable ou responsable de ce qui vous arrive, alors que vous n’avez rien demandé ?',
        ],
        'submit' => 'Voir mon résultat',
        'reset' => 'Recommencer',
        'incomplete' => 'Répondez aux :count questions pour voir votre résultat.',
        'reset_done' => 'Réponses effacées.',
        'results' => [
            'low' => [
                'title' => 'Peu d’indices de violence',
                'text' => 'Rien ne laisse penser à une situation préoccupante, mais si un doute persiste, parlez-en à une personne de confiance ou à l’assistant.',
            ],
            'medium' => [
                'title' => 'Quelques signes méritent votre attention',
                'text' => 'Certaines situations décrites peuvent être des formes de violence. Vous pouvez en parler à une personne paire ou faire un signalement anonyme.',
            ],
            'high' => [
                'title' => 'Plusieurs signes préoccupants',
                'text' => 'Vos réponses évoquent une situation qui peut vous mettre en danger. Vous avez le droit d’être protégé·e : contactez une personne formée et, en cas de danger immédiat, utilisez le bouton SOS.',
            ],
        ],
        'actions_label' => 'Trouver de l’aide',
        'directory' => 'Annuaire',
        'emergency_contacts' => 'Contacts d’urgence',
        'assistant' => 'Assistant',
        'note' => 'Ce résultat est indicatif et ne remplace pas l’avis d’un·e professionnel·le. Il n’a pas été enregistré.',
    ],

    'og_alt' => 'Comprendre les violences basées sur le genre : formes, signes, cycle et auto-évaluation confidentielle',
    'schema_section' => 'Violences basées sur le genre',
];

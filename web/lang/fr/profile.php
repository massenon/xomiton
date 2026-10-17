<?php

declare(strict_types=1);

/*
 * Profil, profil progressif et paramètres (P8-07 à P8-09, FR-AUTH-07 ; prototype partials/profile-head.php,
 * my-profile*.php, settings.php). Registre : tu. Chaque groupe du profil progressif a son « Pourquoi on demande ça ? »
 * (UX_GUIDELINES.md §4 : données sensibles facultatives et expliquées). Visibilités dans enums/identity.php ;
 * langues proposées limitées à config('xomiton.locales') (Q40).
 */
return [

    'title' => 'Mon profil',
    'page_title_of' => 'Profil de :name',
    'cover_alt' => 'Photo de couverture',
    'avatar_alt' => 'Avatar de :name',
    'edit_cover' => 'Changer la couverture',
    'edit_avatar' => 'Changer l’avatar',
    'verified_role' => 'Rôle vérifié : :role',
    'pseudo' => '@:pseudo',
    'connections' => '{0} Aucune connexion|{1} :count connexion|[2,*] :count connexions',
    'posts_count' => '{0} Aucune publication|{1} :count publication|[2,*] :count publications',
    'followers' => '{0} Aucun·e abonné·e|{1} :count abonné·e|[2,*] :count abonné·es',
    'following' => '{0} Aucun abonnement|{1} :count abonnement|[2,*] :count abonnements',
    'member_since' => 'Membre depuis :date',
    'location' => ':commune, :zone',
    'completion' => 'Profil complété à :percent %',
    'more_actions' => 'Plus d’actions',

    'tabs' => [
        'posts' => 'Publications',
        'about' => 'À propos',
        'connections' => 'Connexions',
        'media' => 'Médias',
        'events' => 'Événements',
        'activity' => 'Activité',
    ],

    'empty' => [
        'posts' => 'Tu n’as pas encore publié. Partage un témoignage, une question ou une ressource avec la communauté.',
        'posts_other' => ':name n’a rien publié pour l’instant.',
        'connections' => 'Aucune connexion trouvée.',
        'media' => 'Aucune photo pour l’instant. Les images de tes publications apparaîtront ici.',
        'events' => 'Aucun événement. Réponds « Je participe » à un événement pour le retrouver ici.',
        'activity' => 'Aucune activité récente.',
    ],

    'about' => [
        'title' => 'À propos',
        'info' => 'Informations',
        'bio' => 'Présentation',
        'interests' => 'Centres d’intérêt',
        'pronouns' => 'Pronoms',
        'member_type' => 'Type de membre',
        'zone' => 'Zone',
        'joined' => 'Inscription',
        'privacy_title' => 'Confidentialité de ton profil',
        'privacy_text' => 'Ton nom complet n’est visible que par tes connexions. Tes publications anonymes ne sont jamais liées à ton compte publiquement.',
        'manage_privacy' => 'Gérer ma confidentialité',
        'edit' => 'Modifier',
        'not_provided' => 'Non renseigné',
        'prefer_not_say' => 'Je préfère ne pas le dire',
    ],

    'sidebar' => [
        'about' => 'Présentation',
        'media' => 'Médias',
        'media_all' => 'Voir toutes les photos',
        'connections' => 'Connexions',
        'connections_all' => 'Voir toutes les connexions',
        'message' => 'Envoyer un message',
        'remove_connection' => 'Retirer la connexion',
    ],

    // Profil progressif (LOGIN_INSCRPTION_MULTISTEP_USER_INSTRUCTION.md §7, P8-08).
    'progressive' => [
        'banner_title' => 'Complète ton profil, c’est facultatif',
        'banner_text' => 'Un groupe de questions à la fois. Tu peux répondre « Je préfère ne pas le dire » et tout modifier plus tard.',
        'banner_progress' => ':percent % complété',
        'banner_dismiss' => 'Plus tard',
        'why' => 'Pourquoi on demande ça ?',
        'skip_group' => 'Passer ce groupe',
        'save_group' => 'Enregistrer et continuer',
        'done_title' => 'Merci, ton profil est à jour',
        'done_text' => 'Tu peux revenir le compléter ou le modifier à tout moment depuis les paramètres.',
        'sensitive_consent' => 'J’accepte que Xomiton conserve ces informations sensibles, chiffrées, pour me proposer des contenus adaptés. Je peux retirer ce consentement à tout moment.',
        'sensitive_required' => 'Pour enregistrer ton identité de genre ou ton orientation, accepte d’abord la conservation de ces données sensibles.',
        'groups' => [
            'pronouns' => [
                'title' => 'Tes pronoms',
                'why' => 'Pour que la communauté et l’équipe s’adressent à toi correctement. Visible selon ta confidentialité.',
                'options' => [
                    'elle' => 'Elle',
                    'il' => 'Il',
                    'iel' => 'Iel',
                    'other' => 'Autre',
                    'prefer_not' => 'Je préfère ne pas le dire',
                ],
                'other_placeholder' => 'Précise (autre)',
            ],
            'member_type' => [
                'title' => 'Qui es-tu sur Xomiton ?',
                'why' => 'Pour adapter ton espace : une personne concernée, une pair·e éducateur·rice, une allié·e ou une personne ressource ne voient pas les mêmes outils.',
            ],
            'ally_status' => [
                'title' => 'Allié·e ou personne ressource ?',
                'why' => 'Pour orienter vers toi les demandes qui te concernent (juridique, santé, écoute) et mettre en avant ton rôle de soutien.',
                'options' => [
                    'none' => 'Aucun des deux',
                    'ally' => 'Allié·e',
                    'resource' => 'Personne ressource (juriste, psychologue, santé…)',
                ],
                'profession' => 'Ta profession ou ta structure',
            ],
            'gender_identity' => [
                'title' => 'Identité de genre',
                'why' => 'Pour te proposer des contenus et des contacts adaptés. Donnée sensible : chiffrée, jamais visible publiquement ni partagée sans ton accord.',
                'options' => [
                    'femme_cis' => 'Femme cisgenre',
                    'femme_trans' => 'Femme transgenre',
                    'homme_cis' => 'Homme cisgenre',
                    'homme_trans' => 'Homme transgenre',
                    'non_binaire' => 'Non binaire',
                    'intersexe' => 'Intersexe',
                    'questionnement' => 'En questionnement',
                    'autre' => 'Autre',
                    'prefer_not' => 'Je préfère ne pas le dire',
                ],
            ],
            'orientation' => [
                'title' => 'Orientation',
                'why' => 'Pour adapter les ressources (santé, droits, communauté). Donnée sensible : chiffrée, jamais visible publiquement ni partagée sans ton accord.',
                'options' => [
                    'lesbienne' => 'Lesbienne',
                    'bisexuelle' => 'Bisexuel·le',
                    'pansexuelle' => 'Pansexuel·le',
                    'queer' => 'Queer',
                    'asexuelle' => 'Asexuel·le',
                    'heterosexuelle' => 'Hétérosexuel·le',
                    'questionnement' => 'En questionnement',
                    'autre' => 'Autre',
                    'prefer_not' => 'Je préfère ne pas le dire',
                ],
            ],
            'age' => [
                'title' => 'Ton âge',
                'why' => 'Pour te montrer les contenus de ta tranche d’âge et respecter les règles de protection des mineur·es. Jamais affiché.',
                'field' => 'Âge',
                'range' => 'Tranche d’âge',
            ],
            'location' => [
                'title' => 'Où vis-tu ?',
                'why' => 'Pour te proposer les contacts, événements et groupes de ta zone, et router une alerte vers la bonne personne. La commune est facultative.',
                'zone' => 'Département',
                'commune' => 'Commune / Ville',
                'show_zone' => 'Afficher ma zone sur mon profil',
            ],
            'interests' => [
                'title' => 'Ce qui t’intéresse',
                'why' => 'Pour mettre en avant les thématiques, événements et newsletters qui te parlent. Modifiable à tout moment.',
                'hint' => 'Choisis autant de sujets que tu veux.',
            ],
            'trusted_contact' => [
                'title' => 'Personne de confiance',
                'why' => 'Pour la prévenir si tu déclenches un SOS. Elle n’est jamais contactée autrement.',
                'name' => 'Son prénom ou surnom',
                'phone' => 'Son téléphone',
            ],
            'bio' => [
                'title' => 'Présentation',
                'why' => 'Quelques mots pour te présenter à la communauté. Visible selon ta confidentialité.',
                'field' => 'Présentation (:max caractères max.)',
                'placeholder' => 'Ce que tu veux partager de toi…',
            ],
        ],
    ],

    // Paramètres /parametres (P8-09).
    'settings' => [
        'title' => 'Paramètres',
        'intro' => 'Gère ton compte, ta confidentialité et ta sécurité.',
        'sections' => [
            'profile' => 'Profil',
            'password' => 'Mot de passe',
            'privacy' => 'Confidentialité',
            'notifications' => 'Notifications',
            'language' => 'Langue',
            'appearance' => 'Apparence',
            'security' => 'Sécurité',
            'data' => 'Mes données',
            'sessions' => 'Appareils connectés',
            'delete' => 'Supprimer mon compte',
        ],

        'profile' => [
            'name' => 'Nom complet',
            'name_help' => 'Visible seulement par tes connexions. Jamais public.',
            'pseudo' => 'Pseudonyme',
            'pseudo_help' => 'Visible par la communauté. Ton vrai nom n’est jamais demandé.',
            'email' => 'E-mail',
            'email_help' => 'Pour te connecter et recevoir tes codes. Jamais partagé.',
            'phone' => 'Téléphone',
            'phone_placeholder' => '+229 01 XX XX XX XX',
            'phone_help' => 'Facultatif : permet la connexion par téléphone et le rappel après un SOS.',
            'zone' => 'Département',
            'commune' => 'Commune',
            'bio' => 'Présentation (:max caractères max.)',
            'submit' => 'Enregistrer le profil',
        ],

        'password' => [
            'current' => 'Mot de passe actuel',
            'new' => 'Nouveau mot de passe',
            'confirm' => 'Confirmation',
            'rules' => 'Au moins :min caractères, avec une majuscule, une minuscule et un chiffre ; un symbole pour « Excellent ».',
            'revoke_others' => 'Déconnecter mes autres appareils',
            'submit' => 'Changer le mot de passe',
        ],

        'privacy' => [
            'visibility' => 'Qui peut voir mon profil ?',
            'show_zone' => 'Afficher ma zone sur mon profil',
            'searchable' => 'Apparaître dans les suggestions « Qui suivre »',
            'sos_gps' => 'Consentement SOS : autoriser le partage de ma position GPS lors d’une alerte',
            'sos_gps_help' => 'La position n’est transmise qu’au moment où tu déclenches une alerte, aux personnes ciblées de l’annuaire.',
            'sensitive_consent' => 'Conserver mes données sensibles (identité de genre, orientation), chiffrées',
            'sensitive_consent_help' => 'Si tu retires ce consentement, ces informations sont effacées de ton profil.',
        ],

        'language' => [
            'title' => 'Langue de l’interface',
            'help' => 'Les langues marquées « bientôt » arriveront quand leurs traductions seront complètes.',
            'options' => [
                'fr' => 'Français',
                'fon' => 'Fɔngbe',
                'yo' => 'Yorùbá',
                'en' => 'English',
            ],
            'soon' => '(bientôt)',
            'unavailable' => 'Cette langue n’est pas encore disponible.',
        ],

        'appearance' => [
            'title' => 'Thème',
            'help' => 'Le thème est mémorisé sur cet appareil.',
            'light' => 'Clair',
            'dark' => 'Sombre',
            'auto' => 'Automatique',
            'applied' => 'Thème appliqué.',
            'font_size' => 'Taille du texte',
            'reduced_motion' => 'Réduire les animations',
        ],

        'security' => [
            'mask_notifications' => 'Masquer le contenu sensible dans les notifications',
            'discreet_mode' => 'Mode discret',
            'discreet_mode_help' => 'Titre d’onglet neutre, aperçus masqués, aucune image sensible sur l’écran de verrouillage.',
            'neutral_tab_title' => 'Titre d’onglet neutre',
            'neutral_tab_title_help' => 'Le nom de l’onglet affiche « Notes » au lieu de Xomiton.',
            'neutral_tab_value' => 'Notes',
            'pin_enable' => 'Verrouiller l’application avec un code PIN',
            'pin_help' => 'Demandé à chaque ouverture de l’application. Jamais transmis à nos serveurs.',
            'pin' => 'Code PIN à 4 chiffres',
            'pin_placeholder' => '0000',
            'pin_keep' => '•••• (laisser vide pour conserver)',
            'pin_invalid' => 'Le code PIN doit comporter exactement 4 chiffres.',
            'pin_required' => 'Définis un code PIN à 4 chiffres pour l’activer.',
            'sounds' => 'Sons',
            'sounds_help' => 'Le son du SOS reste actif pour les personnes qui le reçoivent.',
        ],

        'sessions' => [
            'title' => 'Appareils connectés',
            'intro' => 'Les appareils où ta session est ouverte. Déconnecte ceux que tu ne reconnais pas.',
            'current' => 'Cet appareil',
            'last_active' => 'Dernière activité :ago',
            'revoke' => 'Déconnecter',
            'revoke_all' => 'Déconnecter tous les autres appareils',
            'empty' => 'Aucun autre appareil connecté.',
        ],

        'data' => [
            'title' => 'Télécharger mes données',
            'text' => 'Obtiens une copie de ton profil, de tes publications et de ton activité au format JSON.',
            'submit' => 'Télécharger mes données',
            'preparing' => 'Ton export est en préparation. Tu recevras un e-mail avec le lien de téléchargement (valable :hours h).',
            'ready' => 'Ton export est prêt.',
        ],

        'delete' => [
            'title' => 'Supprimer mon compte',
            'text' => 'Cette action est définitive : ton profil et tes publications seront supprimés sous :days jours. Tes signalements anonymes déjà transmis sont conservés pour le suivi des cas.',
            'confirm_label' => 'Tape SUPPRIMER pour confirmer',
            'confirm_word' => 'SUPPRIMER',
            'confirm_error' => 'Tape SUPPRIMER pour confirmer.',
            'submit' => 'Supprimer mon compte',
            'done' => 'Ton compte est supprimé. Tes données seront effacées sous :days jours.',
        ],

        'toasts' => [
            'profile_saved' => 'Ton profil est mis à jour.',
            'password_changed' => 'Mot de passe modifié.',
            'saved' => 'Paramètres enregistrés.',
            'language_saved' => 'Langue enregistrée.',
            'sessions_revoked' => 'Autres appareils déconnectés.',
            'avatar_saved' => 'Avatar mis à jour.',
            'cover_saved' => 'Couverture mise à jour.',
        ],

        'errors' => [
            'name_required' => 'Indique ton nom.',
            'pseudo_format' => 'Le pseudonyme doit contenir :min à :max caractères (lettres, chiffres, _ . -).',
            'pseudo_taken' => 'Ce pseudonyme est déjà utilisé.',
            'phone_invalid' => 'Numéro de téléphone béninois invalide (ex. +229 01 97 38 88 47).',
            'current_password' => 'Le mot de passe actuel est incorrect.',
            'password_confirmation' => 'La confirmation ne correspond pas.',
            'locale_unavailable' => 'Cette langue n’est pas encore disponible.',
        ],
    ],

];

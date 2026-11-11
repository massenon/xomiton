<?php

declare(strict_types=1);

/*
 * Pages légales publiques : politique de confidentialité, conditions d’utilisation, mentions légales, cookies
 * (LegalPageController, vue public/legal/page.blade.php). Registre : vous (VOICE_AND_TONE.md §3, site public).
 * Jetons {nom} remplacés par App\Application\Legal\LegalTexts : durées lues dans la configuration, identité de l’éditeur
 * (XOMITON_LEGAL_*, sinon « [À COMPLÉTER PAR LE CLIENT : …] »), prestataires SMS et assistant selon la configuration.
 * Ces textes ne remplacent pas un avis juridique : à faire valider par le client et son conseil (docs/playstore/
 * PUBLICATION_PLAY_STORE.md §7). Changer un texte = changer `xomiton.content.legal_updated_at`.
 * Les clauses (`clauses`) ne contiennent pas de jeton.
 */
return [

    'updated' => 'Dernière mise à jour : :date',
    'nav_label' => 'Sections de la page',
    'others' => 'Autres pages utiles',

    // Libellés des marqueurs « À COMPLÉTER PAR LE CLIENT » (un par champ d’identité).
    'fields' => [
        'publisher_name' => 'nom de l’association ou de l’entité qui édite Xomiton',
        'legal_form' => 'forme juridique',
        'registration' => 'numéro d’enregistrement ou de déclaration',
        'address' => 'adresse postale',
        'director' => 'nom de la personne directrice de la publication',
        'editor_phone' => 'numéro de téléphone',
        'host_name' => 'nom de l’hébergeur',
        'host_address' => 'adresse de l’hébergeur',
        'host_country' => 'pays d’hébergement des données',
        'dpo_name' => 'nom de la personne chargée de la protection des données',
        'dpo_email' => 'adresse e-mail de la personne chargée de la protection des données',
        'jurisdiction' => 'juridiction compétente',
    ],

    'clauses' => [
        'sms_vonage' => 'Pour les SMS de secours d’une alerte SOS, Xomiton fait appel au prestataire Vonage, qui reçoit le numéro de téléphone du contact de confiance et le texte du message, uniquement pour l’envoyer. Ce prestataire peut traiter ces données hors du Bénin.',
        'sms_twilio' => 'Pour les SMS de secours d’une alerte SOS, Xomiton fait appel au prestataire Twilio, qui reçoit le numéro de téléphone du contact de confiance et le texte du message, uniquement pour l’envoyer. Ce prestataire peut traiter ces données hors du Bénin.',
        'sms_none' => 'Les SMS de secours d’une alerte SOS sont envoyés par un prestataire d’envoi de SMS (Vonage ou Twilio) qui reçoit le numéro du contact de confiance et le texte du message, uniquement pour l’envoyer. Tant que ce service n’est pas activé, aucun SMS n’est envoyé.',
        'assistant_kb' => 'L’assistant répond à partir de la base de connaissances de Xomiton. Vos questions ne sont envoyées à aucun service d’intelligence artificielle externe : le service Gemini de Google est branché mais désactivé.',
        'assistant_llm' => 'L’assistant peut transmettre le texte de votre question au service Gemini de Google (intelligence artificielle) pour formuler sa réponse, sans votre identité. Ce traitement a lieu hors du Bénin. Ne saisissez pas d’informations qui vous identifient.',
    ],

    'pages' => [

        // ------------------------------------------------------------------------------------------------------
        'privacy' => [
            'title' => 'Politique de confidentialité',
            'lead' => 'Quelles données Xomiton collecte, pourquoi, combien de temps, avec qui elles sont partagées et comment exercer vos droits.',
            'sections' => [
                [
                    'id' => 'responsable',
                    'title' => 'Qui est responsable de vos données',
                    'blocks' => [
                        '{publisher_name} ({legal_form}), éditrice de {site_name}, est responsable du traitement de vos données personnelles. Adresse : {address}.',
                        'La personne chargée de la protection des données est {dpo_name}. Vous pouvez la joindre à {dpo_email}, ou à {contact_email} pour toute question sur vos données.',
                        'Xomiton est un espace sûr pour s’informer, échanger et être soutenu·e, au Bénin. Nous collectons le strict nécessaire, ne vendons aucune donnée, n’affichons aucune publicité et ne faisons aucun profilage.',
                    ],
                ],
                [
                    'id' => 'donnees',
                    'title' => 'Les données que nous collectons',
                    'blocks' => [
                        'Voici les données réellement traitées par l’application et le site, avec la raison et la base légale de chacune.',
                        ['table' => [
                            'head' => ['Donnée', 'Pourquoi', 'Base légale', 'Facultative'],
                            'rows' => [
                                ['Compte : pseudonyme, adresse e-mail, mot de passe (conservé haché), zone et commune, confirmation d’avoir {min_age} ans ou plus', 'Créer et sécuriser votre compte, vous envoyer les codes de vérification', 'Exécution du service', 'Non. Aucun nom réel ni date de naissance n’est exigé'],
                                ['Nom, numéro de téléphone du profil', 'Vous joindre ou vous reconnaître si vous le choisissez ; SMS de secours', 'Votre consentement', 'Oui'],
                                ['Profil : biographie, pronoms, photo, centres d’intérêt', 'Personnaliser votre espace', 'Votre consentement', 'Oui'],
                                ['Origine, orientation ou identité de genre (données sensibles)', 'Adapter le soutien proposé', 'Votre consentement explicite et distinct, retirable à tout moment', 'Oui'],
                                ['Contacts de confiance (nom, téléphone ou e-mail)', 'Les prévenir en cas d’alerte SOS', 'Votre consentement', 'Oui'],
                                ['Position au moment d’une alerte SOS', 'Indiquer où vous êtes aux personnes alertées', 'Votre consentement, donné à chaque alerte', 'Oui'],
                                ['Signalements et besoins : récit, lieu, pièces jointes, moyen de vous recontacter', 'Prendre en charge la situation et vous orienter', 'Votre consentement ; intérêt vital de la personne en danger', 'Oui. Un signalement peut être fait sans compte'],
                                ['Publications, commentaires, sondages, messages privés, échanges avec l’assistant', 'Faire fonctionner la communauté et la messagerie', 'Exécution du service', 'Selon les fonctions utilisées'],
                                ['Données techniques : adresse IP, navigateur ou appareil, journal des connexions et des actions sensibles, preuve de vos consentements', 'Sécuriser les comptes, prévenir les abus, prouver votre consentement', 'Intérêt légitime de sécurité ; obligation légale pour les consentements', 'Non'],
                                ['Jeton de notification de l’appareil (Firebase Cloud Messaging)', 'Vous envoyer les notifications que vous avez activées', 'Votre consentement', 'Oui'],
                                ['Adresse e-mail de la newsletter', 'Vous envoyer les actualités, après confirmation par e-mail', 'Votre consentement (double confirmation)', 'Oui'],
                            ],
                        ]],
                        'Aucune bibliothèque de publicité, de mesure d’audience ou de rapport de plantage n’est intégrée à l’application. Nous ne lisons ni votre carnet d’adresses, ni votre agenda, ni votre galerie : les pièces jointes passent par le sélecteur de fichiers de votre téléphone.',
                    ],
                ],
                [
                    'id' => 'sensibles',
                    'title' => 'Données sensibles, position et signalements',
                    'blocks' => [
                        ['list' => [
                            'L’origine, l’orientation ou l’identité de genre ne sont enregistrées que si vous y consentez, séparément de l’inscription. Vous pouvez retirer ce consentement dans les paramètres ; le refus ne bloque aucune autre fonction.',
                            'Votre position n’est relevée qu’au moment où vous déclenchez une alerte SOS, application ouverte, si le partage est actif. Elle n’est jamais suivie en arrière-plan. Elle n’est visible que des personnes alertées (vos contacts de confiance et le point focal de votre zone).',
                            'Les récits de signalement, les notes de suivi, les coordonnées et la position d’une alerte, ainsi que le contenu des messages, sont chiffrés dans la base de données.',
                            'Un signalement sans compte ne demande ni identifiant ni adresse IP : seuls son contenu, son code de suivi et, si vous le choisissez, un moyen de vous recontacter sont enregistrés.',
                        ]],
                    ],
                ],
                [
                    'id' => 'destinataires',
                    'title' => 'Qui reçoit vos données',
                    'blocks' => [
                        ['list' => [
                            'L’équipe habilitée de Xomiton (modération, pair·es éducateur·rices, administration), chacune selon son rôle et seulement pour ce qui lui est utile.',
                            'Les personnes que vous alertez ou ciblez vous-même : vos contacts de confiance, le point focal de votre zone, les membres de l’annuaire que vous choisissez.',
                            'Nos prestataires techniques, qui agissent pour notre compte (section suivante).',
                            'Les autorités, uniquement si la loi nous y oblige.',
                        ]],
                        'Vos données ne sont jamais vendues et ne servent à aucune publicité.',
                    ],
                ],
                [
                    'id' => 'prestataires',
                    'title' => 'Prestataires et transferts hors du Bénin',
                    'blocks' => [
                        ['table' => [
                            'head' => ['Prestataire', 'Rôle', 'Données concernées', 'Lieu de traitement'],
                            'rows' => [
                                ['{host_name}', 'Hébergement du site, de l’API et de la base de données', 'Toutes les données de l’application', '{host_country}'],
                                ['Google (Firebase Cloud Messaging)', 'Acheminement des notifications sur Android', 'Jeton de l’appareil, contenu minimal de la notification (aperçus masqués possibles)', 'Hors du Bénin, y compris hors Union européenne'],
                                ['Prestataire d’envoi de SMS (Vonage ou Twilio)', 'SMS de secours d’une alerte SOS', 'Numéro du contact de confiance, texte du SMS', 'Hors du Bénin'],
                                ['Service d’envoi d’e-mails', 'Codes de vérification, notifications, newsletter', 'Adresse e-mail, contenu du message', '{host_country}'],
                            ],
                        ]],
                        '{sms_clause}',
                        '{assistant_clause}',
                        'Quand des données quittent le Bénin, nous nous assurons que le prestataire offre des garanties adaptées (engagement contractuel de protection des données, clauses contractuelles types ou pays reconnu comme offrant une protection suffisante) et nous ne lui transmettons que ce qui est nécessaire.',
                    ],
                ],
                [
                    'id' => 'conservation',
                    'title' => 'Combien de temps nous les conservons',
                    'blocks' => [
                        ['table' => [
                            'head' => ['Donnée', 'Durée de conservation'],
                            'rows' => [
                                ['Compte actif', 'Tant que votre compte existe'],
                                ['Compte supprimé', 'Accès coupé tout de suite, données personnelles effacées après {grace_days} jours (anonymisation)'],
                                ['Dossiers de signalement et alertes', 'Durée du dossier, puis {case_years} ans après sa clôture, puis anonymisation'],
                                ['Origine, orientation, identité de genre', 'Jusqu’au retrait de votre consentement'],
                                ['Codes de vérification', '{otp_hours} heures'],
                                ['Journal des actions sensibles (adresse IP, navigateur)', '{audit_days} jours'],
                                ['Journaux d’envoi d’e-mails', '{mail_days} jours ; le contenu d’un e-mail est vidé dès son envoi (ou après {failed_mail_days} jours en cas d’échec)'],
                                ['Appareils connectés inactifs', 'Révoqués après {device_days} jours sans activité'],
                                ['Accusés de synchronisation hors connexion', '{sync_days} jours'],
                                ['Compteurs de consultation des pages (sans donnée personnelle)', '{events_days} jours'],
                                ['Export de vos données', 'Lien valable {export_hours} heures, fichier effacé ensuite'],
                                ['Demande de suppression faite depuis le site', 'Seule une empreinte de l’adresse est gardée, pour limiter les abus, puis effacée'],
                            ],
                        ]],
                    ],
                ],
                [
                    'id' => 'droits',
                    'title' => 'Vos droits',
                    'blocks' => [
                        'Vous pouvez à tout moment :',
                        ['list' => [
                            'accéder à vos données et en obtenir une copie : Paramètres > Mes données (export disponible dans l’application) ;',
                            'corriger vos informations depuis votre profil ;',
                            'supprimer votre compte et vos données : dans l’application (Paramètres > Sécurité > Supprimer mon compte) ou sur le site, même sans accès à votre compte, depuis la page « Supprimer mon compte et mes données » ;',
                            'retirer un consentement (données sensibles, notifications, newsletter) sans conséquence sur les autres fonctions ;',
                            'vous opposer à un traitement fondé sur notre intérêt légitime, ou en demander la limitation.',
                        ]],
                        'Pour exercer un droit que l’application ne couvre pas, écrivez à {dpo_email} ou à {contact_email}. Nous répondons dans un délai d’un mois au plus. Si vous estimez que vos droits ne sont pas respectés, vous pouvez saisir l’Autorité de protection des données personnelles du Bénin (APDP).',
                    ],
                ],
                [
                    'id' => 'securite',
                    'title' => 'Comment nous protégeons vos données',
                    'blocks' => [
                        ['list' => [
                            'Connexion chiffrée (HTTPS) pour tous les échanges ; aucune sauvegarde automatique Android de l’application.',
                            'Chiffrement des champs sensibles dans la base de données, mots de passe conservés sous forme hachée.',
                            'Accès réservé à l’équipe habilitée, selon des rôles précis ; journal des actions sensibles.',
                            'Limitation des tentatives de connexion et de demandes, protection contre l’énumération de comptes.',
                            'Mode discret dans l’application : icône et nom neutres, aperçus masqués, verrouillage par code.',
                        ]],
                        'Aucun système n’est infaillible : en cas de violation de données susceptible de vous exposer, nous vous en informerons et préviendrons l’autorité compétente dans les délais prévus par la loi.',
                    ],
                ],
                [
                    'id' => 'mineurs',
                    'title' => 'Âge minimum et mineur·es',
                    'blocks' => [
                        'Xomiton est réservé aux personnes de {min_age} ans ou plus. À l’inscription, vous confirmez avoir {min_age} ans ou plus ; nous ne demandons pas de date de naissance. Si nous apprenons qu’une personne plus jeune a créé un compte, nous le supprimons.',
                        'Si un signalement révèle qu’une personne mineure est en danger, l’équipe peut l’orienter vers les services de protection compétents, dans l’intérêt de cette personne.',
                    ],
                ],
                [
                    'id' => 'cookies',
                    'title' => 'Cookies et stockage local',
                    'blocks' => [
                        'Xomiton n’utilise que des cookies et un stockage local indispensables au fonctionnement. Le détail est sur la page Cookies.',
                    ],
                ],
                [
                    'id' => 'modifications',
                    'title' => 'Modifications et contact',
                    'blocks' => [
                        'Nous mettons cette politique à jour quand nos pratiques changent ; la date de dernière mise à jour figure en haut de page. En cas de changement important, nous vous en informons dans l’application.',
                        'Une question ? Écrivez à {contact_email}.',
                    ],
                ],
            ],
        ],

        // ------------------------------------------------------------------------------------------------------
        'terms' => [
            'title' => 'Conditions d’utilisation',
            'lead' => 'Les règles qui s’appliquent quand vous utilisez le site et l’application Xomiton.',
            'sections' => [
                [
                    'id' => 'objet',
                    'title' => 'À quoi sert Xomiton',
                    'blocks' => [
                        '{site_name} est un espace numérique sûr, édité par {publisher_name}, pour s’informer, échanger et être soutenu·e, avec une attention particulière aux filles et aux femmes, et un accueil sans jugement. Le site et l’application sont gratuits.',
                        'Utiliser Xomiton, c’est accepter ces conditions et la charte de bienveillance.',
                    ],
                ],
                [
                    'id' => 'acces',
                    'title' => 'Accès et compte',
                    'blocks' => [
                        ['list' => [
                            'Il faut avoir {min_age} ans ou plus pour créer un compte.',
                            'Vous choisissez un pseudonyme ; vous n’êtes pas obligé·e d’utiliser votre vrai nom.',
                            'Vous êtes responsable de la confidentialité de votre mot de passe et de votre appareil. Utilisez un mot de passe fort et ne le partagez pas.',
                            'Les inscriptions peuvent être ouvertes, limitées ou suspendues par l’administration.',
                        ]],
                    ],
                ],
                [
                    'id' => 'regles',
                    'title' => 'Règles de la communauté',
                    'blocks' => [
                        'Vous vous engagez à respecter les autres, quelle que soit leur identité ou leur orientation. Sont interdits :',
                        ['list' => [
                            'les propos haineux, violents, discriminatoires, ou le harcèlement ;',
                            'le fait de révéler l’identité, l’orientation ou les informations privées d’une autre personne ;',
                            'le chantage, l’usurpation d’identité, la publicité, le démarchage et les contenus illégaux ;',
                            'toute tentative de contourner la sécurité du service.',
                        ]],
                        'Tout manquement peut entraîner un avertissement, la suppression d’un contenu, une suspension ou une exclusion décidés par l’équipe de modération. Vous pouvez signaler un contenu ou une personne à tout moment.',
                    ],
                ],
                [
                    'id' => 'contenus',
                    'title' => 'Vos contenus',
                    'blocks' => [
                        'Vous restez propriétaire de ce que vous publiez. Vous nous autorisez à l’afficher dans {site_name} selon vos réglages de visibilité, et à le modérer. Vous garantissez avoir le droit de le publier.',
                    ],
                ],
                [
                    'id' => 'limites',
                    'title' => 'Ce que Xomiton ne remplace pas',
                    'blocks' => [
                        ['list' => [
                            'Xomiton ne remplace ni les services d’urgence, ni les soins médicaux, ni l’aide juridique. En cas de danger immédiat, contactez les services d’urgence de votre pays.',
                            'Le bouton SOS prévient vos contacts de confiance et le point focal de votre zone. Il ne garantit ni une intervention, ni un délai de réponse.',
                            'Les ressources et l’assistant informent et orientent. Ils ne donnent ni diagnostic, ni avis médical ou juridique.',
                            'Une personne formée lit vos messages sous 24 heures, sans garantie de présence permanente.',
                        ]],
                    ],
                ],
                [
                    'id' => 'disponibilite',
                    'title' => 'Disponibilité et responsabilité',
                    'blocks' => [
                        'Le service est mis à jour régulièrement ; des interruptions peuvent survenir. Nous mettons tout en œuvre pour le rendre sûr et disponible, sans pouvoir garantir un accès continu. Dans la limite permise par la loi, {publisher_name} n’est pas responsable des contenus publiés par les membres, ni des dommages liés à une interruption ou à un usage contraire à ces conditions.',
                    ],
                ],
                [
                    'id' => 'donnees',
                    'title' => 'Vos données',
                    'blocks' => [
                        'Le traitement de vos données est décrit dans la politique de confidentialité. Vous pouvez supprimer votre compte à tout moment, dans l’application ou depuis la page « Supprimer mon compte et mes données ».',
                    ],
                ],
                [
                    'id' => 'evolution',
                    'title' => 'Évolution des conditions et droit applicable',
                    'blocks' => [
                        'Nous pouvons modifier ces conditions ; la date de dernière mise à jour figure en haut de page, et nous vous prévenons en cas de changement important. Continuer à utiliser Xomiton après ce changement vaut acceptation.',
                        'Ces conditions sont régies par le droit béninois. En cas de litige, et à défaut d’accord amiable, la juridiction compétente est : {jurisdiction}.',
                        'Contact : {contact_email}.',
                    ],
                ],
            ],
        ],

        // ------------------------------------------------------------------------------------------------------
        'notice' => [
            'title' => 'Mentions légales',
            'lead' => 'Qui édite et héberge Xomiton, comment nous joindre et comment signaler un contenu.',
            'sections' => [
                [
                    'id' => 'editeur',
                    'title' => 'Éditeur du site et de l’application',
                    'blocks' => [
                        ['dl' => [
                            ['Nom', '{publisher_name}'],
                            ['Forme juridique', '{legal_form}'],
                            ['Enregistrement', '{registration}'],
                            ['Adresse', '{address}'],
                            ['Directeur ou directrice de la publication', '{director}'],
                            ['Téléphone', '{editor_phone}'],
                            ['E-mail', '{contact_email}'],
                        ]],
                    ],
                ],
                [
                    'id' => 'hebergeur',
                    'title' => 'Hébergeur',
                    'blocks' => [
                        ['dl' => [
                            ['Nom', '{host_name}'],
                            ['Adresse', '{host_address}'],
                            ['Pays', '{host_country}'],
                        ]],
                    ],
                ],
                [
                    'id' => 'donnees',
                    'title' => 'Protection des données',
                    'blocks' => [
                        'Personne chargée de la protection des données : {dpo_name}, joignable à {dpo_email}. Le détail des traitements est dans la politique de confidentialité.',
                    ],
                ],
                [
                    'id' => 'propriete',
                    'title' => 'Propriété intellectuelle',
                    'blocks' => [
                        'La marque, le logo, la charte graphique, les textes et les illustrations de {site_name} sont protégés. Toute reprise sans autorisation écrite est interdite, sauf courte citation avec mention de la source. Les contenus publiés par les membres restent leur propriété.',
                    ],
                ],
                [
                    'id' => 'signaler',
                    'title' => 'Signaler un contenu illicite ou dangereux',
                    'blocks' => [
                        'Pour signaler un contenu ou une personne, utilisez le bouton « Signaler » du site ou de l’application, ou écrivez à {contact_email}. L’équipe examine chaque signalement dans la confidentialité.',
                    ],
                ],
                [
                    'id' => 'accessibilite',
                    'title' => 'Accessibilité',
                    'blocks' => [
                        'Nous cherchons à rendre Xomiton utilisable par toutes et tous : contrastes suffisants, navigation au clavier, textes alternatifs pour les images, formulaires étiquetés, respect du réglage de réduction des animations. Si un contenu vous pose problème, écrivez à {contact_email} : nous vous répondrons et chercherons une solution.',
                    ],
                ],
                [
                    'id' => 'droit',
                    'title' => 'Droit applicable',
                    'blocks' => [
                        'Le site et l’application sont soumis au droit béninois. Juridiction compétente : {jurisdiction}.',
                    ],
                ],
            ],
        ],

        // ------------------------------------------------------------------------------------------------------
        'cookies' => [
            'title' => 'Cookies et stockage local',
            'lead' => 'Xomiton n’utilise que ce qui est indispensable à son fonctionnement. Aucun cookie publicitaire, aucune mesure d’audience tierce.',
            'sections' => [
                [
                    'id' => 'principe',
                    'title' => 'Ce que nous utilisons',
                    'blocks' => [
                        'Les cookies et le stockage local ci-dessous sont strictement nécessaires : ils ne demandent donc pas votre accord préalable et le site n’affiche pas de bandeau de consentement. Nous n’utilisons ni Google Analytics, ni pixel publicitaire, ni cookie de réseau social.',
                        ['table' => [
                            'head' => ['Nom', 'Rôle', 'Durée'],
                            'rows' => [
                                ['{session_cookie}', 'Garde votre session ouverte et protège les formulaires', '{session_minutes} minutes d’inactivité'],
                                ['XSRF-TOKEN', 'Protection contre les envois de formulaires frauduleux', 'Durée de la session'],
                                ['remember_device', 'Reconnaître un appareil de confiance, uniquement si vous le demandez à la connexion', '{remember_days} jours'],
                                ['Préférences d’affichage (stockage local)', 'Thème clair ou sombre', 'Jusqu’à effacement par vous'],
                                ['Brouillons et file d’envoi hors connexion (stockage local)', 'Garder un signalement non envoyé ou une saisie hors connexion sur votre appareil', 'Brouillon : {draft_days} jours ; file : jusqu’à l’envoi'],
                                ['Ressources hors connexion (cache du navigateur)', 'Lire les ressources que vous avez marquées sans connexion', 'Jusqu’à effacement par vous'],
                            ],
                        ]],
                    ],
                ],
                [
                    'id' => 'controle',
                    'title' => 'Garder la main',
                    'blocks' => [
                        'Vous pouvez supprimer ces données à tout moment dans les réglages de votre navigateur. Sans le cookie de session, vous ne pouvez plus rester connecté·e.',
                        'Les compteurs de consultation des pages sont anonymes, sans cookie ni identifiant, et conservés {events_days} jours.',
                        ['note' => 'Si nous ajoutons un jour un cookie non indispensable (par exemple une mesure d’audience), nous afficherons un choix clair avant de le déposer et nous mettrons cette page à jour.'],
                    ],
                ],
            ],
        ],
    ],
];

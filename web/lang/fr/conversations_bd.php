<?php

declare(strict_types=1);

/*
 * Conversations en BD (P20) : textes partagés du rendu des bulles (composant Blade `x-bd.panel` et jumeau Vue
 * `XmBdPanel`), des statuts, des types et des contrôles de la grammaire (ConversationGrammar : import, administration,
 * éditeur des membres au lot B). Exporté vers vue-i18n et Flutter par `php artisan lang:export`. Registre neutre (lu
 * par les lecteurs d'écran et repris dans les écrans publics, membres et d'administration).
 * Lot B (P20-07 à P20-09) : `author`, `errors`, `filter` (neutres, repris par l'API et la modération) ; `member` et
 * `editor` : espace membre, tutoiement comme les autres pages membres.
 */
return [

    'status' => [
        'brouillon' => 'Brouillon',
        'proposee' => 'Proposée',
        'publiee' => 'Publiée',
        'refusee' => 'Refusée',
        'retiree' => 'Retirée',
    ],

    'types' => [
        'parole' => 'Parole',
        'pensee' => 'Pensée',
        'cri' => 'Cri',
        'tremblee' => 'Voix tremblée',
        'chuchote' => 'Chuchotement',
        'joie' => 'Joie',
        'message' => 'Message écrit',
        'voix_appareil' => 'Voix d’un appareil',
        'cartouche' => 'Cartouche (narration)',
    ],

    'tail_targets' => [
        'personnage' => 'Vers le personnage',
        'ecran' => 'Vers l’écran ou l’appareil',
        'bord-gauche' => 'Hors de l’image, à gauche',
        'bord-droit' => 'Hors de l’image, à droite',
        'bord-haut' => 'Hors de l’image, en haut',
        'bord-bas' => 'Hors de l’image, en bas',
    ],

    // Lecture des bulles dans l'ordre (GRAMMAIRE_DES_BULLES.md §3) : la forme n'est jamais le seul repère.
    'sr' => [
        'verbs' => [
            'parole' => 'dit',
            'pensee' => 'pense',
            'cri' => 'crie',
            'tremblee' => 'dit, mal à l’aise',
            'chuchote' => 'chuchote',
            'joie' => 'dit avec joie',
        ],
        'line' => ':name :verb : :text',
        'line_offscreen' => ':name :verb, hors de l’image : :text',
        'message_received' => 'Message reçu de :name : :text',
        'message_sent' => 'Message envoyé par :name : :text',
        'device' => 'Voix au téléphone, :name : :text',
        'narration' => 'Narration : :text',
        'masked' => 'Un message insultant, masqué : il n’est pas reproduit.',
        'someone' => 'Quelqu’un',
    ],

    'panel' => [
        'counter' => 'Case :n sur :count',
        'title' => 'Case :n sur :count – :title',
        'transcript' => 'Texte de la case',
        'no_bubble' => 'Case sans parole.',
        'image_pending' => 'Illustration à venir',
        'scene' => 'Scène : :description',
    ],

    'grammar' => [
        'where_panel' => 'Case :panel :',
        'where_bubble' => 'Case :panel, bulle :bubble :',
        'panel_count' => 'une conversation compte de :min à :max cases (:count actuellement).',
        'no_bubble' => 'la conversation n’a aucune bulle.',
        'type' => 'type de bulle inconnu.',
        'empty' => 'la bulle est vide.',
        'too_long' => ':count mots : :max au plus. Coupez-la en bulles associées.',
        'too_many' => ':count bulles hors cartouche : :max au plus par case.',
        'outside' => 'la bulle sort de la case (seul le cri peut déborder un peu).',
        'overlap' => 'la bulle chevauche la bulle :other sans être marquée « superposée ».',
        'caption_tail' => 'un cartouche n’a pas de queue.',
        'tail_missing' => 'la queue manque : elle indique qui parle.',
        'tail_target' => 'cible de la queue inconnue.',
        'tail_point' => 'la pointe de la queue doit avoir une position.',
        'tail_outside' => 'la pointe de la queue sort de la case.',
        'tail_inside' => 'la pointe de la queue est dans la bulle.',
        'link' => 'la bulle associée doit suivre une bulle précédente de la même case.',
        'order' => 'ordre de lecture à vérifier : la bulle est placée plus haut et plus à gauche que la précédente.',
        'crowd' => 'plus de 3 bulles superposées : effet de brouhaha, à réserver à cet usage.',
    ],

    // Création d'un·e membre publiée (Q99) : anonyme par défaut.
    'author' => [
        'anonymous' => 'Anonyme',
    ],

    // Refus de l'API et de la modération (BdConversationRefused).
    'errors' => [
        'bd_content_rejected' => ':message',
        'bd_invalid_input' => ':message',
        'bd_invalid' => 'La conversation ne peut pas encore être proposée : :message',
        'bd_not_editable' => 'Cette conversation ne peut plus être modifiée : retire-la d’abord si elle est proposée ou publiée.',
        'bd_not_submittable' => 'Cette conversation est déjà proposée ou publiée.',
        'bd_not_withdrawable' => 'Seule une conversation proposée ou publiée peut être retirée.',
        'bd_not_pending' => 'Cette conversation n’attend plus de décision : la liste a été mise à jour.',
        'bd_own' => 'Une autre personne de l’équipe doit relire votre propre création.',
        'bd_reason_required' => 'Le motif du refus est obligatoire : la personne le lira pour corriger sa création.',
        'bd_too_many' => 'Tu as déjà :max conversations. Supprime un brouillon pour en créer une nouvelle.',
        'scene_unknown' => 'Cette scène n’est plus disponible : choisis une autre scène dans la bibliothèque.',
        'character_unknown' => 'Ce personnage n’est pas dans la scène : choisis un personnage présent dans la case ou la narration.',
        'character_required' => 'Choisis qui parle : un personnage de la scène, ou la narration (cartouche).',
    ],

    // Filtres de contenu (ConversationContentFilter) : dire quoi retirer, jamais le terme filtré.
    'filter' => [
        'phone' => 'Retire le numéro de téléphone : une conversation peut devenir publique, et un numéro permet de retrouver quelqu’un.',
        'email' => 'Retire l’adresse e-mail : une conversation peut devenir publique, et une adresse permet de retrouver quelqu’un.',
        'link' => 'Retire le lien : les liens ne sont pas permis dans les conversations en BD.',
        'contact' => 'Retire les coordonnées (adresse, lien de messagerie) : elles permettent de retrouver quelqu’un.',
        'banned' => 'Ce texte contient un mot qui n’est pas permis sur Xomiton. Reformule-le avec d’autres mots.',
    ],

    // « Mes conversations en BD » (espace membre, tu).
    'member' => [
        'title' => 'Mes conversations en BD',
        'intro' => 'Crée ta propre conversation à partir de scènes illustrées : choisis les cases, ajoute les bulles, puis relis l’aperçu. Elle reste privée tant que tu ne la proposes pas. L’équipe relit chaque proposition avant de la publier.',
        'new_title' => 'Nouvelle conversation',
        'create' => 'Créer et ouvrir l’éditeur',
        'full' => 'Tu as atteint :max conversations. Supprime un brouillon pour en créer une nouvelle.',
        'list_title' => 'Mes créations',
        'empty' => 'Tu n’as encore créé aucune conversation.',
        'meta' => '{0} Aucune case · modifiée le :date|{1} :count case · modifiée le :date|[2,*] :count cases · modifiée le :date',
        'private' => 'privée, visible par toi seul·e',
        'refusal' => 'Motif de l’équipe :',
        'edit' => 'Modifier',
        'view' => 'Voir',
        'pdf' => 'PDF',
        'public' => 'Voir la page publique',
        'withdraw' => 'Retirer',
        'delete' => 'Supprimer',
        'confirm_withdraw' => 'Retirer « :title » ? Elle ne sera plus visible ni proposée. Tu pourras la modifier puis la proposer de nouveau.',
        'confirm_delete' => 'Supprimer définitivement « :title » ?',
        'withdrawn' => '« :title » est retirée.',
        'deleted' => '« :title » est supprimée.',
    ],

    // Éditeur (espace membre, tu).
    'editor' => [
        'title' => 'Éditeur de conversation en BD',
        'intro' => 'Ton brouillon est privé et enregistré automatiquement. Aucune image ne peut être importée : les cases viennent de la bibliothèque de scènes de Xomiton.',
        'back' => 'Mes conversations',
        'modes' => 'Affichage',
        'mode_edit' => 'Modifier',
        'mode_preview' => 'Aperçu',
        'preview_title' => 'Aperçu de toute la conversation',
        'status_title' => 'Statut : :status',
        'narration' => 'Narration (cartouche)',
        'fields' => [
            'title' => 'Titre',
            'summary' => 'Résumé',
            'panels' => 'Cases',
        ],
        'hints' => [
            'title' => 'De :min à :max caractères.',
            'summary' => 'Une ou deux phrases pour présenter la situation.',
        ],
        'save' => [
            'saving' => 'Enregistrement…',
            'saved' => 'Enregistré à :time',
            'error' => 'Pas encore enregistré : vérifie ta connexion, l’éditeur réessaiera à la prochaine modification.',
            'blocked' => 'Pas enregistré : :message',
        ],
        'readonly' => [
            'brouillon' => 'Brouillon privé.',
            'proposee' => 'Ta conversation attend la relecture de l’équipe. Elle n’est visible par personne d’autre. Tu peux la retirer pour la modifier.',
            'publiee' => 'Ta conversation est publiée. Pour la modifier, retire-la : elle sera relue de nouveau quand tu la proposeras.',
            'refusee' => 'L’équipe n’a pas publié cette conversation. Corrige-la puis propose-la de nouveau.',
            'retiree' => 'Conversation retirée : elle n’est plus visible. Tu peux la modifier puis la proposer de nouveau.',
        ],
        'panels' => [
            'title' => 'Cases (:count sur :max)',
            'hint' => 'Une conversation compte de :min à :max cases. Choisis chaque scène dans la bibliothèque.',
            'add' => 'Ajouter une case',
            'list' => 'Cases de la conversation',
            'open' => '{0} Case :n (sans bulle)|{1} Case :n (1 bulle)|[2,*] Case :n (:count bulles)',
            'empty' => 'Aucune case pour l’instant : ajoute une première case.',
            'earlier' => 'Avancer la case :n',
            'later' => 'Reculer la case :n',
            'change_scene' => 'Changer de scène',
            'remove' => 'Retirer la case',
            'confirm_remove' => 'Retirer la case :n et ses bulles ?',
        ],
        'scenes' => [
            'title_add' => 'Choisir la scène de la nouvelle case',
            'title_replace' => 'Changer la scène de la case :n',
            'search' => 'Rechercher une scène (lieu, personnage…)',
            'count' => '{0} Aucune scène trouvée|{1} 1 scène|[2,*] :count scènes',
            'cast' => 'Personnages :',
            'no_cast' => 'aucun personnage',
            'zone' => 'zone calme pour les bulles : :zone',
            'choose' => 'Choisir cette scène',
            'more' => 'Afficher plus de scènes',
            'cancel' => 'Annuler',
        ],
        'stage' => [
            'title' => 'Case :n sur :count',
            'help' => 'Pour déplacer une bulle : fais-la glisser, ou choisis-la puis utilise les flèches du clavier (Maj + flèche pour aller plus vite) ou les boutons de position.',
            'bubble_handle' => 'Bulle :n, :who : :text. Flèches pour déplacer.',
            'tail_handle' => 'Pointe de la queue de la bulle :n. Flèches pour déplacer.',
            'empty_text' => 'texte à écrire',
            'moved' => 'Bulle :n déplacée : :x % depuis la gauche, :y % depuis le haut.',
            'tail_moved' => 'Pointe de la bulle :n : :x % depuis la gauche, :y % depuis le haut.',
        ],
        'bubbles' => [
            'title' => '{0} Bulles|{1} Bulles (1)|[2,*] Bulles (:count)',
            'add' => 'Ajouter une bulle',
            'list' => 'Bulles de la case, dans l’ordre de lecture',
            'item' => ':n. :type',
            'empty' => 'Pas encore de bulle : une case peut rester silencieuse, mais la conversation doit en avoir au moins une.',
        ],
        'bubble' => [
            'legend' => 'Bulle :n',
            'type' => 'Type de bulle',
            'character' => 'Qui parle ?',
            'text' => 'Texte',
            'words' => ':count mots sur :max',
            'emphasis' => 'Mots en gras',
            'emphasis_hint' => 'Recopie les mots à mettre en valeur, séparés par des virgules.',
            'tail' => 'La queue de la bulle pointe',
            'overlap' => 'Bulle superposée',
            'move_bubble' => 'Déplacer la bulle',
            'move_tail' => 'Déplacer la pointe de la queue',
            'arrows' => [
                'left' => 'Déplacer la bulle vers la gauche',
                'up' => 'Déplacer la bulle vers le haut',
                'down' => 'Déplacer la bulle vers le bas',
                'right' => 'Déplacer la bulle vers la droite',
            ],
            'tail_arrows' => [
                'left' => 'Déplacer la pointe vers la gauche',
                'up' => 'Déplacer la pointe vers le haut',
                'down' => 'Déplacer la pointe vers le bas',
                'right' => 'Déplacer la pointe vers la droite',
            ],
            'earlier' => 'Lire plus tôt',
            'later' => 'Lire plus tard',
            'remove' => 'Supprimer la bulle',
        ],
        'checks' => [
            'title' => 'Contrôles de la grammaire des bulles',
            'ok' => 'Tout est en ordre : tu peux proposer ta conversation.',
            'error' => 'À corriger :',
            'warning' => 'À vérifier :',
        ],
        'submit' => [
            'title' => 'Proposer à la publication',
            'text' => 'L’équipe relit ta conversation avant de la publier. Une fois publiée, elle est publique et peut être trouvée par les moteurs de recherche. Tu peux la retirer à tout moment.',
            'pseudonym' => 'Afficher mon pseudonyme',
            'pseudonym_hint' => 'Si tu ne coches pas, ta conversation sera publiée sous le nom « Anonyme ».',
            'button' => 'Proposer à la publication',
            'blocked' => '{1} 1 point à corriger avant de proposer (voir les contrôles).|[2,*] :count points à corriger avant de proposer (voir les contrôles).',
        ],
        'export' => [
            'pdf' => 'Télécharger en PDF',
            'png_panels' => 'Images PNG des cases',
            'png_strip' => 'Image PNG en bande',
            'working' => 'Préparation des images…',
            'done' => 'Images prêtes : le téléchargement a commencé.',
            'failed' => 'Les images n’ont pas pu être préparées dans ce navigateur. Le PDF reste disponible.',
            'source' => 'Conversation en BD créée sur Xomiton (:site)',
        ],
    ],

];

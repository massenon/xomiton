<?php

declare(strict_types=1);

/*
 * Outils CCC en lecture `/ccc` (P8-12, lot outils-ccc ; prototype ccc.php, manuel-pair-animateur.php, boites-a-images.php,
 * bd-ssr.php), espace connecté : tutoiement (VOICE_AND_TONE.md §3). Une seule implémentation pour le membre et le pair
 * (P9-05, « mode présentation »). Pas de quiz : aucune source de questions en base. Exporté vers vue-i18n par
 * `php artisan lang:export`.
 */
return [
    'title' => 'Outils CCC',
    'heading' => 'Outils CCC interactifs',
    'tagline' => 'Communication pour le changement de comportement',
    'intro' => 'Les outils papier du terrain, rendus numériques : à utiliser en séance de groupe, en entretien individuel ou pour te former.',
    'presentation' => [
        'badge' => 'Mode présentation',
        'notice' => 'Tu es en mode présentation : le guide de l’animateur·rice s’affiche avec chaque image du mode animation.',
    ],

    'tabs' => [
        'label' => 'Choisir un outil',
        'manuel' => 'Manuel',
        'boites_images' => 'Boîtes à images',
        'bd' => 'BD SSR',
        'lois' => 'Lois',
    ],

    'empty' => [
        'manuel' => 'Aucun module du manuel n’est publié pour le moment.',
        'boites_images' => 'Aucune boîte à images n’est publiée pour le moment.',
        'bd' => 'Aucune bande dessinée n’est publiée pour le moment.',
        'lois' => 'Aucun texte de loi n’est publié pour le moment.',
        'hint' => 'Reviens bientôt, de nouveaux outils sont ajoutés régulièrement.',
    ],

    'image' => [
        'loading' => 'Image en cours de chargement',
        'failed' => 'Image indisponible pour le moment.',
    ],

    // Lecture hors ligne (application mobile) : copie locale de la dernière réponse réussie, images sur disque.
    'offline' => [
        'status' => 'Hors ligne',
        'saved_on' => 'Contenu enregistré le :date.',
        'notice' => 'Tu lis la copie enregistrée sur ton téléphone. Les nouveautés et les outils retirés se mettront à jour dès que tu seras de nouveau connecté·e.',
        'refresh' => 'Actualiser',
        'refresh_label' => 'Actualiser les outils CCC',
        'expired' => 'La copie enregistrée date de plus de :days jours : elle n’est plus affichée. Connecte-toi à Internet pour récupérer les outils.',
        'tool_available' => 'Disponible hors ligne',
        'download' => [
            'cta' => 'Télécharger pour lire hors ligne',
            'hint' => 'Enregistre les images sur ton téléphone pour animer une séance sans réseau. Préfère le Wi-Fi : le téléchargement consomme des données mobiles.',
            'progress' => 'Téléchargement des images : :done sur :total.',
            'cancel' => 'Annuler le téléchargement',
            'cancelled' => 'Téléchargement annulé. Les images déjà enregistrées restent disponibles.',
            'done' => 'Toutes les images sont enregistrées : tu peux animer sans réseau.',
            'failed' => '{1} 1 image n’a pas pu être enregistrée. Réessaie avec un meilleur réseau.|[2,*] :count images n’ont pas pu être enregistrées. Réessaie avec un meilleur réseau.',
            'full' => 'La place réservée aux images est pleine : certaines images ne sont pas enregistrées.',
            'needs_network' => 'Connecte-toi à Internet pour télécharger les images.',
        ],
    ],

    'manual' => [
        'heading' => 'Manuel du pair animateur·rice',
        'intro' => 'Coche les points que tu as vus et imprime ton récapitulatif. Ta progression reste sur cet appareil : elle n’est jamais envoyée au serveur.',
        'modules_count' => '{1} 1 module|[2,*] :count modules',
        'duration' => ':minutes min',
        'duration_label' => 'Durée : :minutes minutes',
        'chapters_count' => '{0} Aucun point|{1} 1 point|[2,*] :count points',
        'progress_count' => ':done sur :total points vus',
        'progress_module' => 'Progression du module « :title »',
        'progress_global' => 'Progression globale',
        'progress_percent' => ':percent %',
        'progress_announce' => ':done sur :total points vus dans ce module.',
        'global_announce' => 'Progression globale : :percent %.',
        'module_done' => 'Module terminé',
        'open' => 'Ouvrir le module',
        'back' => 'Tous les modules',
        'detail_label' => 'Module : :title',
        'chapters_heading' => 'Contenu du module',
        'chapter_seen' => 'Point vu : :chapter',
        'print' => 'Imprimer le récapitulatif',
        'reset' => 'Effacer ma progression',
        'reset_done' => 'Ta progression a été effacée de cet appareil.',
        'storage_unavailable' => 'Ton navigateur ne permet pas de garder ta progression : elle sera perdue en quittant la page.',
        'summary_heading' => 'Mon récapitulatif',
        'summary_line' => ':title : :done sur :total points vus',
        'summary_date' => 'Édité le :date',
        'summary_empty' => 'Aucun module publié.',
    ],

    'images' => [
        'heading' => 'Boîtes à images',
        'intro' => 'Ouvre le mode animation pour projeter les images au groupe, une par une, avec le guide pour lancer la discussion.',
        'start' => 'Mode animation',
        'start_one' => 'Mode animation : :title',
        'count' => '{1} 1 image|[2,*] :count images',
        'badge' => 'Image :number',
        'guide' => 'Guide de l’animateur·rice',
        'tips_heading' => 'Questions types pour animer',
        'tips' => [
            'Que vois-tu sur cette image ?',
            'Que ressent chaque personne ?',
            'Qu’est-ce qui pourrait se passer ensuite ?',
            'Que ferais-tu à sa place ?',
            'Qui pourrait aider, et comment ?',
        ],
        'tips_reminder' => 'Rappel : crée un cadre sûr (confidentialité, respect, droit de ne pas répondre) et oriente vers l’annuaire si une personne se confie.',
        'directory' => 'Ouvrir l’annuaire',
    ],

    'show' => [
        'label' => 'Mode animation',
        'counter' => 'Image :current sur :total',
        'announce' => 'Image :current sur :total : :title',
        'previous' => 'Image précédente',
        'next' => 'Image suivante',
        'close' => 'Fermer',
        'close_hint' => 'Fermer (Échap)',
        'keys' => 'Utilise les flèches ← → du clavier pour changer d’image, Échap pour fermer.',
        'guide' => 'Guide de l’animateur·rice',
    ],

    'comic' => [
        'heading' => 'Bandes dessinées SSR',
        'intro' => 'Des histoires courtes sur la santé sexuelle et reproductive, à lire seul·e ou en groupe.',
        'pages_count' => '{1} 1 planche|[2,*] :count planches',
        'read' => 'Lire la BD',
        'back' => 'Toutes les histoires',
        'reader_label' => 'Lecteur de bande dessinée : :title',
        'page_counter' => 'Planche :current sur :total',
        'page_announce' => 'Planche :current sur :total : :caption',
        'previous' => 'Planche précédente',
        'next' => 'Planche suivante',
        'finish' => 'Terminer',
        'progress' => 'Progression de la lecture',
        'keys' => 'Utilise les flèches ← → du clavier pour changer de planche.',
        'done_title' => 'Histoire terminée',
        'done' => 'Place à la discussion !',
        'discuss' => 'Discutons de l’histoire',
        'discuss_items' => [
            'Qu’a ressenti le personnage ?',
            'Qu’aurions-nous fait à sa place ?',
            'Quelles ressources de l’annuaire pouvaient l’aider ?',
        ],
        'restart' => 'Relire depuis le début',
        // P19-04 : BD publique, page /bd/{key} ouverte dans le navigateur (lecture et partage).
        'public_page' => 'Voir la page publique de la BD',
    ],

    'laws' => [
        'heading' => 'Lois et codes du Bénin',
        'intro' => 'Une bibliothèque juridique consultable, avec des résumés en langage simple.',
        'count' => '{1} 1 texte publié|[2,*] :count textes publiés',
        'open' => 'Ouvrir les lois et codes',
    ],
];

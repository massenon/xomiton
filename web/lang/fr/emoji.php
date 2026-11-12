<?php

declare(strict_types=1);

/*
 * Sélecteur d'émojis (composant Vue XmEmojiPicker, widget Flutter EmojiPickerSheet) : boutons, onglets, recherche,
 * annonces aux lecteurs d'écran. Espace connecté (publications, commentaires, messagerie) : tutoiement ; les libellés
 * du composant partagé restent neutres (VOICE_AND_TONE.md §3, règle 1). Les noms français des catégories et des émojis
 * ne sont pas ici : ils vivent dans la source de données resources/js/emoji/emojis.json (une seule source, copiée vers
 * mobile/lib/core/emoji/emoji_data.dart par scripts/emoji-sync.mjs).
 */
return [

    'trigger' => 'Ajouter un émoji',
    'title' => 'Choisir un émoji',
    'close' => 'Fermer le sélecteur d’émojis',
    'search_label' => 'Rechercher un émoji',
    'search_placeholder' => 'Par exemple : cœur, soleil…',
    'search_clear' => 'Effacer la recherche',
    'categories_label' => 'Catégories d’émojis',
    'recents' => 'Récents',
    'recents_empty' => 'Les émojis que tu utilises apparaîtront ici.',
    'results' => 'Résultats de la recherche',
    'results_count' => '{0} Aucun émoji trouvé.|{1} :count émoji trouvé.|[2,*] :count émojis trouvés.',
    'no_results' => 'Aucun émoji ne correspond à ta recherche.',
    'loading' => 'Chargement des émojis…',
    'load_failed' => 'Impossible de charger les émojis pour le moment.',
    'retry' => 'Réessayer',
    'added' => 'Émoji ajouté : :name',
    'limit_reached' => 'Limite de caractères atteinte : l’émoji n’a pas été ajouté.',
    'tone_label' => 'Teinte de peau',
    'tones' => [
        'default' => 'Teinte par défaut',
        'light' => 'Teinte claire',
        'medium_light' => 'Teinte moyennement claire',
        'medium' => 'Teinte moyenne',
        'medium_dark' => 'Teinte moyennement foncée',
        'dark' => 'Teinte foncée',
    ],
    'with_tone' => ':name, :tone',

];

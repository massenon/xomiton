<?php

declare(strict_types=1);

/*
 * API de lecture de l'application mobile (P6-08 : tableau de bord, thématiques, ressources, publications d'un compte ;
 * docs/API.md §11). Registre : tu (application mobile = espace connecté, VOICE_AND_TONE.md §3). Noms de champs des
 * messages de validation (lang/fr/validation.php) ; les libellés d'écran restent dans le catalogue du client.
 */
return [

    'api' => [
        'attributes' => [
            'categorie' => 'catégorie',
            'q' => 'recherche',
            'type' => 'type de ressource',
            'page' => 'page',
            'per_page' => 'nombre d’éléments par page',
        ],
    ],

];

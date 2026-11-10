<?php

declare(strict_types=1);

/*
 * Liste de DÉPART du filtre de contenus (D-IOS-9) : [mot, gravité, catégorie]. NEUTRE et courte : une structure et
 * quelques exemples génériques, la liste réelle sera complétée par l'équipe de modération (Administration › Modération ›
 * Mots filtrés) et validée par le conseil. Les coordonnées personnelles et les liens suspects sont des motifs de structure
 * (config/xomiton.php, `moderation.content_filter`), pas des mots.
 */
return [
    ['sale pédé', 'bloquer', 'haine'],
    ['gouine', 'bloquer', 'haine'],
    ['va mourir', 'bloquer', 'violence'],
    ['tarée', 'signaler', 'insulte'],
    ['casse-toi', 'signaler', 'insulte'],
    ['argent facile', 'signaler', 'autre'],
    ['clique ici', 'signaler', 'autre'],
];

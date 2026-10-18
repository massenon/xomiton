<?php

declare(strict_types=1);

/*
 * Actualités de démonstration (P4-10) : reprises de x_blog_articles() du prototype (inc/x_public.php l.6-22) — six
 * articles tirés des fiches thématiques (titre, résumé, catégorie, couverture, durée de lecture), publiés tous les six
 * jours avant le 10/10/2026, et le corps générique de blog-details.php l.9-14 (au vous). Semées par DemoNewsSeeder hors
 * production seulement ; aucune signature de personne (« Équipe Xomiton » à l'affichage, plan §7).
 */
return [

    'published_from' => '2026-10-10 08:00:00',

    // Id prototype de la fiche thématique (couverture covers/th-<id>.jpg semée par ContentSeeder), dans l'ordre du prototype.
    'thematiques' => [1, 3, 21, 16, 19, 23],

    // Écart en jours entre deux articles (prototype : -(k × 6 + 1) jours).
    'days_step' => 6,
    'days_offset' => 1,

    // Corps (blog-details.php l.9-14) ; `:summary` = résumé de la fiche.
    'sections' => [
        ['Comprendre', 'Ce sujet touche de nombreuses filles et femmes au Bénin. Le comprendre, c’est déjà reprendre du pouvoir : savoir nommer ce qui se passe permet de chercher de l’aide et de se protéger.'],
        ['Les repères essentiels', ':summary Ces repères sont issus des fiches thématiques de Xomiton, élaborées avec des pair·es animateur·rices et des professionnel·les.'],
        ['Que faire concrètement ?', 'Parlez-en à une personne de confiance, notez les faits (dates, lieux, preuves), puis contactez un·e professionnel·le de l’annuaire. Vous pouvez aussi utiliser le signalement anonyme ou, en cas de danger immédiat, le bouton SOS.'],
        ['Vous n’êtes pas seule', 'La communauté Xomiton est là pour vous écouter sans jugement. Rejoignez un groupe, participez à un atelier ou posez vos questions à l’assistant.'],
    ],
];

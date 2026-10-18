<?php

declare(strict_types=1);

/*
 * Ressources de démonstration (P4-03) : `/ressources` n'a pas de données dans le prototype (sa grille listait les
 * thématiques) et l'admin Contenu (P12-03) n'existe pas encore. Semées par DemoResourcesSeeder hors production
 * seulement (local, testing, staging). Titres des six fiches PDF : prototype ressources.php l.17-22 ; résumés au vous.
 *
 * Chaque ligne : [slug, type (ResourceKind), catégorie (slug `thematique`), titre, résumé, options] ; options :
 *   thematique (id prototype : couverture covers/th-<id>.jpg de la fiche), image (n° de couverture th-<n>.jpg),
 *   file (bool : PDF minimal écrit sur le disque privé), external_url, duration (min), offline (is_offline_available),
 *   status (`publie` par défaut), body (`steps` | `points` : liste reprise de thematiques_content.php pour la catégorie,
 *   ou HTML), days (ancienneté en jours depuis `published_from` : tri déterministe).
 */
return [

    'published_from' => '2026-10-01 08:00:00',

    'rows' => [
        ['fiche-memo-reconnaitre-les-5-formes-de-vbg', 'pdf', 'vbg', 'Fiche mémo : reconnaître les 5 formes de VBG',
            'Physique, psychologique, sexuelle, économique, numérique : une page à garder pour repérer les signaux d’alerte et savoir vers qui vous tourner.',
            ['thematique' => 8, 'image' => 8, 'file' => true, 'days' => 0]],
        ['fiche-mes-droits-en-cas-de-violence-au-benin', 'pdf', 'droits', 'Fiche : mes droits en cas de violence au Bénin',
            'Les textes qui vous protègent, les recours possibles et les structures qui accompagnent les victimes, résumés sur une page à imprimer.',
            ['thematique' => 6, 'image' => 6, 'file' => true, 'days' => 1]],
        ['fiche-que-faire-apres-une-agression-72-h', 'pdf', 'vbg', 'Fiche : que faire après une agression ? (72 h)',
            'Les gestes des premières 72 heures : vous mettre en sécurité, consulter, conserver les preuves et vous faire accompagner.',
            ['thematique' => 19, 'image' => 19, 'file' => true, 'offline' => true, 'days' => 2]],
        ['fiche-plan-de-securite-a-imprimer', 'pdf', 'vbg', 'Fiche : plan de sécurité à imprimer',
            'Contacts de confiance, lieux sûrs, documents à garder à portée de main : préparez votre plan de sécurité, à votre rythme.',
            ['thematique' => 7, 'image' => 7, 'file' => true, 'offline' => true, 'days' => 3]],
        ['fiche-hygiene-menstruelle-bien-choisir', 'pdf', 'regles', 'Fiche : hygiène menstruelle, bien choisir',
            'Serviette, tampon, cup ou culotte menstruelle : comparez les protections selon votre confort, votre budget et votre accès à l’eau.',
            ['thematique' => 9, 'image' => 9, 'file' => true, 'days' => 4]],
        ['fiche-contraception-comparatif-des-methodes', 'pdf', 'esc', 'Fiche : contraception, comparatif des méthodes',
            'Efficacité, durée, accès et effets possibles des principales méthodes, pour en parler sereinement avec un·e professionnel·le de santé.',
            ['thematique' => 22, 'image' => 22, 'file' => true, 'days' => 5]],
        ['guide-porter-plainte-pas-a-pas', 'guide', 'droits', 'Porter plainte au Bénin : le guide pas à pas',
            'Noter les faits, garder les preuves, prendre conseil puis vous faire accompagner : les étapes pour porter plainte sans être seule.',
            ['thematique' => 19, 'image' => 19, 'duration' => 8, 'body' => 'steps', 'days' => 6]],
        ['guide-respiration-contre-l-angoisse', 'guide', 'mental', 'Respirer pour calmer une crise d’angoisse : 5 exercices',
            'Des exercices simples, à faire n’importe où, pour ralentir le souffle et retrouver un peu de calme quand l’angoisse monte.',
            ['thematique' => 14, 'image' => 14, 'duration' => 5, 'body' => 'steps', 'days' => 7]],
        ['fiche-consentement-les-4-criteres', 'fiche', 'esc', 'Le consentement en 4 critères',
            'Libre, éclairé, spécifique et réversible : quatre repères pour reconnaître un vrai consentement et le faire respecter.',
            ['thematique' => 21, 'image' => 21, 'duration' => 3, 'body' => 'points', 'days' => 8]],
        // Pas de vidéo de démonstration : aucune adresse réelle et vérifiée (une vidéo YouTube inventée tromperait la recette).
        ['guide-comprendre-le-cycle-de-la-violence', 'guide', 'vbg', 'Comprendre le cycle de la violence',
            'Tension, explosion, justification, lune de miel : comprendre le cycle qui enferme dans une relation violente, et comment en sortir.',
            ['thematique' => 7, 'image' => 7, 'duration' => 4, 'days' => 9,
                'body' => '<p>Le cycle de la violence compte quatre phases : la tension qui monte, l’explosion, la justification puis la « lune de miel », avant que la tension ne remonte.</p><p>Reconnaître ce cycle aide à comprendre pourquoi il est si difficile de partir, et à demander de l’aide sans honte : vous n’êtes jamais responsable de la violence que vous subissez.</p>']],
        // Titre fidèle à la cible : le portail générique des services publics, pas une ligne d'écoute (aucun numéro vérifié).
        ['portail-des-services-publics-du-benin', 'lien', 'droits', 'Portail officiel des services publics du Bénin',
            'Le portail officiel de l’administration béninoise : démarches, services et coordonnées des institutions publiques.',
            ['image' => 3, 'external_url' => 'https://www.gouv.bj/', 'days' => 10]],
        ['fiche-brouillon-budget', 'guide', 'corps', 'Gérer son budget : premiers pas (brouillon)',
            'Brouillon de démonstration, jamais publié : il ne doit apparaître ni dans la liste, ni dans le sitemap.',
            ['thematique' => 27, 'image' => 27, 'status' => 'brouillon']],
    ],

];

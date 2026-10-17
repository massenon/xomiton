<?php

declare(strict_types=1);

/*
 * Contenu éditorial des thématiques (prototype_social/inc/x_thematiques_content.php) :
 * - `categories` : paragraphes, points à retenir, étapes et message « L'essentiel » par catégorie ;
 * - `specific` : introduction propre à certaines thématiques (clé = id prototype) ;
 * - `laws` : textes de loi cités, par clé ; `laws_by_category` et `laws_overrides` reproduisent x_them_legal().
 */
return [

    'categories' => [
        'vbg' => [
            'paragraphs' => [
                "Les violences basées sur le genre (VBG) désignent tout acte de violence exercé contre une personne en raison de son sexe, de son identité de genre ou de son orientation sexuelle réelle ou supposée. Elles touchent toutes les couches de la société, à la maison, à l'école, au travail, dans la rue et en ligne.",
                "Au Bénin, comme ailleurs, les violences sont encore trop souvent banalisées, attribuées à la « nature » de l'agresseur ou à la « faute » de la victime. Pour les filles, les femmes et les personnes LBTQ+, s'y ajoutent la peur d'être exposée (outing), le rejet familial et la méfiance envers les institutions, ce qui retarde la demande d'aide.",
                "Reconnaître la violence est une première étape de protection : une violence n'est jamais la faute de la personne qui la subit, quel que soit son comportement, sa tenue, son orientation ou sa relation avec l'agresseur. Les conséquences (blessures, anxiété, isolement, troubles du sommeil, perte d'estime de soi) sont réelles et méritent d'être prises en charge.",
                "Vous n'avez pas à affronter cela seule. À tout moment, vous pouvez signaler de façon anonyme, joindre un point focal, un·e psychologue ou un·e juriste de l'annuaire, ou déclencher une alerte SOS si vous êtes en danger immédiat. Vous décidez du rythme et de la suite à donner : porter plainte n'est pas obligatoire pour être écoutée et accompagnée.",
            ],
            'points' => ["La violence n'est jamais justifiée ni « méritée »", 'Elle peut être physique, psychologique, sexuelle, économique ou numérique', 'Les séquelles psychologiques sont aussi importantes que les blessures visibles', "Demander de l'aide est un acte de courage, pas de faiblesse", 'Vous restez maîtresse de vos décisions (plainte, hébergement, suivi)'],
            'steps' => ["Si vous êtes en danger immédiat, éloignez-vous et utilisez le bouton SOS ou appelez un contact d'urgence.", "Parlez à une personne de confiance ou à un point focal de l'annuaire.", 'Conservez les preuves : messages, photos, certificats médicaux, noms de témoins.', 'Consultez un centre de santé pour vos soins et un certificat médical si nécessaire.', 'Faites le point avec un·e juriste ou un·e psychologue sur les suites possibles.'],
            'retenir' => "Vous n'êtes pas responsable de la violence subie. Chaque démarche, même petite, compte, et vous pouvez avancer à votre rythme avec l'appui de l'équipe Xomiton.",
        ],
        'regles' => [
            'paragraphs' => [
                "Les règles sont un processus physiologique normal qui concerne les filles, les femmes et certains hommes trans et personnes non-binaires. Pourtant, elles restent entourées de tabous, de honte et de fausses croyances qui nuisent à la santé, à la scolarité et à l'estime de soi.",
                "Au Bénin, le coût des protections, le manque d'eau et de latrines adaptées dans certains établissements et le silence familial obligent parfois des jeunes à s'absenter de l'école ou à utiliser des solutions peu sûres. Mieux s'informer permet de choisir des protections adaptées à son budget et à son mode de vie.",
                "Une bonne hygiène menstruelle repose sur quelques gestes simples : changer régulièrement de protection, se laver les mains avant et après, éviter les produits parfumés à l'intérieur du corps et conserver ses protections réutilisables propres et sèches. Le confort et la sécurité passent avant tout.",
                "Certains signaux doivent conduire à consulter : douleurs invalidantes, saignements très abondants ou très prolongés, absence prolongée de règles, fièvre ou mauvaise odeur inhabituelle pendant l'usage d'une protection interne. Un·e professionnel·le de santé vous écoutera sans jugement.",
            ],
            'points' => ['Les règles ne rendent personne « impure » ou « malade »', 'Chaque corps est différent : cycle, durée et douleur varient', "Choisir sa protection selon son confort, son budget et son accès à l'eau", 'Parler des règles réduit la honte et les risques sanitaires', 'Consulter en cas de douleur forte, saignement anormal ou fièvre'],
            'steps' => ['Repérez la protection qui vous convient (serviette, tampon, cup, culotte menstruelle).', 'Changez-la toutes les 4 à 6 heures et lavez-vous les mains avant et après.', "Notez la durée et l'intensité de vos cycles dans un petit carnet.", 'Soulagez la douleur par la chaleur, le mouvement doux et le repos.', "Consultez si les signaux d'alerte apparaissent ou si les règles perturbent votre quotidien."],
            'retenir' => "Les règles sont normales. Disposer d'informations fiables, de protections accessibles et d'un espace sans honte est un droit qui protège votre santé et votre dignité.",
        ],
        'mental' => [
            'paragraphs' => [
                "La santé mentale englobe la façon dont nous pensons, ressentons et réagissons au quotidien. Stress, tristesse, anxiété ou fatigue émotionnelle sont des réactions humaines, mais lorsqu'elles durent ou s'intensifient, elles méritent attention et soutien.",
                "Les personnes LBTQ+ et les survivantes de violences portent souvent un fardeau supplémentaire : peur du jugement, rejet, secrets à garder, discriminations répétées. Ce « stress minoritaire » augmente le risque d'isolement et d'épuisement, d'où l'importance de réseaux de pairs et d'espaces sûrs.",
                "Certains outils simples aident à se réguler : respiration lente, ancrage dans le présent, activité physique douce, sommeil régulier, expression par l'écriture ou le dessin. Ils ne remplacent pas un accompagnement, mais ils offrent des appuis immédiats en cas de tension.",
                "Si vous avez des idées noires, ressentez un désespoir persistant ou ne parvenez plus à accomplir vos activités, parlez-en sans attendre à une personne de confiance ou à un·e psychologue de l'annuaire. En cas de danger immédiat pour vous-même, appelez les urgences ou déclenchez une alerte SOS.",
            ],
            'points' => ["Demander de l'aide est une force", 'Le stress prolongé a des effets sur le corps et le sommeil', 'Respiration, mouvement et parole sont des ressources gratuites', "Le soutien des pairs réduit l'isolement", 'Une alerte urgente doit toujours être prise au sérieux'],
            'steps' => ['Nommez ce que vous ressentez, sans le juger.', 'Pratiquez 5 minutes de respiration lente (inspirer 4 secondes, expirer 6 secondes).', 'Contactez une personne de confiance ou rejoignez un groupe de soutien.', "Prenez rendez-vous avec un·e psychologue via l'annuaire si le mal-être persiste.", 'Préparez un petit plan de sécurité : contacts, lieu calme, gestes qui apaisent.'],
            'retenir' => "Votre santé mentale compte autant que votre santé physique. Vous méritez d'être écoutée, soutenue et accompagnée sans jugement.",
        ],
        'droits' => [
            'paragraphs' => [
                "Connaître ses droits est un outil de protection puissant. Au Bénin, plusieurs textes garantissent l'égalité, l'intégrité physique, la santé et l'accès à la justice : la Constitution du 11 décembre 1990, le Code pénal, le Code des personnes et de la famille et la loi n°2011-26 du 9 janvier 2012 portant prévention et répression des violences faites aux femmes.",
                "Dans la pratique, l'accès à la justice reste difficile : méconnaissance des procédures, peur des représailles, coûts, pressions familiales ou sociales. Les structures d'aide juridique, les points focaux et les juristes partenaires de Xomiton existent pour lever ces obstacles et vous accompagner pas à pas.",
                'Pour faire valoir vos droits, la préparation compte : rassembler les preuves (messages, photos, certificats médicaux, témoignages), noter les dates et faits précis, et se faire accompagner par une personne de confiance ou un·e juriste. Vous pouvez demander des explications à chaque étape et être accompagnée lors des auditions.',
                "Pour les personnes LBTQ+, la vigilance est particulière : les protections contre les violences, le chantage, le harcèlement et les atteintes à la vie privée s'appliquent à toutes et tous. Un·e juriste de l'annuaire peut évaluer votre situation en toute confidentialité avant toute démarche.",
            ],
            'points' => ['Toute personne a droit à la protection et à la dignité', 'Les violences, le harcèlement et le chantage sont sanctionnés par la loi', 'Vous avez droit à une assistance médicale et juridique', 'Vous pouvez être accompagnée à chaque étape', 'Les informations partagées avec un·e juriste sont confidentielles'],
            'steps' => ['Notez les faits : dates, lieux, témoins et menaces.', 'Conservez les preuves sans les modifier et faites-en une copie sécurisée.', 'Consultez un centre de santé pour un certificat médical si nécessaire.', "Prenez conseil auprès d'un·e juriste de l'annuaire avant de déposer plainte.", 'Faites-vous accompagner lors des démarches (police, gendarmerie, tribunal).'],
            'retenir' => "La loi vous protège et des personnes ressources peuvent vous accompagner. Être informée, c'est déjà reprendre du pouvoir sur sa situation.",
        ],
        'esc' => [
            'paragraphs' => [
                "L'éducation complète à la sexualité (ECS) fournit des informations exactes, adaptées à l'âge et sans jugement sur le corps, les relations, le consentement, la contraception et la prévention des infections. Elle contribue à des choix éclairés et à des relations plus respectueuses.",
                "Au Bénin, la sexualité reste un sujet tabou dans beaucoup de familles et d'écoles, ce qui laisse la place aux rumeurs, aux mythes et aux informations erronées sur internet. Pour les filles et femmes LBTQ+, les contenus adaptés à leurs réalités sont encore plus rares, d'où l'intérêt de sources fiables comme les pairs animateur·rices et les professionnel·les de santé.",
                "Le consentement est la base de toute relation : libre, éclairé, spécifique et réversible. Il peut être retiré à tout moment, il ne se présume pas du silence ni d'une relation passée, et il ne peut être obtenu sous pression, chantage ou emprise de l'alcool.",
                "Pour protéger votre santé, utilisez des moyens de prévention adaptés (préservatifs, contraception, dépistage régulier du VIH et des IST). Les centres de santé partenaires offrent des services confidentiels, souvent gratuits, et l'équipe Xomiton peut vous orienter vers un·e professionnel·le sans stigmatisation.",
            ],
            'points' => ['Le consentement est libre, éclairé, spécifique et réversible', 'Les informations fiables protègent des grossesses non désirées et des IST', 'Le dépistage régulier est un geste de soin, pas un jugement', 'Chaque personne décide de son corps et de ses pratiques', 'Les services de santé doivent respecter votre confidentialité'],
            'steps' => ['Informez-vous auprès de sources fiables (professionnel·le de santé, pair animateur·rice).', 'Discutez avec votre ou vos partenaires de limites, de protection et de dépistage.', 'Choisissez une méthode de prévention ou de contraception adaptée avec un·e soignant·e.', 'Faites un dépistage VIH/IST régulier dans un centre confidentiel.', 'En cas de rapport non protégé ou de violence, consultez rapidement un centre de santé.'],
            'retenir' => "Votre corps vous appartient. S'informer, consentir et se protéger permet de vivre sa sexualité en sécurité et avec plaisir.",
        ],
        'corps' => [
            'paragraphs' => [
                "L'autonomisation est la capacité de prendre des décisions pour soi : sur son corps, son argent, son parcours et ses relations. Elle se construit pas à pas, avec des compétences, des ressources et surtout un entourage qui soutient plutôt qu'il ne contrôle.",
                'Au Bénin, de nombreuses jeunes femmes ont peu de marge de manœuvre financière ou sociale, ce qui accroît leur dépendance et leur exposition aux violences. Développer une activité génératrice de revenus, apprendre à budgétiser et épargner de petites sommes sont des leviers concrets de sécurité.',
                "La confiance en soi se travaille par de petites habitudes : se fixer un objectif réaliste, célébrer ses réussites, apprendre à dire non, s'entourer de personnes bienveillantes. Elle réduit la vulnérabilité à l'emprise et facilite la prise de parole en cas de problème.",
                "La sororité, c'est-à-dire la solidarité entre femmes et personnes LBTQ+, démultiplie ces efforts : cercles d'épargne, entraide, mentorat, partage d'informations fiables. Rejoindre les groupes et événements Xomiton est une manière de ne pas avancer seule.",
            ],
            'points' => ['Décider pour soi est un droit', "L'indépendance financière protège de la dépendance", 'La confiance en soi se construit par la pratique', "L'entraide entre pairs renforce la sécurité", "Demander conseil n'enlève rien à votre autonomie"],
            'steps' => ['Définissez un objectif personnel simple pour les trois prochains mois.', 'Tenez un petit budget : entrées, dépenses, épargne.', 'Mettez de côté une somme, même modeste, chaque semaine.', 'Identifiez deux personnes ou un groupe sur lesquel·les vous pouvez compter.', 'Inscrivez-vous à une formation ou à un atelier pour développer une compétence.'],
            'retenir' => "L'autonomie est un chemin, pas une destination. Chaque décision que vous prenez pour vous-même renforce votre pouvoir d'agir.",
        ],
    ],

    'specific' => [
        1 => "Une VBG est un acte nuisible commis contre la volonté d'une personne et fondé sur les rôles et rapports de pouvoir liés au genre. Elle comprend les violences conjugales, le harcèlement, les agressions sexuelles, les mariages forcés, les violences en ligne et les violences ciblant l'orientation sexuelle ou l'identité de genre.",
        2 => "Les signaux d'alerte sont souvent progressifs : isolement imposé, contrôle du téléphone, humiliations, jalousie extrême, menaces, privation d'argent, pression sexuelle. Repérer ces signes tôt permet d'agir avant l'escalade.",
        3 => "La loi n°2011-26 du 9 janvier 2012 portant prévention et répression des violences faites aux femmes définit et sanctionne notamment les violences conjugales, le harcèlement sexuel, le viol, le mariage forcé et les mutilations génitales féminines. Elle prévoit aussi des mesures de protection et d'assistance.",
        4 => "Le chantage à l'outing consiste à menacer de révéler l'orientation sexuelle ou l'identité de genre d'une personne pour obtenir de l'argent, des faveurs ou le silence. C'est une forme de violence psychologique et d'extorsion. Les « thérapies » imposées ou violences dites correctives sont des atteintes graves à l'intégrité et ne soignent rien.",
        5 => "Quand une amie vous confie une violence, l'essentiel est de croire, d'écouter sans juger et de respecter ses décisions. Évitez les questions qui culpabilisent (« pourquoi n'es-tu pas partie ? ») et proposez plutôt de l'accompagner vers les personnes ressources.",
        6 => "En cas de violence, vous avez droit à une protection, à des soins médicaux, à l'information juridique et, selon les situations, à un hébergement d'urgence. Les services de police, de gendarmerie et les structures sociales ont l'obligation de vous accueillir.",
        7 => 'Le cycle de la violence se répète en quatre temps : montée de la tension, agression, justification (excuses, culpabilisation) puis « lune de miel ». Comprendre ce schéma aide à ne pas se croire responsable et à planifier sa sortie en sécurité.',
        9 => "Serviettes jetables ou lavables, tampons, cups, culottes menstruelles : chaque option a ses avantages, ses limites et son coût. Le bon choix dépend de votre budget, de l'accès à l'eau et à l'intimité, et de votre confort.",
        14 => "La cohérence cardiaque (5 secondes d'inspiration, 5 secondes d'expiration pendant 5 minutes), la respiration carrée et l'ancrage des 5 sens sont des exercices simples à pratiquer lors d'une crise d'angoisse, sans matériel ni lieu particulier.",
        19 => "Porter plainte se prépare : rassembler les preuves, obtenir un certificat médical, être accompagnée d'une personne de confiance ou d'un·e juriste, puis déposer la plainte auprès de la police, de la gendarmerie ou du procureur. Vous pouvez demander un récépissé et des explications à chaque étape.",
        20 => "Le droit béninois ne comporte pas, à notre connaissance, de disposition pénalisant spécifiquement l'orientation sexuelle, mais les stigmatisations sociales et les abus (chantage, extorsion, violences) restent fréquents. Les protections contre les violences et atteintes à la vie privée s'appliquent à toutes et tous.",
        21 => "Le consentement se manifeste par un accord clair, enthousiaste et réversible. Le silence, la peur ou l'ivresse ne sont pas un « oui ». Chacun·e a la responsabilité de s'assurer que l'autre est d'accord à chaque étape.",
        23 => 'Le dépistage du VIH et des IST est confidentiel et, dans de nombreux centres partenaires, gratuit. Un traitement efficace existe pour le VIH et la plupart des IST : détecter tôt permet de rester en bonne santé et de protéger ses partenaires.',
    ],

    'laws' => [
        'loi' => ['Loi n°2011-26 du 9 janvier 2012', 'Portant prévention et répression des violences faites aux femmes : sanctionne les violences conjugales, le harcèlement sexuel, le viol, le mariage forcé et les mutilations génitales féminines, et prévoit des mesures de protection.'],
        'cp' => ['Code pénal béninois (loi n°2018-16 du 28 décembre 2018)', 'Qualifie et sanctionne les coups et blessures, menaces, agressions sexuelles, viol, extorsion et atteintes à la vie privée.'],
        'cpf' => ['Code des personnes et de la famille (2004)', "Garantit le consentement au mariage, l'égalité des époux et la protection de l'enfant."],
        'sante' => ['Loi n°2003-04 sur la santé sexuelle et de la reproduction', "Reconnaît le droit à l'information, aux soins et à la confidentialité en matière de santé sexuelle et reproductive."],
        'const' => ['Constitution du 11 décembre 1990', "Garantit l'égalité de toutes et tous devant la loi et la protection de la personne humaine."],
        'vih' => ['Loi n°2005-31 sur la prévention du VIH', "Interdit la discrimination des personnes vivant avec le VIH et garantit la confidentialité et l'accès aux soins."],
    ],

    'laws_by_category' => [
        'vbg' => ['loi', 'cp', 'const'],
        'droits' => ['loi', 'cp', 'cpf', 'const'],
        'regles' => ['sante', 'const'],
        'mental' => ['sante', 'cp', 'const'],
        'esc' => ['sante', 'loi', 'const'],
        'corps' => ['const', 'cpf', 'loi'],
    ],

    'laws_overrides' => [
        18 => ['cpf', 'loi', 'cp'],
        23 => ['sante', 'loi', 'const', 'vih'],
    ],

];

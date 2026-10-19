<?php

declare(strict_types=1);

/*
 * Assistant Xomiton (P3-09 App\Application\Shared\KnowledgeBaseAnswerer, P8-11 page /assistant) : réponse de crise,
 * repli bienveillant, mentions fixes, page membre et « Parler à un humain ». Tutoiement : SOS, signalement et espace
 * membre tutoient (VOICE_AND_TONE.md §3) ; ton calme et direct, sans adjectif alarmiste ni néologisme dans les écrans
 * de crise (§2, §4) ; aucune promesse de réponse humaine immédiate (§3 règle 6 : « sous 24 h »).
 */
return [

    'crisis' => [
        'intro' => 'Je te crois, et tu n’es pas seule. Si tu es en danger maintenant, mets-toi à l’abri si tu le peux et appelle tout de suite :',
        'number' => '• :label : :number',
        // Sans URL : l'offcanvas public et la page /assistant affichent les boutons ; l'échange enregistré d'un·e membre
        // ajoute l'adresse en fin de ligne.
        'sos' => 'Bouton SOS : ton alerte part avec ta position vers les personnes de confiance de l’annuaire.',
        'report' => 'Tu peux aussi signaler ce qui se passe, de façon anonyme si tu le souhaites.',
        // Échange sans « Parler à un humain » (visiteur·se) : personne ne peut recontacter, renvoi vers « Exprimer un besoin ».
        'need' => 'Pour qu’une personne formée te réponde, exprime un besoin depuis la page Signaler : elle le lira sous 24 h.',
        // Compte connecté (P8-11) : « Parler à un humain » crée une demande routée (EscalateToHuman).
        'human' => 'Une personne formée peut te répondre : demande à parler à un humain et une pair éducatrice, une psychologue ou une juriste te recontacte.',
        'flag_reason' => 'Mots de crise détectés : :keyword',
    ],

    'fallback' => [
        'intro' => 'Je n’ai pas trouvé de réponse précise à ta question.',
        'suggestions' => 'Ces questions sont peut-être proches de la tienne :',
        'suggestion' => '• :question',
        'rephrase' => 'Tu peux aussi reformuler en quelques mots (ex. « comment porter plainte ? », « contraception d’urgence », « chantage à l’outing »).',
        'disclaimer' => 'Je donne des informations générales et je ne remplace pas une professionnelle de santé, de droit ou d’écoute. En cas de danger, utilise le bouton SOS.',
    ],

    'kinds' => [
        'kb' => 'Réponse de la base de connaissances',
        'crisis' => 'Réponse de crise',
        'fallback' => 'Réponse de repli',
    ],

    'links' => [
        'sos' => 'SOS',
        'report' => 'Signaler',
        'emergency' => 'Contacts d’urgence',
        'human' => 'Parler à un humain',
        // Lien `tel:` de la réponse de crise et du texte hors connexion (numéros de settings.emergency_numbers).
        'call' => 'Appeler le :number (:label)',
        'need' => 'Exprimer un besoin',
    ],

    // Page membre /assistant (P8-11, prototype assistant.php) : Vue `Assistant/Index`.
    'page' => [
        'title' => 'Assistant Xomiton',
        'subtitle' => 'Spécialisé VBG, santé sexuelle et droits',
        'online' => 'En ligne',
        'offline' => 'Hors connexion',
        'welcome' => 'Bonjour, je suis l’assistant Xomiton. Je peux t’informer sur les violences, la santé sexuelle, tes droits et les services disponibles. Je ne remplace pas un·e professionnel·le. En cas de danger, utilise le bouton SOS.',
        'chips_label' => 'Questions fréquentes',
        'chips' => [
            'Comment porter plainte ?',
            'Que faire après une agression ?',
            'Contraception d’urgence',
            'Chantage à l’outing, que faire ?',
            'Dépistage VIH / IST',
            'Douleurs de règles',
            'Je me sens angoissée',
            'C’est quoi le consentement ?',
        ],
        'log_label' => 'Conversation avec l’assistant',
        // Préfixe lu par les lecteurs d'écran devant chaque bulle.
        'me' => 'Toi :',
        'bot' => 'Assistant :',
        'typing' => 'L’assistant écrit…',
        'input_label' => 'Ton message',
        'placeholder' => 'Pose ta question…',
        'send' => 'Envoyer',
        'options' => 'Options de la conversation',
        'clear' => 'Effacer la conversation',
        'cleared' => 'Conversation effacée.',
        'record_failed' => 'Ce message n’a pas pu être enregistré sur nos serveurs. Les numéros ci-dessus restent valables : appelle si tu es en danger.',
        'clear_failed' => 'La conversation enregistrée n’a pas pu être effacée. Réessaie dans un instant.',
        'retry' => 'Réessayer',
        'danger_title' => 'En danger ?',
        'danger_text' => 'Si tu es en danger immédiat, n’attends pas : envoie une alerte.',
        'human_title' => 'Parler à un humain',
        'human_text' => 'Une pair éducatrice, une psychologue ou une juriste peut t’écouter.',
        'human_cta' => 'Demander à être recontacté·e',
        'directory_cta' => 'Ouvrir l’annuaire',
        'disclaimer' => 'Cet assistant donne des informations générales et ne remplace pas un·e professionnel·le de santé, de droit ou de soutien psychologique.',
        'history_tab' => 'La conversation est gardée uniquement dans cet onglet.',
        'history_discreet' => 'Mode discret : rien n’est gardé dans ce navigateur, la conversation disparaît quand tu quittes la page.',
        'history_saved' => 'Conversation enregistrée et chiffrée : tu peux l’effacer à tout moment.',
        'persist' => 'Garder cette conversation',
        // Aucune promesse de relecture (VOICE_AND_TONE §3 règle 6) : la réponse humaine passe par « Parler à un humain ».
        'persist_help' => 'Chiffrée sur nos serveurs, effaçable à tout moment. Si tu sembles en danger, elle pourra être relue par l’équipe, sans réponse ici : pour qu’une personne formée te recontacte, utilise « Parler à un humain ». Désactivé par défaut.',
        'conversation' => 'conversation',
    ],

    // « Parler à un humain » (P8-11, App\Application\Assistant\EscalateToHuman) : demande « besoin » routée, lue sous 24 h.
    'escalation' => [
        'title' => 'Parler à un humain',
        'intro' => 'Une personne formée (pair éducatrice, psychologue ou juriste) lira ta demande sous 24 h et te recontactera par le moyen que tu indiques.',
        'contact' => 'Comment te joindre ?',
        'contact_help' => 'Téléphone ou e-mail. Obligatoire pour être recontacté·e.',
        'channel' => 'Moyen préféré',
        'channel_any' => 'Peu importe',
        'message' => 'Ton message',
        'message_help' => ':max caractères maximum. Tu peux simplement dire de quoi tu as besoin.',
        'share_pseudo' => 'Transmettre mon pseudonyme',
        'share_pseudo_help' => 'Sans cela, ta demande reste anonyme : seul le moyen de contact est transmis.',
        'not_shared' => 'Le contenu de cette conversation n’est pas transmis.',
        'shared' => 'Ta conversation enregistrée pourra être relue par l’équipe.',
        // Demande anonyme : la conversation (liée au compte) n'est pas jointe, sinon l'anonymat serait levé (EscalateToHuman).
        'shared_needs_pseudo' => 'Ta demande anonyme n’est pas reliée à ta conversation enregistrée. Pour que l’équipe puisse la relire avec ta demande, transmets ton pseudonyme.',
        'contact_required' => 'Indique un téléphone ou un e-mail pour être recontacté·e.',
        'too_long' => ':max caractères maximum.',
        'errors_title' => 'Ta demande n’est pas encore partie :',
        'submit' => 'Envoyer ma demande',
        'sent' => 'Demande envoyée. Une personne formée la lira sous 24 h. Code de suivi : :code',
        'offline' => 'Tu es hors connexion : ta demande ne peut pas partir. En danger ? Appelle le :number.',
        'offline_no_number' => 'Tu es hors connexion : ta demande ne peut pas partir. En danger ? Ouvre les contacts d’urgence.',
        'directory' => 'Choisir une personne dans l’annuaire',
        // Échec autre qu'une erreur de saisie (quota, serveur) : jamais sans issue sur ce chemin de crise.
        'failed_help' => 'En danger ? N’attends pas : appelle un numéro d’urgence ou choisis une personne dans l’annuaire.',
        'default_message' => 'Je souhaite parler à une personne formée (demande envoyée depuis l’assistant).',
        'flag_reason' => 'Demande à parler à un humain',
    ],
];

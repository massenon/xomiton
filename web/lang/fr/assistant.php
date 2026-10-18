<?php

declare(strict_types=1);

/*
 * Assistant Xomiton (P3-09 App\Application\Shared\KnowledgeBaseAnswerer, P8-11) : réponse de crise, repli bienveillant,
 * mentions fixes. Tutoiement : SOS, signalement et espace membre tutoient (VOICE_AND_TONE.md §3) ; ton calme et direct,
 * sans adjectif alarmiste ni néologisme dans les écrans de crise (§2, §4).
 */
return [

    'crisis' => [
        'intro' => 'Je te crois, et tu n’es pas seule. Si tu es en danger maintenant, mets-toi à l’abri si tu le peux et appelle tout de suite :',
        'number' => '• :label : :number',
        // Sans URL : l'offcanvas public affiche les boutons ; l'échange enregistré d'un·e membre ajoute l'adresse en fin de ligne.
        'sos' => 'Bouton SOS : ton alerte part avec ta position vers les personnes de confiance de l’annuaire.',
        'report' => 'Tu peux aussi signaler ce qui se passe, de façon anonyme si tu le souhaites.',
        // Échange non enregistré (visiteur·se) : personne ne peut recontacter, renvoi vers « Exprimer un besoin » (/signaler).
        'need' => 'Pour qu’une personne formée te réponde, exprime un besoin depuis la page Signaler : elle le lira sous 24 h.',
        // Échange enregistré (membre, P8-11) : la conversation est marquée pour relecture humaine.
        'human' => 'Une personne formée peut te répondre : demande à parler à un humain et une pair éducatrice, une psychologue ou une juriste te recontacte.',
        'flag_reason' => 'Mots de crise détectés : :keyword',
    ],

    'fallback' => [
        'intro' => 'Je n’ai pas trouvé de réponse précise à ta question.',
        'suggestions' => 'Ces questions sont peut-être proches de la tienne :',
        'suggestion' => '• :question',
        'rephrase' => 'Tu peux aussi reformuler en quelques mots (ex. « comment porter plainte ? », « contraception d’urgence », « chantage à l’outing »).',
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
];

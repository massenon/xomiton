<?php

declare(strict_types=1);

/*
 * Base de connaissances de l'assistant (prototype_social/data/assistant_kb.json) :
 * [question, réponse, mots-clés, active].
 * Réponses au tutoiement (P4-01, décision consignée au plan §7) : l'assistant est un dialogue d'accompagnement, comme
 * ses réponses de crise et de repli (lang/fr/assistant.php) et l'espace membre (VOICE_AND_TONE.md §3).
 */
return [
    ['Que faire après une agression sexuelle ?', 'Tu n’es pas responsable. Si possible, ne te lave pas, consulte un centre de santé dans les 72 h (PEP, contraception d’urgence) et contacte un·e juriste ou psychologue de l’annuaire.', ['agression', 'viol', 'attouchement'], true],
    ['Comment porter plainte ?', 'La loi n°2011-26 réprime les violences faites aux femmes. Tu peux te rendre au commissariat, à la gendarmerie ou auprès du procureur ; un·e juriste Xomiton peut t’accompagner.', ['plainte', 'police', 'justice', 'loi'], true],
    ['Qu’est-ce que le consentement ?', 'Un consentement libre, éclairé, enthousiaste, spécifique et réversible. Le silence, la peur ou l’alcool ne valent jamais consentement.', ['consentement'], true],
    ['Contraception d’urgence', 'À prendre le plus tôt possible, idéalement dans les 72 h. Disponible en centre de santé ou en pharmacie ; demande conseil à un·e professionnel·le.', ['contraception', 'pilule', 'grossesse'], true],
    ['Chantage à l’outing', 'Ne réponds pas, ne paie pas, conserve les captures d’écran datées et utilise le signalement anonyme. Un·e juriste peut agir pour faire retirer les contenus.', ['outing', 'chantage', 'menace en ligne'], true],
    ['Dépistage VIH / IST', 'Le dépistage est confidentiel et gratuit dans les centres partenaires. Après une prise de risque, la PEP doit démarrer dans les 72 h.', ['vih', 'ist', 'dépistage', 'sida'], true],
];

<?php

declare(strict_types=1);

/*
 * Base de connaissances de l'assistant (prototype_social/data/assistant_kb.json) :
 * [question, réponse, mots-clés, active].
 */
return [
    ['Que faire après une agression sexuelle ?', 'Vous n’êtes pas responsable. Si possible, ne vous lavez pas, consultez un centre de santé dans les 72 h (PEP, contraception d’urgence) et contactez un·e juriste ou psychologue de l’annuaire.', ['agression', 'viol', 'attouchement'], true],
    ['Comment porter plainte ?', 'La loi n°2011-26 réprime les violences faites aux femmes. Vous pouvez vous rendre au commissariat, à la gendarmerie ou auprès du procureur ; un·e juriste Xomiton peut vous accompagner.', ['plainte', 'police', 'justice', 'loi'], true],
    ['Qu’est-ce que le consentement ?', 'Un consentement libre, éclairé, enthousiaste, spécifique et réversible. Le silence, la peur ou l’alcool ne valent jamais consentement.', ['consentement'], true],
    ['Contraception d’urgence', 'À prendre le plus tôt possible, idéalement dans les 72 h. Disponible en centre de santé ou en pharmacie ; demandez conseil à un·e professionnel·le.', ['contraception', 'pilule', 'grossesse'], true],
    ['Chantage à l’outing', 'Ne répondez pas, ne payez pas, conservez les captures d’écran datées et utilisez le signalement anonyme. Un·e juriste peut agir pour faire retirer les contenus.', ['outing', 'chantage', 'menace en ligne'], true],
    ['Dépistage VIH / IST', 'Le dépistage est confidentiel et gratuit dans les centres partenaires. Après une prise de risque, la PEP doit démarrer dans les 72 h.', ['vih', 'ist', 'dépistage', 'sida'], true],
];

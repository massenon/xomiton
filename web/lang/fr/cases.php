<?php

declare(strict_types=1);

/*
 * Dossiers VBG / VDH et suivi des victimes : espace pair, collecte et API `pair/` (P9-01, P9-02 ; prototype
 * collecte.php, admin/suivi.php). Registre : tu (VOICE_AND_TONE.md §3 : espace connecté, espace pair, collecte).
 * Les libellés des natures, statuts et résultats viennent de enums/cases.php ; les messages de validation par champ
 * de validation.php (`date_not_future`, `min_len_20`, `age_range`, `commune_in_zone`, `case_code`).
 */
return [

    'errors' => [
        'client_uuid_conflict' => 'Cet envoi porte un identifiant déjà utilisé. Recommence la saisie : rien n’a été enregistré.',
        'client_uuid_reused' => 'Cet envoi reprend l’identifiant d’une saisie déjà enregistrée, avec un contenu différent. Recommence la saisie : la première est conservée, celle-ci n’a pas été enregistrée.',
        'invalid_transition' => 'Passage de « :from » à « :to » impossible pour ce dossier.',
        'locked' => 'Ce dossier est archivé ou anonymisé : il ne peut plus être modifié ni recevoir de nouvelle étape de suivi.',
    ],

    'warnings' => [
        'date_old' => 'La date des faits remonte à plus de :years ans. Vérifie-la : le dossier est bien enregistré.',
        'duplicate_suspected' => 'Un dossier que tu peux consulter ressemble beaucoup à celui-ci (:codes). Vérifie qu’il ne s’agit pas de la même personne.|Plusieurs dossiers que tu peux consulter ressemblent beaucoup à celui-ci (:codes). Vérifie qu’il ne s’agit pas de la même personne.',
    ],

];

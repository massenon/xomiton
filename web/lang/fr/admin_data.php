<?php

declare(strict_types=1);

/*
 * Administration, gestion des données (P11) : Registres `/admin/registres`, Fiches VBG / VDH `/admin/fiches`, Suivi des
 * victimes `/admin/suivi`, Annuaire dynamique `/admin/annuaire`, Règles de validation `/admin/validation` (prototype
 * admin/registres.php, fiches.php, suivi.php, annuaire.php, validation.php). Registre : vous, neutre et précis
 * (VOICE_AND_TONE.md §3, administration). Les libellés d’énumérations sont dans enums/*.php. Aucun récit, aucune note,
 * aucun commentaire ne figure dans une liste, un export ou le journal (ADR-33).
 */
return [

    'registers' => [
        'title' => 'Registres numériques',
        'intro' => 'Les dossiers VBG et VDH collectés par les pair·es. Le récit (notes) n’apparaît jamais dans la liste ni dans l’export : il se lit dans le détail d’un dossier, et chaque lecture est journalisée. Un dossier archivé quitte la liste et les indicateurs, mais reste restaurable.',
        'new' => 'Nouveau dossier',
        'count' => '{0} Aucun dossier|{1} :count dossier|[2,*] :count dossiers',
        'tabs_label' => 'Statut des dossiers',
        'tabs' => ['active' => 'Actifs'],
        'search_placeholder' => 'Code, commune, département, pair…',
        'search_help' => 'La recherche porte sur le code, la commune, le département et la pair·e, jamais sur les notes.',
        'table_caption' => 'Dossiers des registres : code, date, localisation, population, sexe et âge, violence, statut et pair·e',
        'empty' => 'Aucun dossier',
        'empty_text' => 'Aucun dossier ne correspond à ces critères. Les dossiers archivés se retrouvent dans l’onglet « Archivé ».',
        'confirm_archive' => 'Archiver le dossier :code ? Il quittera la liste et les indicateurs, mais pourra être restauré.',
        'confirm_restore' => 'Restaurer le dossier :code ? Il redeviendra « Clos » et réapparaîtra dans la liste.',

        'kpi' => [
            'active' => 'Dossiers actifs',
            'open' => 'Ouverts',
            'in_progress' => 'En cours',
            'closed' => 'Clos',
        ],

        'filters' => [
            'all_kinds' => 'Registre',
            'all_violences' => 'Toutes les violences',
            'all_populations' => 'Toutes les populations',
        ],

        'columns' => [
            'code' => 'Code',
            'kind' => 'Registre',
            'date' => 'Date',
            'zone' => 'Département',
            'commune' => 'Commune',
            'population' => 'Population',
            'sex' => 'Sexe',
            'age' => 'Tranche d’âge',
            'sex_age' => 'Sexe et âge',
            'violence' => 'Violence',
            'status' => 'Statut',
            'peer' => 'Pair·e',
            'referral' => 'Orientation',
            'actions' => 'Actions',
        ],

        'actions' => [
            'view' => 'Voir le dossier',
            'edit' => 'Modifier le dossier',
            'archive' => 'Archiver le dossier',
            'restore' => 'Restaurer le dossier',
        ],

        'fields' => [
            'kind' => 'Registre',
            'occurred_on' => 'Date des faits ou de l’accueil',
            'zone_id' => 'Département',
            'commune_id' => 'Commune',
            'population_id' => 'Population',
            'sex' => 'Sexe',
            'age_range' => 'Tranche d’âge',
            'violence_type_id' => 'Type de violence',
            'referral' => 'Orientation',
            'notes' => 'Notes',
            'status' => 'Statut',
        ],

        'form' => [
            'title_new' => 'Nouveau dossier',
            'title_edit' => 'Modifier le dossier',
            'warning' => 'Ne saisissez jamais de nom ni de coordonnées de la personne. Les notes sont pseudonymisées et chiffrées.',
            'code_hint' => 'Le code est attribué à l’enregistrement (VBG-0000 ou VDH-0000).',
            'kind_locked' => 'Le registre et le code d’un dossier ne changent jamais.',
            'no_referral' => 'Aucune',
            'notes_hint' => '20 caractères au moins, ou laissez vide. Ne décrivez pas la personne.',
            'notes_replace' => 'Remplacer les notes',
            'notes_keep' => 'Les notes existantes ne sont pas affichées ici. Laissez vide pour les conserver.',
            'notes_placeholder' => 'Ne jamais saisir de nom ni de coordonnées de la personne.',
            'clear_notes' => 'Effacer les notes existantes',
            'save' => 'Enregistrer',
        ],

        'detail' => [
            'title' => 'Dossier :code',
            'anonymized' => 'Dossier anonymisé : le récit a été effacé.',
            'change_status' => 'Changer le statut',
            'apply' => 'Appliquer',
            'followup' => 'Suivi (:count)',
            'forms' => 'Fiches (:count)',
        ],

        'toasts' => [
            'created' => 'Dossier :code enregistré.',
            'replayed' => 'Dossier :code déjà enregistré : aucun doublon créé.',
            'updated' => 'Dossier :code mis à jour.',
            'status' => 'Statut de :code : :status.',
            'archived' => 'Dossier :code archivé.',
            'restored' => 'Dossier :code restauré.',
        ],

        'errors' => [
            'not_found' => 'Ce dossier est introuvable ou hors de votre périmètre.',
            'locked' => 'Ce dossier est archivé ou anonymisé : il ne peut plus être modifié.',
            'uuid' => 'Cette saisie a déjà servi pour un autre dossier. Rouvrez le formulaire pour recommencer.',
            'status' => 'Choisissez « Ouvert », « En cours » ou « Clos » : l’archivage a son propre bouton.',
            'transition' => 'Le passage de « :from » à « :to » n’est pas possible.',
            'restore' => 'Ce dossier ne peut pas être restauré (il n’est pas archivé, ou il est anonymisé).',
        ],
    ],

    'followups' => [
        'title' => 'Suivi des victimes',
        'intro' => 'Les étapes d’accompagnement de chaque dossier : accueil, écoute, orientations, suivis à 30 et 90 jours. Les commentaires (chiffrés) ne se lisent que dans la frise d’un dossier, et chaque ouverture est journalisée. Un dossier archivé n’apparaît plus ici.',
        'case_title' => 'Frise d’un dossier',
        'case_label' => 'Code du dossier',
        'show' => 'Afficher',
        'hide' => 'Masquer',
        'form_link' => 'Fiche de référence',
        'received' => 'faits du :date',
        'timeline_title' => 'Frise de suivi du dossier :code',
        'timeline_label' => 'Étapes de suivi du dossier :code',
        'timeline_empty' => 'Aucune étape enregistrée pour ce dossier.',
        'mark_done' => 'Marquer réalisé',
        'count' => '{0} Aucune étape|{1} :count étape|[2,*] :count étapes',
        'search_placeholder' => 'Code, étape, auteur·e…',
        'search_help' => 'La recherche porte sur le code du dossier, l’étape et l’auteur·e, jamais sur les commentaires.',
        'table_caption' => 'Étapes de suivi : dossier, étape, date, auteur·e, résultat et prochaine échéance',
        'empty' => 'Aucune étape de suivi',
        'empty_text' => 'Aucune étape ne correspond à ces critères. Ajoutez une étape depuis le formulaire.',
        'add_title' => 'Ajouter une étape de suivi',
        'add' => 'Enregistrer l’étape',
        'comment_hint' => 'Facultatif, 1 000 caractères au plus. Ne saisissez ni nom ni coordonnées.',
        'late_title' => 'En retard (:count)',
        'no_late' => 'Aucun retard.',
        'soon_title' => 'Prochains suivis',
        'no_soon' => 'Aucun suivi planifié. Indiquez une prochaine date lors d’une étape.',
        'on_date' => 'le :date',

        'kpi' => [
            'done' => 'Suivis réalisés',
            'done_sub' => ':done sur :total étapes',
            'overdue' => 'Suivis en retard',
            'overdue_sub' => 'Prochaine date dépassée',
            'gap' => 'Délai moyen entre suivis',
            'gap_sub' => 'Premier suivi après :days j en moyenne',
            'days' => ':count j',
            'cases' => 'Survivant·es suivi·es',
            'upcoming' => '{0} Aucun suivi à venir|{1} :count suivi à venir|[2,*] :count suivis à venir',
        ],

        'filters' => [
            'all_outcomes' => 'Tous les résultats',
            'late_only' => 'En retard seulement',
        ],

        'columns' => [
            'case' => 'Dossier',
            'step' => 'Étape',
            'date' => 'Date',
            'by' => 'Par',
            'outcome' => 'Résultat',
            'next' => 'Prochaine échéance',
        ],

        'fields' => [
            'case_code' => 'Code du dossier',
            'step' => 'Étape',
            'date' => 'Date de l’étape',
            'outcome' => 'Résultat',
            'next_on' => 'Prochaine date de suivi',
            'comment' => 'Commentaire',
        ],

        'toasts' => [
            'added' => 'Étape de suivi enregistrée pour :code.',
            'replayed' => 'Étape déjà enregistrée pour :code : aucun doublon créé.',
            'done' => 'Étape marquée comme réalisée.',
        ],

        'errors' => [
            'case_unknown' => 'Ce code ne correspond à aucun dossier actif de votre périmètre.',
            'locked' => 'Ce dossier est archivé ou anonymisé : il ne reçoit plus d’étape.',
            'not_found' => 'Cette étape est introuvable.',
            'uuid' => 'Cette saisie a déjà servi pour une autre étape. Rechargez la page pour recommencer.',
        ],
    ],

    'forms' => [
        'title' => 'Fiches VBG / VDH',
        'intro' => 'Les fiches de référence de chaque dossier : identification pseudonymisée, faits, services reçus, orientation vers l’annuaire et consentement de la personne. Le récit, l’auteur·e présumé·e et le signataire sont chiffrés : ils ne se lisent que dans une fiche ouverte, et chaque lecture est journalisée.',
        'new' => 'Nouvelle fiche',
        'count' => '{0} Aucune fiche|{1} :count fiche|[2,*] :count fiches',
        'search_placeholder' => 'Référence, dossier, orientation…',
        'search_help' => 'La recherche porte sur la référence, le code du dossier, l’orientation et l’auteur·e de la fiche, jamais sur le récit.',
        'table_caption' => 'Fiches de référence : référence, dossier, date, orientation et consentement',
        'empty' => 'Aucune fiche de référence',
        'empty_text' => 'Aucune fiche ne correspond à ces critères. Une fiche se rattache à un dossier existant.',
        'no_referral' => 'Sans orientation',
        'consent_given' => 'Consentement donné',
        'consent_shared' => 'Consentement et partage',
        'consent_missing' => 'Sans consentement',
        'confirm_delete' => 'Supprimer la fiche :reference ? Son contenu sera effacé définitivement ; le dossier reste. La suppression est journalisée.',

        'filters' => [
            'case' => 'Dossier',
            'all_kinds' => 'Registre',
        ],

        'columns' => [
            'reference' => 'Référence',
            'case' => 'Dossier',
            'kind' => 'Registre',
            'created' => 'Établie le',
            'referral' => 'Orientation',
            'consent' => 'Consentement',
            'actions' => 'Actions',
        ],

        'actions' => [
            'view' => 'Ouvrir la fiche',
            'pdf' => 'Imprimer en PDF (A4)',
            'delete' => 'Supprimer la fiche',
        ],

        'sections' => [
            'identification' => '1. Identification pseudonymisée',
            'facts' => '2. Faits',
            'services' => '3. Services reçus',
            'referral' => '4. Orientation vers l’annuaire',
            'consent' => '5. Consentement',
        ],

        'fields' => [
            'case_code' => 'Dossier (code)',
            'pseudo' => 'Pseudonyme',
            'facts_date' => 'Date des faits',
            'facts_place' => 'Lieu',
            'facts_author' => 'Auteur·e présumé·e (lien)',
            'facts_description' => 'Description des faits',
            'services' => 'Services reçus',
            'services_note' => 'Précisions sur les services',
            'referral_contact_id' => 'Personne ou structure',
            'referral_date' => 'Date d’orientation',
            'referral_reason' => 'Motif de l’orientation',
            'referral_urgency' => 'Urgence',
            'consent_given' => 'Consentement',
            'consent_mode' => 'Mode de consentement',
            'consent_sharing' => 'Partage',
            'consent_signatory' => 'Initiales ou signature',
        ],

        'urgencies' => [
            'normale' => 'Normale',
            'haute' => 'Haute',
            'critique' => 'Critique',
        ],

        'modes' => [
            'oral' => 'oral',
            'ecrit' => 'écrit',
        ],

        'form' => [
            'title' => 'Nouvelle fiche de référence',
            'warning' => 'La fiche est rattachée à un dossier existant : l’identification est reprise du dossier. Ne saisissez jamais de nom ni de coordonnées de la personne.',
            'case_hint' => 'Saisissez le code d’un dossier : sa description pseudonymisée s’affiche ici.',
            'pseudo_placeholder' => 'Prénom d’emprunt',
            'author_placeholder' => 'Par exemple : membre de la famille',
            'description_hint' => '20 caractères au moins. Décrivez les faits sans identifier la personne.',
            'services_note_placeholder' => 'Précisions sur les services (facultatif)',
            'no_contact' => 'Aucune orientation',
            'consent_given' => 'La personne a donné son consentement éclairé à l’enregistrement de cette fiche.',
            'consent_sharing' => 'Elle autorise le partage des informations avec la personne référente.',
            'signatory_placeholder' => 'Initiales ou signature',
            'save' => 'Enregistrer la fiche',
        ],

        'detail' => [
            'title' => 'Fiche :reference',
        ],

        'pdf' => [
            'subtitle' => 'Espace sûr d’apprentissage et d’appui holistique',
            'title' => 'Fiche de référence :kind',
            'case' => 'dossier :code',
            'established' => 'établie le :date par :author',
            'confidential' => 'Confidentiel',
            'footer' => 'Usage strictement professionnel, ne pas diffuser.',
            'sex_age' => 'Sexe et tranche d’âge',
            'population' => 'Population',
            'location' => 'Localisation',
            'date_place' => 'Date et lieu',
            'type' => 'Type de violence',
            'services' => 'Services',
            'none' => 'Aucun',
            'to' => 'Orientée vers',
            'date_urgency' => 'Date et urgence',
            'consent' => 'Consentement',
            'consent_given' => 'Consentement :mode recueilli',
            'consent_missing' => 'Consentement non recueilli',
            'sharing' => 'Partage d’informations',
            'allowed' => 'autorisé',
            'not_allowed' => 'non autorisé',
        ],

        'toasts' => [
            'created' => 'Fiche :reference enregistrée.',
            'deleted' => 'Fiche :reference supprimée.',
        ],

        'errors' => [
            'case_unknown' => 'Ce code ne correspond à aucun dossier actif de votre périmètre.',
            'locked' => 'Ce dossier est archivé ou anonymisé : il ne reçoit plus de fiche.',
            'not_found' => 'Cette fiche est introuvable ou hors de votre périmètre.',
        ],
    ],

    'directory' => [
        'title' => 'Annuaire dynamique',
        'intro' => 'Les personnes et les structures que le routage des alertes cible : point focal, juristes, psychologues, gynécologues, responsables de réseau. Seules les fiches actives sont ciblées. Un changement prend effet à la prochaine alerte.',
        'new' => 'Nouveau contact',
        'count' => '{0} Aucun contact|{1} :count contact|[2,*] :count contacts',
        'search_placeholder' => 'Nom, organisation, e-mail…',
        'search_help' => 'La recherche porte sur le nom, l’organisation et l’e-mail, jamais sur le téléphone.',
        'table_caption' => 'Contacts de l’annuaire : contact, rôle, zone, coordonnées, statut actif',
        'empty' => 'Aucun contact',
        'empty_text' => 'Aucun contact ne correspond à ces critères. Ajoutez une fiche ou importez un fichier CSV.',
        'national' => 'National',
        'yes' => 'oui',
        'no' => 'non',
        'export' => 'Exporter CSV (:count)',
        'toggle' => 'Activer ou désactiver :name',
        'confirm_delete' => 'Supprimer le contact « :name » ? Il ne sera plus ciblé par le routage ; les alertes déjà traitées gardent leur historique.',

        'kpi' => [
            'contacts' => 'Contacts',
            'active' => 'Actifs',
            'focal' => 'Points focaux',
            'trio' => 'Juristes · Psy · Gynéco',
        ],

        'filters' => [
            'all_roles' => 'Tous les rôles',
            'active' => 'Actifs',
            'inactive' => 'Inactifs',
        ],

        'columns' => [
            'contact' => 'Contact',
            'role' => 'Rôle',
            'zone' => 'Zone',
            'details' => 'Coordonnées',
            'active' => 'Actif',
            'actions' => 'Actions',
        ],

        'actions' => [
            'edit' => 'Modifier le contact',
            'delete' => 'Supprimer le contact',
        ],

        'flags' => [
            'national_focal' => 'Point focal national',
            'emergency' => 'Urgence',
            'private' => 'Non public',
            'account' => 'Compte : :pseudo',
        ],

        'fields' => [
            'name' => 'Nom',
            'role' => 'Rôle',
            'org' => 'Organisation',
            'availability' => 'Disponibilité',
            'phone' => 'Téléphone',
            'email' => 'E-mail',
            'whatsapp' => 'WhatsApp',
            'zone_id' => 'Département',
            'commune_id' => 'Commune',
            'user_ref' => 'Compte lié',
            'is_active' => 'Actif',
            'is_public' => 'Public',
            'is_emergency' => 'Urgence',
            'is_national_focal' => 'Point focal national',
            'user_id' => 'Compte lié',
        ],

        'form' => [
            'title_new' => 'Nouveau contact',
            'title_edit' => 'Modifier le contact',
            'availability_placeholder' => '24 h sur 24, 9 h à 17 h…',
            'account_hint' => 'Facultatif : pseudo ou e-mail d’un compte. La personne voit alors les alertes routées vers elle.',
            'is_active' => 'Contact actif (ciblable par le routage des alertes)',
            'is_public' => 'Public (visible des membres dans l’annuaire)',
            'is_emergency' => 'Contact d’urgence (servi hors connexion sur la page des urgences)',
            'is_national_focal' => 'Point focal national (destinataire de l’escalade, rôle « Point focal » uniquement)',
            'save' => 'Enregistrer',
        ],

        'toasts' => [
            'created' => 'Contact « :name » ajouté.',
            'updated' => 'Contact « :name » mis à jour.',
            'activated' => 'Contact « :name » activé.',
            'deactivated' => 'Contact « :name » désactivé.',
            'deleted' => 'Contact « :name » supprimé.',
            'no_national_focal' => 'Aucun point focal national actif : l’escalade passera par les points focaux de zone, puis par le premier contact actif.',
        ],

        'errors' => [
            'not_found' => 'Ce contact est introuvable.',
            'unreachable' => 'Indiquez au moins un moyen de joindre la personne : téléphone, WhatsApp, e-mail ou compte lié.',
            'national_focal' => 'Seul un contact de rôle « Point focal » peut être point focal national.',
            'account_unknown' => 'Aucun compte ne porte ce pseudo ou cet e-mail.',
        ],

        'import' => [
            'button' => 'Importer CSV',
            'title' => 'Importer un fichier CSV',
            'help' => 'Une ligne par contact, séparateur « ; », colonnes du modèle (Nom et Rôle obligatoires). Les lignes qui échouent aux règles de validation sont rejetées et listées ; un contact déjà présent (même e-mail, sinon même nom et même rôle) est mis à jour.',
            'template' => 'Télécharger le modèle',
            'file' => 'Fichier CSV',
            'uploading' => 'Envoi du fichier…',
            'submit' => 'Importer',
            'done' => 'Import terminé : :accepted contact(s) enregistré(s), :rejected ligne(s) rejetée(s).',
            'report' => ':created créé(s), :updated mis à jour, :rejected rejeté(s).',
            'rejected_caption' => 'Lignes rejetées de l’import',
            'line' => 'Ligne',
            'reason' => 'Motif',
            'errors' => [
                'too_big' => 'Ce fichier dépasse 1 Mo : découpez-le en plusieurs fichiers.',
                'too_long' => 'Ce fichier dépasse :max lignes : découpez-le en plusieurs fichiers.',
                'unreadable' => 'Ce fichier ne peut pas être lu.',
                'columns' => 'Colonnes attendues : au moins « Nom » et « Rôle » dans la première ligne.',
                'role' => 'Rôle inconnu : « :role ».',
                'zone' => 'Département inconnu : « :zone ».',
                'commune' => 'Commune inconnue dans :zone : « :commune ».',
                'commune_without_zone' => 'Indiquez le département de la commune.',
            ],
        ],

        'csv' => [
            'name' => 'Nom',
            'role' => 'Rôle',
            'org' => 'Organisation',
            'phone' => 'Téléphone',
            'email' => 'E-mail',
            'whatsapp' => 'WhatsApp',
            'zone' => 'Département',
            'commune' => 'Commune',
            'availability' => 'Disponibilité',
            'active' => 'Actif',
            'public' => 'Public',
            'national_focal' => 'Point focal national',
            'emergency' => 'Urgence',
        ],

        'csv_example' => ['Exemple NOM Prénom', 'Juriste', 'Barreau', '+229 01 97 00 00 00', 'exemple@mail.bj', '+229 01 97 00 00 00', 'Littoral', 'Cotonou', '9 h à 17 h', 'oui', 'oui', 'non', 'non'],

        'routing' => [
            'title' => 'Aperçu du routage des alertes',
            'rule' => 'Règle appliquée à chaque alerte : 1. le rôle correspondant au besoin exprimé (juriste, psychologue, gynécologue) dans le département de la personne ; 2. à défaut, le point focal du département ; 3. le point focal national ; 4. le premier contact actif. Seuls les contacts actifs sont ciblés.',
            'need' => 'Type de besoin',
            'zone' => 'Département de l’alerte',
            'sos' => 'SOS ou signalement sans besoin',
            'fallback' => 'repli',
            'none' => 'Aucun contact actif : l’alerte resterait non routée.',
            'levels' => 'Les quatre niveaux',
            'skipped' => 'sauté (aucun département)',
            'nobody' => 'personne',
            'retained' => 'Retenu',
            'matrix' => 'Département et besoin',
            'matrix_caption' => 'Contact retenu pour chaque département et chaque besoin',
        ],
    ],

    'validation' => [
        'title' => 'Règles de validation',
        'intro' => 'Les contrôles de saisie appliqués par les formulaires et par le serveur. Activez ou désactivez une règle : le changement vaut pour la collecte, l’application mobile et les imports à la requête suivante. Les règles verrouillées protègent les personnes et les comptes : elles restent toujours actives.',
        'catalog_title' => 'Catalogue des règles de saisie',
        'table_caption' => 'Règles de validation : interrupteur, libellé et exemples, formulaires concernés',
        'save' => 'Enregistrer',
        'save_short' => 'Enregistrer',
        'locked' => 'Verrouillée',
        'toggle' => 'Activer ou désactiver la règle « :rule »',
        'try' => 'Tester',
        'valid_example' => 'Exemple valide',
        'invalid_example' => 'Exemple invalide',
        'catalog_note' => 'L’état des règles est enregistré côté serveur et lu par chaque formulaire à chaque saisie. Une règle absente du catalogue reste active.',

        'kpi' => [
            'rules' => 'Règles actives',
            'completeness' => 'Complétude des dossiers',
            'cases' => '{0} Aucun dossier|{1} Sur :count dossier|[2,*] Sur :count dossiers',
            'duplicates' => 'Doublons suspects',
            'duplicate_cases' => '{0} Aucun dossier concerné|{1} :count dossier concerné|[2,*] :count dossiers concernés',
            'score' => 'Score qualité',
        ],

        'columns' => [
            'active' => 'Active',
            'rule' => 'Règle',
            'forms' => 'S’applique à',
            'actions' => 'Actions',
        ],

        'sandbox' => [
            'title' => 'Bac à sable de validation',
            'rule' => 'Règle',
            'value' => 'Valeur à tester',
            'placeholder' => 'Tapez une valeur…',
            'attribute' => 'valeur',
            'valid' => 'Valeur acceptée.',
            'disabled' => 'Règle désactivée : la valeur serait acceptée sans contrôle.',
            'empty' => 'Valeur vide : acceptée (champ facultatif).',
            'hint' => 'Saisissez une valeur pour voir le résultat.',
            'not_testable' => 'La valeur est essayée avec le code même des formulaires, sans rien enregistrer. « Commune cohérente avec le département » (deux champs) et « Mot de passe fort » (un mot de passe ne se saisit pas ici) ne se testent pas.',
        ],

        'duplicates' => [
            'title' => 'Détection des doublons',
            'enabled' => 'Alerter lors d’une saisie suspecte',
            'legend' => 'Deux dossiers sont suspects s’ils ont en commun :',
            'min_fields' => 'Sélectionnez au moins deux champs pour détecter un doublon.',
            'fields' => [
                'date' => 'Date',
                'commune' => 'Commune',
                'population' => 'Population',
                'age' => 'Tranche d’âge',
                'type' => 'Type de violence',
                'sexe' => 'Sexe',
            ],
        ],

        'plausibility' => [
            'title' => 'Règles de plausibilité',
            'enabled' => 'Avertir d’une date ancienne',
            'effect' => 'Effet des règles de plausibilité sur les dossiers',
            'age' => 'Tranche d’âge',
            'age_value' => 'de :min à :max ans',
            'future' => 'Date postérieure à aujourd’hui',
            'future_value' => '{0} Refusée (aucune trouvée)|{1} Refusée (:count trouvée)|[2,*] Refusée (:count trouvées)',
            'old' => 'Date de plus de :years ans',
            'old_value' => '{0} Avertissement (aucune)|{1} Avertissement (:count)|[2,*] Avertissement (:count)',
            'gps' => 'Coordonnées GPS',
            'gps_value' => 'Bénin : lat :lat0 à :lat1, lng :lng0 à :lng1',
            'no_range' => 'Aucune tranche d’âge ne tient dans ces bornes : élargissez-les.',
            'fields' => [
                'age_min' => 'Âge minimal',
                'age_max' => 'Âge maximal',
                'max_past_years' => 'Ancienneté maximale d’une date (ans)',
                'is_active' => 'Règle active',
            ],
        ],

        'report' => [
            'title' => '{0} Rapport de qualité des données|{1} Rapport de qualité des données (:count dossier)|[2,*] Rapport de qualité des données (:count dossiers)',
            'print' => 'Imprimer',
            'empty' => 'Aucun dossier à contrôler',
            'empty_text' => 'Le rapport apparaît dès qu’un dossier actif est enregistré.',
            'missing' => 'Champs manquants',
            'duplicates' => 'Doublons suspects',
            'duplicates_caption' => 'Groupes de dossiers suspects d’être des doublons',
            'no_duplicates' => 'Aucun doublon suspect.',
            'cases' => 'Dossiers',
            'shared' => 'Valeurs communes',
            'more' => '(+ :count)',
            'more_groups' => 'Et :count autre(s) groupe(s) non détaillé(s).',
            'fields' => [
                'zone' => 'Département',
                'commune' => 'Commune',
                'population' => 'Population',
                'sex' => 'Sexe',
                'age' => 'Tranche d’âge',
                'violence' => 'Type de violence',
                'referral' => 'Orientation',
                'notes' => 'Notes',
            ],
        ],

        'toasts' => [
            'rules_saved' => 'Règles enregistrées (:active actives sur :total).',
            'locked' => 'La règle « :rule » est verrouillée : elle reste toujours active.',
            'plausibility_saved' => 'Règles de plausibilité enregistrées.',
            'duplicates_saved' => 'Règle de détection des doublons enregistrée.',
        ],
    ],

];

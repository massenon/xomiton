<?php

declare(strict_types=1);

/*
 * Libellés des enums du domaine « fil » (lot 4) : app/Enums/PostTag, PostVisibility, PostStatus,
 * InvitationStatus, GroupPrivacy, GroupRole, MembershipStatus.
 */
return [

    'post_tag' => [
        'temoignage' => 'Témoignage',
        'question' => 'Question',
        'ressource' => 'Ressource',
        'evenement' => 'Événement',
        'violences' => 'Violences',
        'sondage' => 'Sondage',
    ],

    'post_visibility' => [
        'public' => 'Public',
        'followers' => 'Abonné·es',
        'me' => 'Moi uniquement',
    ],

    'post_status' => [
        'publie' => 'Publié',
        'masque' => 'Masqué',
        'supprime' => 'Supprimé',
    ],

    // App\Enums\ContentReportReason (P7-02) : motifs d'un signalement de contenu, gardés tels quels en base (reason).
    'report_reason' => [
        'harcelement' => 'Harcèlement ou insultes',
        'discrimination' => 'Contenu discriminatoire (homophobie, transphobie)',
        'menaces' => 'Menaces ou chantage',
        'donnees_personnelles' => 'Partage d’informations privées',
        'desinformation' => 'Désinformation santé',
        'usurpation' => 'Usurpation d’identité',
        'spam' => 'Spam',
        'autre' => 'Autre',
        'filtre_automatique' => 'Mots surveillés (filtre automatique)',
    ],

    'invitation_status' => [
        'en_attente' => 'En attente',
        'acceptee' => 'Acceptée',
        'refusee' => 'Refusée',
    ],

    'group_privacy' => [
        'public' => 'Public',
        'prive' => 'Privé',
    ],

    'group_role' => [
        'membre' => 'Membre',
        'moderateur' => 'Modérateur·rice',
        'admin' => 'Administrateur·rice',
    ],

    'membership_status' => [
        'demande' => 'Demande en attente',
        'actif' => 'Actif',
        'banni' => 'Banni',
    ],

];

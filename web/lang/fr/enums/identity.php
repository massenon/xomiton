<?php

declare(strict_types=1);

/*
 * Libellés des enums du domaine « identité et accès » (lot 2) : app/Enums/UserStatus, MemberType,
 * ProfileVisibility, OtpPurpose, ConsentKind.
 */
return [

    'user_status' => [
        'en_attente' => 'En attente de validation',
        'actif' => 'Actif',
        'suspendu' => 'Suspendu',
    ],

    'member_type' => [
        'membre' => 'Membre',
        'pair' => 'Pair éducateur·rice',
        'allie' => 'Allié·e',
        'ressource' => 'Personne ressource',
    ],

    // App\Enums\AllyStatus (Q10 c, P6-06) : profil progressif, aucun droit associé.
    'ally_status' => [
        'allie' => 'Allié·e',
        'ressource' => 'Personne ressource',
    ],

    'profile_visibility' => [
        'public' => 'Tout le monde',
        'community' => 'Toute la communauté',
        'followers' => 'Mes abonné·es seulement',
        'private' => 'Privé',
    ],

    'otp_purpose' => [
        'login' => 'Connexion',
        'signup' => 'Inscription',
        'reset' => 'Réinitialisation du mot de passe',
    ],

    'consent_kind' => [
        'cgu' => 'Conditions générales d’utilisation',
        'privacy' => 'Politique de confidentialité',
        'sensitive_data' => 'Données sensibles',
        'newsletter' => 'Lettre d’information',
    ],

];

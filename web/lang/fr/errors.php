<?php

declare(strict_types=1);

/*
 * Pages d'erreur HTTP (P3-05, P4-11 : pages 404 et 503 sobres ; prototype error-404.php).
 * Registre (VOICE_AND_TONE.md §3, Q36) : les pages d'erreur sont rendues par la coquille publique → vous ;
 * la 419 existe en deux clés (public = vous, member = tu) ; 422 et 429 sont écrites sans registre.
 * Chaque erreur : title (h1), text (ce qui s'est passé + comment corriger), action (bouton principal).
 */
return [

    'page_title' => 'Erreur :code',
    'code' => 'Erreur :code',
    'help' => 'Besoin d’aide ? Consultez le centre d’aide ou écrivez à l’équipe Xomiton.',

    '403' => [
        'title' => 'Accès refusé',
        'text' => 'Vous n’avez pas les droits nécessaires pour consulter cette page. Si vous pensez qu’il s’agit d’une erreur, contactez l’équipe Xomiton.',
        'action' => 'Retour à l’accueil',
    ],

    '404' => [
        'title' => 'Oups, cette page est introuvable',
        'text' => 'La page que vous cherchez a peut-être été déplacée ou n’existe plus. Vous n’avez rien fait de mal : retrouvons ensemble le bon chemin.',
        'action' => 'Retour à l’accueil',
        'secondary' => 'Centre d’aide',
    ],

    '419' => [
        'public' => [
            'title' => 'Session expirée',
            'text' => 'Votre session a expiré, veuillez réessayer. Par sécurité, les informations saisies n’ont pas été envoyées.',
            'action' => 'Réessayer',
        ],
        'member' => [
            'title' => 'Session expirée',
            'text' => 'Ta session a expiré. Reconnecte-toi puis réessaie : rien n’a été envoyé.',
            'action' => 'Se reconnecter',
        ],
    ],

    '422' => [
        'title' => 'Informations à corriger',
        'text' => 'Certaines informations sont incomplètes ou incorrectes. Corriger les champs signalés, puis renvoyer le formulaire.',
        'action' => 'Retour au formulaire',
    ],

    '429' => [
        'title' => 'Trop de tentatives',
        'text' => 'Pour protéger la plateforme, le nombre d’actions par minute est limité. Nouvelle tentative possible dans :seconds secondes.',
        'text_no_delay' => 'Pour protéger la plateforme, le nombre d’actions par minute est limité. Patienter un instant avant de recommencer.',
        'action' => 'Réessayer',
    ],

    '500' => [
        'title' => 'Un problème est survenu',
        'text' => 'Une erreur s’est produite de notre côté. L’équipe est prévenue ; réessayez dans quelques instants.',
        'action' => 'Retour à l’accueil',
    ],

    '503' => [
        'title' => 'Maintenance en cours',
        'text' => 'Xomiton revient dans quelques minutes. Merci de votre patience.',
        'action' => 'Actualiser la page',
    ],

    // Rappel de crise affiché sur toute page d'erreur publique (error-404.php du prototype).
    'emergency' => [
        'title' => 'En danger ?',
        'text' => 'Utilisez le bouton SOS ou appelez le 117.',
        'sos' => 'SOS',
        'contacts' => 'Contacts d’urgence',
    ],

    // Réponses JSON de l'API /api/v1 ({message, code, errors} ; P5-09). Sans registre : lues par Vue et Flutter.
    'api' => [
        'unauthenticated' => 'Connexion requise.',
        'forbidden' => 'Action non autorisée.',
        'not_found' => 'Ressource introuvable.',
        'conflict' => 'Conflit : une version plus récente existe déjà.',
        'validation' => 'Les données envoyées sont invalides.',
        'throttled' => 'Trop de requêtes. Nouvelle tentative possible dans :seconds secondes.',
        'server' => 'Erreur interne. Réessayer plus tard.',
        'maintenance' => 'Plateforme en maintenance. Réessayer dans quelques minutes.',
        'bad_request' => 'Requête mal formée.',
        'gone' => 'Cette ressource n’est plus disponible.',
        'payload_too_large' => 'Contenu trop volumineux.',
        'unsupported_media_type' => 'Format de contenu non pris en charge.',
        'http' => 'La requête n’a pas pu aboutir.',
        // Middleware `idempotent` (ADR-32, docs/API.md §1.3).
        'idempotency' => [
            'idempotency_key_required' => 'L’en-tête Idempotency-Key est obligatoire pour cet envoi.',
            'idempotency_key_invalid' => 'L’en-tête Idempotency-Key doit être un UUID.',
            'idempotency_key_reused' => 'Cette clé Idempotency-Key a déjà servi pour un autre contenu. Utiliser une nouvelle clé pour un nouvel envoi.',
            'idempotency_in_progress' => 'L’envoi d’origine est encore en cours de traitement. Nouvelle tentative possible dans :seconds secondes.',
        ],
    ],

];

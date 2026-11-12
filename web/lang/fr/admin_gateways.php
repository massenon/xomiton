<?php

declare(strict_types=1);

/*
 * Administration, Passerelles réseaux `/admin/passerelles` (P12-08, D4-bis, ADR-15 ; prototype admin/passerelles.php).
 * Registre : vous, neutre et précis (VOICE_AND_TONE.md §3, administration). Les libellés des réseaux, types et statuts sont
 * dans enums/gateways.php. Aucun jeton n’est jamais affiché ni écrit au journal.
 */
return [

    'title' => 'Passerelles réseaux',
    'notice' => 'Aucun connecteur automatique n’est encore branché : les publications sont préparées ici, puis publiées à la main sur le compte de l’organisation et marquées « publiées ». Aucune donnée de membre n’est envoyée aux réseaux.',
    'notice_live' => 'Facebook et Telegram publient automatiquement à l’heure prévue, avec le jeton du compte de l’organisation. Les autres réseaux restent à publier à la main : copiez le texte, joignez les images, puis marquez la publication « publiée ». Aucune donnée de membre n’est envoyée aux réseaux.',
    'last_error' => 'Le réseau a refusé la dernière publication : vérifiez le jeton et les droits du compte.',
    'cards_title' => 'Comptes de l’organisation',
    'not_connected' => 'Non connecté',
    'api' => 'API prévue',
    'manual' => 'Publication à la main',
    'token_expired' => 'Jeton expiré',
    'disconnect' => 'Déconnecter',

    'connect' => [
        'open' => 'Connecter',
        'title' => 'Connecter :network',
        'help' => 'Indiquez le compte de l’organisation, jamais un compte personnel.',
        'account' => 'Identifiant du compte',
        'account_help' => '3 à 40 caractères, avec ou sans @.',
        'token' => 'Jeton d’accès',
        'token_help' => 'Jeton de page Facebook (longue durée) ou jeton du bot Telegram. Enregistré chiffré, il ne s’affiche plus ensuite ; laissez vide pour garder le jeton actuel.',
        'external_id' => 'Identifiant de la page ou du canal',
        'external_id_help' => 'Page Facebook : son identifiant numérique. Canal Telegram : -100… ou @nomducanal. Sans identifiant, l’identifiant du compte est utilisé.',
        'submit' => 'Connecter le compte',
    ],

    'confirm' => [
        'disconnect' => 'Déconnecter :network ? Le compte et son jeton sont retirés de la plateforme.',
        'mark' => 'Confirmez-vous avoir publié ce message sur :network ?',
        'cancel' => 'Annuler cette publication planifiée ?',
        'remove' => 'Retirer cette publication de la file ?',
        'delete_template' => 'Supprimer ce modèle de message ?',
    ],

    'schedule' => [
        'title' => 'Planifier une publication',
        'submit' => 'Planifier',
        'connected_only' => '(réseaux connectés seulement)',
        'message_help' => '500 caractères au plus. Obligatoire pour un message personnalisé ; sinon, le texte de partage de la BD (ou son titre) ou le texte du modèle est repris.',
        'republish_help' => 'Une BD n’est jamais diffusée deux fois sur le même réseau. Cochez pour la republier volontairement.',
        'at_help' => 'À l’heure du Bénin, dans le futur.',
        'fields' => [
            'kind' => 'Contenu',
            'reference' => 'Contenu',
            'reference_bd' => 'Bande dessinée SSR',
            'reference_template' => 'Message clé',
            'message' => 'Message d’accompagnement',
            'channels' => 'Réseaux ciblés',
            'at' => 'Date et heure',
            'republish' => 'Republier',
        ],
    ],

    'preview' => [
        'title' => 'Aperçu pour :network',
        'link' => 'Lien :',
        'no_link' => 'BD réservée aux membres : aucun lien public ni image ne sont envoyés.',
        'media' => '{1} :count image jointe (texte alternatif quand le réseau le permet) :|[2,*] :count images jointes, dans cet ordre (texte alternatif quand le réseau le permet) :',
        'caption_cut' => 'Sur Telegram, la légende est limitée à 1 024 caractères : le texte sera raccourci, le lien conservé.',
    ],

    'series' => [
        'title' => 'Calendrier BD',
        'help' => 'Planifiez en une fois toutes les BD publiques pas encore diffusées sur les réseaux choisis : une BD tous les N jours, à la même heure. Chaque BD ne vise que les réseaux où elle manque ; chaque publication reste annulable tant qu’elle n’est pas partie.',
        'preview' => 'Prévisualiser le calendrier',
        'submit' => 'Planifier ce calendrier',
        'plan_title' => '{1} :count BD à planifier|[2,*] :count BD à planifier',
        'fields' => [
            'channels' => 'Réseaux',
            'date' => 'À partir du',
            'time' => 'Heure',
            'every' => 'Une BD tous les (jours)',
        ],
    ],

    'queue' => [
        'title' => 'File de publication (:count)',
        'empty' => 'Aucune publication planifiée.',
        'custom' => 'Message personnalisé',
        'copy' => 'Copier le texte',
        'copied' => 'Texte copié',
        'mark' => 'Marquer publié',
        'cancel' => 'Annuler',
        'remove' => 'Retirer',
        'view' => 'Voir la publication',
    ],

    'delivery_errors' => [
        'unauthorized' => 'Jeton refusé ou droits insuffisants : reconnectez le compte',
        'rate_limited' => 'Limite de débit du réseau atteinte : nouvel essai automatique',
        'rejected' => 'Publication refusée par le réseau',
        'server_error' => 'Le réseau est momentanément indisponible : nouvel essai automatique',
        'transport_error' => 'Réseau injoignable : nouvel essai automatique',
        'media_unavailable' => 'Image introuvable ou illisible',
        'credentials_missing' => 'Jeton ou identifiant du compte manquant',
        'message_invalid' => 'Message vide ou trop long',
        'already_published' => 'BD déjà publiée sur ce réseau : non republiée',
        'unknown' => 'Échec de la publication',
        'retry_after' => 'nouvel essai possible dans :seconds s',
    ],

    'templates' => [
        'title' => 'Modèles de messages clés',
        'new' => 'Nouveau modèle',
        'edit_title' => 'Modifier le modèle',
        'empty' => 'Aucun modèle.',
        'inactive' => 'Inactif',
        'fields' => ['title' => 'Titre', 'body' => 'Texte', 'is_active' => 'Proposé à la planification'],
    ],

    'rooms' => [
        'title' => 'Salons de discussion entre pair·es',
        'new' => 'Nouveau salon',
        'edit_title' => 'Modifier le salon',
        'empty' => 'Aucun salon.',
        'inactive' => 'Inactif',
        'activate' => 'Activer',
        'deactivate' => 'Désactiver',
        'summary' => ':max personnes au plus · :moderation · :anonymous',
        'moderated' => 'modération par mots interdits',
        'free' => 'sans modération automatique',
        'pseudonym' => 'sous pseudonyme',
        'named' => 'sans pseudonyme',
        'max_help' => 'De 5 à 500 personnes.',
        'fields' => [
            'name' => 'Nom', 'description' => 'Description', 'max_members' => 'Taille maximale', 'is_moderated' => 'Modération automatique (mots interdits)',
            'allow_anonymous' => 'Participation sous pseudonyme', 'is_active' => 'Salon ouvert',
        ],
    ],

    'toasts' => [
        'connected' => ':network est connecté.',
        'disconnected' => ':network est déconnecté.',
        'scheduled' => 'Publication planifiée.',
        'series_scheduled' => '{1} :count BD planifiée.|[2,*] :count BD planifiées.',
        'marked' => 'Publication marquée comme publiée.',
        'cancelled' => 'Publication annulée.',
        'removed' => 'Publication retirée de la file.',
        'template_saved' => 'Modèle enregistré.',
        'template_deleted' => 'Modèle supprimé.',
        'room_saved' => 'Salon enregistré.',
        'room_activated' => 'Salon ouvert.',
        'room_deactivated' => 'Salon fermé.',
    ],

];

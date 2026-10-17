<?php

declare(strict_types=1);

/*
 * Microcopie transverse de l'espace connecté et des écrans de crise (MICROCOPY.md §5-§6, UX_GUIDELINES.md §3 :
 * chargement, vide, erreur, hors connexion, succès, lecture seule, permission refusée). Registre : tu (Q36) ;
 * la section `public` vouvoie pour les rares messages génériques des pages institutionnelles.
 * Les équivalents de l'administration sont dans admin.php, les libellés de boutons dans buttons.php et common.php.
 */
return [

    'loading' => [
        'default' => 'Chargement…',
        'more' => 'Chargement de la suite…',
        'feed' => 'Chargement du fil…',
        'messages' => 'Chargement des messages…',
        'saving' => 'Enregistrement…',
        'sending' => 'Envoi…',
        'searching' => 'Recherche…',
        'locating' => 'Localisation en cours…',
        'syncing' => 'Synchronisation…',
        'uploading' => 'Téléversement : :percent %',
        'a11y' => 'Chargement en cours, merci de patienter.',
    ],

    'empty' => [
        'title' => 'Rien ici pour l’instant.',
        'feed' => 'Rien ici pour l’instant. Sois la première personne à publier dans cette catégorie.',
        'feed_filter' => 'Aucune publication pour ce filtre.',
        'comments' => 'Aucun commentaire. Écris le premier, avec bienveillance.',
        'search' => 'Aucun résultat pour « :query ». Essaie un autre mot ou élargis les filtres.',
        'search_short' => 'Aucun résultat.',
        'notifications' => 'Aucune notification.',
        'messages' => 'Aucune conversation. Écris à une personne de ta communauté pour commencer.',
        'saved_posts' => 'Aucune publication enregistrée. Utilise « Enregistrer » dans le menu d’une publication.',
        'list' => 'Aucun élément ne correspond aux filtres.',
        'queue' => 'Rien en attente d’envoi.',
        'end_of_list' => 'Tu as tout vu : :count éléments affichés.',
        'load_more_count' => 'Charger plus (:shown sur :total)',
    ],

    'error' => [
        'title' => 'Un problème est survenu',
        'generic' => 'Une erreur s’est produite. Réessaie dans quelques instants ; si le problème persiste, contacte l’équipe Xomiton.',
        'network' => 'Tu es hors connexion. Ton message partira dès que le réseau revient.',
        'timeout' => 'Le serveur met trop de temps à répondre. Réessaie dans quelques instants.',
        'form' => 'Corrige les champs signalés, puis renvoie le formulaire.',
        'not_found' => 'Cet élément est introuvable. Il a peut-être été supprimé.',
        'forbidden' => 'Tu n’as pas accès à cet élément.',
        'rate_limited' => 'Trop de tentatives. Réessaie dans :seconds secondes.',
        'upload_too_large' => 'Fichier trop lourd (:max Mo maximum).',
        'upload_type' => 'Format non pris en charge. Formats acceptés : :types.',
        'copy_failed' => 'Copie impossible. Sélectionne le lien et copie-le à la main.',
        'position_unavailable' => 'Position indisponible (accès refusé ou non pris en charge).',
        'retry_hint' => 'Si le problème persiste, contacte l’équipe depuis le centre d’aide.',
    ],

    'offline' => [
        'banner' => 'Tu es hors connexion. Tes saisies sont conservées et partiront automatiquement au retour du réseau.',
        'toast' => 'Tu es hors connexion. Tes saisies sont conservées.',
        'queued' => 'Enregistré hors connexion. Envoi automatique dès le retour du réseau.',
        'queue_count' => '{0} Rien en attente|{1} :count élément en attente d’envoi|[2,*] :count éléments en attente d’envoi',
        'queue_title' => 'En attente d’envoi',
        'queue_hint' => 'Ces éléments partiront automatiquement dès que le réseau revient. Le SOS part en premier.',
        'pending_message' => 'En attente d’envoi',
        'back_online' => 'Connexion rétablie. Synchronisation en cours…',
        'synced' => '{1} :count élément synchronisé|[2,*] :count éléments synchronisés',
        'synced_partial' => ':sent envoyés, :left en attente.',
        'still_offline' => 'Toujours hors connexion.',
        'nothing_to_sync' => 'Rien à synchroniser.',
        'sync_failed' => 'Synchronisation impossible pour l’instant. Nouvel essai automatique dans quelques minutes.',
        'draft_saved' => 'Brouillon enregistré sur ton téléphone. Tu peux reprendre plus tard.',
        'draft_restored' => 'Brouillon restauré.',
        'available_offline' => 'Disponible hors connexion',
        'saved_offline' => 'Ressource enregistrée pour une lecture hors connexion.',
        'removed_offline' => 'Ressource retirée du hors connexion.',
        'cache_cleared' => 'Cache local vidé.',
    ],

    'success' => [
        'saved' => 'Enregistré.',
        'sent' => 'Envoyé.',
        'deleted' => 'Supprimé.',
        'copied' => 'Lien copié dans le presse-papiers.',
        'theme_applied' => 'Thème appliqué.',
        'settings_saved' => 'Paramètres enregistrés.',
        'feedback' => 'Merci pour ton retour.',
        'feedback_improve' => 'Merci, nous allons améliorer cet article.',
    ],

    'confirm' => [
        'title' => 'Confirmer ?',
        'delete_title' => 'Supprimer définitivement ?',
        'delete_text' => 'Cette action est définitive.',
        'delete_post' => 'Supprimer cette publication ? Ses commentaires seront aussi supprimés.',
        'delete_comment' => 'Supprimer ce commentaire ?',
        'delete_message' => 'Supprimer ce message pour toi ? Les autres participant·es le verront encore.',
        'leave_group' => 'Quitter le groupe « :name » ? Tu pourras le rejoindre à nouveau plus tard.',
        'leave_conversation' => 'Quitter cette conversation ?',
        'cancel_invitation' => 'Annuler l’invitation envoyée à :name ?',
        'block_user' => 'Bloquer :name ? Cette personne ne pourra plus t’écrire ni voir ton profil.',
        'unsaved' => 'Abandonner les modifications ? Ce que tu as saisi sera perdu.',
        'delete_account' => 'Supprimer ton compte ? Ton profil et tes publications seront effacés sous :days jours. Tes signalements anonymes déjà transmis sont conservés pour le suivi.',
        'delete_word' => 'SUPPRIMER',
        'type_to_confirm' => 'Tape :word pour confirmer.',
        'irreversible' => 'Cette action ne peut pas être annulée.',
    ],

    'readonly' => [
        'banner' => 'Lecture seule : :reason',
        'closed' => 'Cet élément est clos. Les actions sont désactivées.',
        'archived' => 'Archivé : consultation seulement.',
    ],

    'permission' => [
        'denied' => 'Tu n’as pas la permission de faire cela.',
        'denied_contact' => 'Tu n’as pas la permission de faire cela. Si tu penses qu’il s’agit d’une erreur, contacte l’équipe Xomiton.',
        'members_only' => 'Réservé aux membres connecté·es. Crée un compte ou connecte-toi.',
        'peer_only' => 'Réservé aux pair·es éducateur·rices validé·es.',
    ],

    'a11y' => [
        'new_items' => '{1} :count nouvel élément|[2,*] :count nouveaux éléments',
        'opens_new_tab' => '(nouvelle fenêtre)',
        'external_link' => '(lien externe)',
        'required' => 'obligatoire',
        'close_dialog' => 'Fermer la fenêtre',
        'main_menu' => 'Menu principal',
        'user_menu' => 'Mon compte',
        'pagination' => 'Pagination',
        'current_page' => 'Page actuelle',
        'sort_asc' => 'tri croissant',
        'sort_desc' => 'tri décroissant',
        'selected' => 'sélectionné',
        'expanded' => 'déplié',
        'collapsed' => 'replié',
        'your_avatar' => 'Ton avatar',
    ],

    // Pages institutionnelles (vous) : seuls messages génériques nécessaires hors de leurs fichiers propres.
    'public' => [
        'error_generic' => 'Une erreur s’est produite. Veuillez réessayer dans quelques instants.',
        'error_form' => 'Veuillez corriger les champs signalés.',
        'offline_queued' => 'Enregistré hors connexion : votre message partira automatiquement au retour du réseau.',
        'back_online' => 'Connexion rétablie.',
        'loading' => 'Chargement…',
        'no_results' => 'Aucun résultat pour « :query ». Essayez un autre mot ou contactez l’équipe.',
        'cookie_notice' => 'Xomiton n’utilise aucun traceur publicitaire. Seuls des cookies techniques (session, préférences) sont déposés.',
    ],

];

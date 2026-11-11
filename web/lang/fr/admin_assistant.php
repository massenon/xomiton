<?php

declare(strict_types=1);

/*
 * Administration, Assistant LLM `/admin/assistant` (P12-07 ; prototype admin/assistant.php). Registre : vous, neutre et
 * précis (VOICE_AND_TONE.md §3, administration). Aucun texte de conversation en dehors de la lecture explicite d’un échange.
 */
return [

    'title' => 'Assistant LLM',
    'intro' => 'Réglages de l’assistant des membres : fournisseur, consigne système, garde-fous, base de connaissances et conversations signalées. Les réponses de crise (numéros d’urgence, SOS) sont toujours données par la plateforme, avant tout fournisseur.',
    'save' => 'Enregistrer',
    'edit' => 'Modifier',
    'delete' => 'Supprimer',

    'state' => [
        'on' => 'Fournisseur actif',
        'off' => 'Fournisseur désactivé',
        'on_text' => 'Les membres reçoivent la réponse du fournisseur configuré, sauf en situation de crise.',
        'off_text' => 'Les membres reçoivent la réponse de la base de connaissances, comme les visiteur·ses.',
        'provider' => 'Pilote effectif :',
        'provider_help' => '« kb » répond depuis la base de connaissances, sans service externe. Le fournisseur LLM n’est pas encore choisi.',
        'disable' => 'Désactiver le fournisseur',
        'enable' => 'Activer le fournisseur',
    ],

    'confirm' => [
        'disable' => 'Désactiver le fournisseur ? Les membres recevront les réponses de la base de connaissances.',
        'reset_prompt' => 'Rétablir la consigne par défaut ? Votre texte sera remplacé.',
        'delete_kb' => 'Supprimer cette entrée de la base de connaissances ?',
    ],

    'kpi' => [
        'conversations' => 'Conversations enregistrées',
        'conversations_help' => 'Seules les conversations dont le·la membre a demandé l’enregistrement.',
        'answered' => 'Réponses de la base',
        'flagged' => 'Signalées',
        'escalated' => 'Escaladées',
    ],

    'prompt' => [
        'title' => 'Consigne système',
        'label' => 'Consigne système',
        'help' => 'Texte transmis au fournisseur LLM pour cadrer ses réponses. Le pilote « kb » actuel ne l’utilise pas.',
        'counter' => ':count / :max caractères',
        'reset' => 'Rétablir la consigne par défaut',
    ],

    'guardrails' => [
        'title' => 'Garde-fous et escalade vers un humain',
        'help' => 'Consignes transmises au fournisseur LLM. Les garde-fous de crise intégrés à la plateforme ne se désactivent pas.',
        'items' => [
            'no_diagnosis' => 'Interdire tout diagnostic médical ou psychologique',
            'no_pii' => 'Ne jamais demander d’informations identifiantes',
            'topic_filter' => 'Refuser les sujets hors périmètre (violences, santé sexuelle, droits)',
            'sos_banner' => 'Rappeler le SOS pour tout message à risque',
            'log_anon' => 'Journaliser les conversations de façon anonymisée',
            'disclaimer' => 'Rappeler que l’assistant ne remplace pas un·e professionnel·le',
        ],
        'escalation' => 'Escalade vers un·e humain·e',
        'keywords' => 'Mots-clés déclencheurs',
        'target' => 'Destinataire',
        'targets' => ['focal' => 'Point focal de la zone', 'psychologist' => 'Psychologue de la zone', 'jurist' => 'Juriste de la zone', 'all_focal' => 'Tous les points focaux'],
    ],

    'kb' => [
        'title' => 'Base de connaissances',
        'new' => 'Nouvelle entrée',
        'edit_title' => 'Modifier l’entrée',
        'search_placeholder' => 'Question ou réponse…',
        'search_help' => 'La recherche porte sur les questions et les réponses.',
        'caption' => 'Base de connaissances : question, mots-clés, nombre de réponses et actions.',
        'columns' => ['state' => 'État', 'question' => 'Question', 'keywords' => 'Mots-clés', 'hits' => 'Réponses'],
        'states' => ['all' => 'Toutes les entrées', 'active' => 'Actives', 'inactive' => 'Inactives'],
        'inactive' => 'Inactive',
        'deactivate' => 'Désactiver',
        'activate' => 'Activer',
        'empty' => 'Aucune entrée',
        'empty_text' => 'Ajoutez une entrée avec « Nouvelle entrée », ou modifiez les filtres.',
        'fields' => ['question' => 'Question', 'answer' => 'Réponse', 'keywords' => 'Mots-clés', 'is_active' => 'Entrée active'],
        'answer_help' => 'Rédigez en tutoyant la personne, en phrases courtes : c’est ce qu’elle lira.',
        'keywords_help' => 'Séparés par des virgules, 20 au plus. Une entrée répond quand l’un d’eux apparaît dans le message.',
    ],

    'flagged' => [
        'title' => 'Conversations signalées',
        'help' => 'Aucun texte n’est affiché ici. Lire un échange est journalisé.',
        'no_reason' => 'Sans motif',
        'visitor' => 'Visiteur·se',
        'member' => 'Membre',
        'messages' => '{0} Aucun message|{1} 1 message|[2,*] :count messages',
        'escalated' => 'Escaladée',
        'read' => 'Lire l’échange',
        'sr' => 'n° :id',
        'empty' => 'Aucune conversation signalée',
        'modal_title' => 'Conversation signalée',
        'modal_help' => 'Lecture journalisée. Ne copiez pas ces messages en dehors de l’administration.',
        'roles' => ['user' => 'Personne', 'assistant' => 'Assistant', 'system' => 'Système'],
        'no_messages' => 'Cette conversation ne contient plus de message.',
        'resolve' => 'Marquer comme traitée',
    ],

    'stats' => [
        'title' => 'Statistiques',
        'help' => 'Comptages uniquement, jamais de texte. Les échanges non enregistrés ne sont pas comptés dans les conversations.',
        'kb_active' => 'Entrées actives',
        'kb_hits' => 'Réponses de la base (total)',
        'top' => 'Entrées les plus consultées',
        'top_empty' => 'Aucune réponse de la base pour l’instant.',
    ],

    'toasts' => [
        'enabled' => 'Fournisseur activé.',
        'disabled' => 'Fournisseur désactivé : la base de connaissances répond.',
        'prompt_saved' => 'Consigne enregistrée.',
        'prompt_reset' => 'Consigne par défaut rétablie.',
        'guardrails_saved' => 'Garde-fous et escalade enregistrés.',
        'kb_saved' => 'Entrée enregistrée.',
        'kb_activated' => 'Entrée activée.',
        'kb_deactivated' => 'Entrée désactivée.',
        'kb_deleted' => 'Entrée supprimée.',
        'resolved' => 'Conversation marquée comme traitée.',
    ],

    'errors' => [
        'prompt_min' => 'La consigne doit contenir au moins :min caractères.',
        'prompt_max' => 'La consigne ne doit pas dépasser :max caractères.',
        'keywords_required' => 'Indiquez au moins un mot-clé.',
        'keywords_max' => 'Indiquez :max mots-clés au plus.',
    ],

];

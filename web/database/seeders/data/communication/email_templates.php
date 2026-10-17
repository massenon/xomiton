<?php

declare(strict_types=1);

/*
 * Les 8 modèles d'e-mails du prototype (prototype_social/data/email_templates.json), dans l'ordre du fichier.
 * `category` est le libellé du prototype (converti par TemplateCategory::fromPrototype) ; `system` = template
 * système au slug immuable (RG-10). Les variables {{...}} sont détectées par EmailTemplate::extractVariables().
 */
return [
    [
        'name' => 'Réinitialisation du mot de passe',
        'slug' => 'reinitialisation-mot-de-passe',
        'category' => 'Authentification',
        'system' => true,
        'subject' => '{{site_name}} : choisissez un nouveau mot de passe',
        'preheader' => 'Ce lien est valable 60 minutes.',
        'body' => '<h2>Bonjour {{user_first_name}},</h2><p>Vous avez demandé à réinitialiser votre mot de passe {{site_name}}. Cliquez sur le lien ci-dessous pour en choisir un nouveau :</p><p><a href="{{reset_link}}" rel="noopener noreferrer" target="_blank"><strong>Choisir un nouveau mot de passe</strong></a></p><p>Ce lien est valable 60 minutes. Si vous n’êtes pas à l’origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.</p>',
        'active' => true,
    ],
    [
        'name' => 'Activation du compte',
        'slug' => 'activation-compte',
        'category' => 'Authentification',
        'system' => true,
        'subject' => 'Activez votre compte {{site_name}}',
        'preheader' => 'Une dernière étape avant de rejoindre la communauté.',
        'body' => '<h2>Bienvenue {{user_first_name}} 💜</h2><p>Merci de rejoindre {{site_name}}. Pour activer votre compte, confirmez votre adresse e-mail :</p><p><a href="{{activation_link}}" rel="noopener noreferrer" target="_blank"><strong>Activer mon compte</strong></a></p><p>Votre pseudonyme : <strong>{{user_pseudo}}</strong>. Vous pourrez compléter votre profil plus tard, à votre rythme.</p>',
        'active' => true,
    ],
    [
        'name' => 'Code de vérification (OTP)',
        'slug' => 'code-verification',
        'category' => 'Authentification',
        'system' => true,
        'subject' => 'Votre code {{site_name}} : {{otp_code}}',
        'preheader' => 'Code valable {{otp_expiry}}.',
        'body' => '<p>Bonjour {{user_first_name}},</p><p>Voici votre code de vérification :</p><h1>{{otp_code}}</h1><p>Il est valable {{otp_expiry}}. Ne le communiquez à personne, même à une personne qui dit faire partie de l’équipe {{site_name}}.</p>',
        'active' => true,
    ],
    [
        'name' => 'Bienvenue',
        'slug' => 'bienvenue',
        'category' => 'Communauté',
        'system' => false,
        'subject' => 'Bienvenue dans votre espace safe, {{user_first_name}}',
        'preheader' => 'Vos premiers pas sur Xomiton.',
        'body' => '<h2>Bienvenue {{user_first_name}} !</h2><p>Votre compte est actif. Voici trois idées pour commencer :</p><ul><li>Explorez les thématiques et les ressources.</li><li>Présentez-vous dans le fil, sous pseudonyme si vous le souhaitez.</li><li>Enregistrez les contacts d’urgence de votre zone.</li></ul><p><a href="{{login_link}}" rel="noopener noreferrer" target="_blank">Me connecter</a></p>',
        'active' => true,
    ],
    [
        'name' => 'Compte validé par l’équipe',
        'slug' => 'compte-valide',
        'category' => 'Communauté',
        'system' => false,
        'subject' => 'Votre compte {{site_name}} est validé',
        'preheader' => 'Vous pouvez vous connecter.',
        'body' => '<p>Bonjour {{user_first_name}},</p><p>Bonne nouvelle : l’équipe {{site_name}} a validé votre inscription. Vous pouvez désormais vous connecter.</p><p><a href="{{login_link}}" rel="noopener noreferrer" target="_blank"><strong>Me connecter</strong></a></p>',
        'active' => true,
    ],
    [
        'name' => 'Alerte routée vers un·e intervenant·e',
        'slug' => 'alerte-routee',
        'category' => 'Alertes',
        'system' => true,
        'subject' => '[{{site_name}}] Nouvelle alerte {{alert_reference}} — {{alert_zone}}',
        'preheader' => 'Une alerte vous a été attribuée.',
        'body' => '<p>Bonjour {{user_first_name}},</p><p>L’alerte <strong>{{alert_reference}}</strong> ({{alert_zone}}) vous a été attribuée par le routage automatique.</p><p>Merci de la prendre en charge depuis votre espace dans les meilleurs délais.</p><p><a href="{{login_link}}" rel="noopener noreferrer" target="_blank">Ouvrir mon espace</a></p>',
        'active' => true,
    ],
    [
        'name' => 'Rappel d’événement',
        'slug' => 'rappel-evenement',
        'category' => 'Communauté',
        'system' => false,
        'subject' => 'Rappel : {{event_title}}',
        'preheader' => '{{event_date}}',
        'body' => '<p>Bonjour {{user_first_name}},</p><p>Petit rappel : <strong>{{event_title}}</strong> a lieu {{event_date}}. Venez avec une amie !</p>',
        'active' => true,
    ],
    [
        'name' => 'Confirmation d’inscription à la newsletter',
        'slug' => 'newsletter-confirmation',
        'category' => 'Newsletter',
        'system' => true,
        'subject' => 'Confirmez votre inscription à la newsletter {{site_name}}',
        'preheader' => 'Un clic pour confirmer.',
        'body' => '<p>Bonjour,</p><p>Merci de votre intérêt pour la newsletter {{site_name}}. Confirmez votre inscription :</p><p><a href="{{activation_link}}" rel="noopener noreferrer" target="_blank"><strong>Confirmer mon inscription</strong></a></p><p>Vous pourrez vous désinscrire à tout moment : <a href="{{unsubscribe_link}}" rel="noopener noreferrer" target="_blank">se désinscrire</a>.</p>',
        'active' => true,
    ],
];

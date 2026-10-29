<?php

declare(strict_types=1);

/*
 * Page publique « Supprimer mon compte et mes données » (`/supprimer-mon-compte`, exigence Google Play : URL web de
 * suppression, accessible sans connexion) et ses états (demande envoyée, confirmation, terminé, lien invalide).
 * Registre : vous. Jetons {nom} : App\Application\Legal\LegalTexts. La procédure décrite est celle du code : DELETE
 * /api/v1/me (RequestAccountDeletion) puis accounts:anonymize après `deletion_grace_days`.
 */
return [

    'title' => 'Supprimer mon compte et mes données',
    'back_home' => 'Retour à l’accueil',

    'page' => [
        'title' => 'Supprimer mon compte et mes données',
        'lead' => 'Comment supprimer votre compte Xomiton, ce qui est effacé, ce qui est conservé et pourquoi, y compris si vous n’avez plus accès à votre compte.',
        'sections' => [
            [
                'id' => 'application',
                'title' => 'Depuis l’application ou le site (connecté·e)',
                'blocks' => [
                    ['ol' => [
                        'Ouvrez l’application Xomiton (ou connectez-vous sur le site).',
                        'Allez dans Paramètres, puis Sécurité.',
                        'Choisissez « Supprimer mon compte », tapez SUPPRIMER pour confirmer.',
                    ]],
                    'Avant de supprimer, vous pouvez télécharger une copie de vos données depuis Paramètres > Mes données (lien envoyé par e-mail, valable {export_hours} heures).',
                ],
            ],
            [
                'id' => 'delais',
                'title' => 'Ce qui se passe, et quand',
                'blocks' => [
                    ['list' => [
                        'Tout de suite : votre compte est fermé. Vous êtes déconnecté·e de tous vos appareils, les notifications s’arrêtent et la newsletter est désactivée. Un e-mail vous confirme la suppression.',
                        'Sous {grace_days} jours : vos données personnelles sont effacées de façon irréversible (anonymisation). Pendant ce délai, vous pouvez changer d’avis en écrivant à {contact_email}.',
                    ]],
                ],
            ],
            [
                'id' => 'efface',
                'title' => 'Ce qui est effacé',
                'blocks' => [
                    ['list' => [
                        'votre pseudonyme, votre nom, votre adresse e-mail, votre numéro de téléphone et votre mot de passe ;',
                        'votre profil : biographie, pronoms, photo, couverture, centres d’intérêt, origine, orientation et identité de genre, contacts de confiance ;',
                        'vos réglages, vos abonnements aux notifications, vos sessions, vos appareils connectés et vos codes de vérification ;',
                        'votre abonnement à la newsletter (adresse effacée) ;',
                        'l’adresse e-mail associée à vos preuves de consentement.',
                    ]],
                ],
            ],
            [
                'id' => 'conserve',
                'title' => 'Ce qui est conservé, et pourquoi',
                'blocks' => [
                    ['list' => [
                        'Vos publications et commentaires déjà publiés restent visibles sous un pseudonyme anonyme (« membre supprimé »), sans lien avec votre identité, pour ne pas casser les échanges des autres membres. Écrivez-nous si vous voulez qu’ils soient retirés.',
                        'Les signalements et dossiers déjà transmis à l’équipe, qui servent à protéger des personnes, sont conservés {case_years} ans après la clôture du dossier, puis anonymisés. Un signalement anonyme n’est de toute façon pas rattaché à votre compte.',
                        'Le journal des actions sensibles (dont la preuve de vos consentements) est gardé {audit_days} jours pour la sécurité et nos obligations légales.',
                        'Les journaux d’envoi d’e-mails sont gardés {mail_days} jours, sans le contenu des messages.',
                        'Des sauvegardes techniques peuvent contenir vos données jusqu’à leur remplacement.',
                    ]],
                ],
            ],
            [
                'id' => 'partiel',
                'title' => 'Supprimer seulement certaines données',
                'blocks' => [
                    'Vous pouvez supprimer vos publications et modifier votre profil depuis l’application. Pour retirer un consentement (orientation, identité de genre, notifications, newsletter), utilisez vos paramètres. Pour toute autre demande, écrivez à {contact_email}.',
                    ['note' => 'Les comptes de l’équipe d’administration ne se suppriment pas depuis cette page : écrivez à {contact_email}.'],
                ],
            ],
        ],
    ],

    'form' => [
        'nav' => 'Je n’ai plus accès à mon compte',
        'title' => 'Je n’ai plus accès à mon compte',
        'intro' => 'Indiquez l’adresse e-mail de votre compte. Si un compte existe, nous y envoyons un lien de confirmation valable :hours heures. Rien n’est supprimé tant que vous n’avez pas cliqué sur ce lien, puis confirmé.',
        'email_label' => 'Adresse e-mail du compte',
        'email_hint' => 'La même qu’à l’inscription.',
        'confirm_label' => 'Je demande la suppression du compte associé à cette adresse et de mes données, sous :days jours.',
        'submit' => 'Recevoir le lien de confirmation',
        'privacy' => 'Nous n’utilisons cette adresse que pour cette demande. <a href=":url">En savoir plus sur vos droits</a>.',
        'email_invalid' => 'Saisissez une adresse e-mail valide.',
        'confirm_required' => 'Cochez la case pour confirmer votre demande.',
        'too_many' => 'Trop de demandes pour l’instant. Réessayez dans :minutes minute(s).',
    ],

    'sent' => [
        'title' => 'Demande reçue',
        'paragraphs' => [
            'Si un compte correspond à cette adresse, un e-mail avec un lien de confirmation vient de vous être envoyé. Le lien est valable {request_hours} heures.',
            'Vous ne le voyez pas ? Regardez dans les courriers indésirables. Pour des raisons de sécurité, nous ne disons pas si un compte existe. Une question ? Écrivez à {contact_email}.',
        ],
    ],

    'confirm' => [
        'title' => 'Confirmer la suppression',
        'paragraphs' => [
            'Vous allez supprimer votre compte Xomiton. Vous serez déconnecté·e de tous vos appareils, et vos données personnelles seront effacées sous {grace_days} jours.',
            'Cette action est définitive. Pour changer d’avis pendant le délai, écrivez à {contact_email}.',
        ],
        'submit' => 'Oui, supprimer mon compte',
        'cancel' => 'Annuler',
    ],

    'done' => [
        'title' => 'Suppression lancée',
        'paragraphs' => [
            'Votre compte est fermé. Vos données personnelles seront effacées sous {grace_days} jours. Un e-mail de confirmation vous a été envoyé.',
        ],
    ],

    'invalid' => [
        'title' => 'Lien invalide ou expiré',
        'paragraphs' => [
            'Ce lien a déjà été utilisé, a expiré ou n’existe pas. Vous pouvez refaire une demande depuis la page « Supprimer mon compte et mes données », ou écrire à {contact_email}.',
        ],
    ],
];

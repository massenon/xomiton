<?php

declare(strict_types=1);

/*
 * English — planned locale (config('xomiton.locales_planned'), Q40). Only errors.php and common.php are translated so
 * far; every other key falls back to French. Same structure as lang/fr/errors.php: keep both files in sync.
 */
return [

    'page_title' => 'Error :code',
    'code' => 'Error :code',
    'help' => 'Need help? Visit the help centre or write to the Xomiton team.',

    '403' => [
        'title' => 'Access denied',
        'text' => 'You do not have permission to view this page. If you think this is a mistake, contact the Xomiton team.',
        'action' => 'Back to home',
    ],

    '404' => [
        'title' => 'Oops, this page cannot be found',
        'text' => 'The page you are looking for may have moved or no longer exists. You did nothing wrong: let’s find the right way together.',
        'action' => 'Back to home',
        'secondary' => 'Help centre',
    ],

    '419' => [
        'public' => [
            'title' => 'Session expired',
            'text' => 'Your session has expired, please try again. For your safety, the information you entered was not sent.',
            'action' => 'Try again',
        ],
        'member' => [
            'title' => 'Session expired',
            'text' => 'Your session has expired. Sign in again, then retry: nothing was sent.',
            'action' => 'Sign in again',
        ],
    ],

    '422' => [
        'title' => 'Information to correct',
        'text' => 'Some information is missing or incorrect. Correct the highlighted fields, then send the form again.',
        'action' => 'Back to the form',
    ],

    '429' => [
        'title' => 'Too many attempts',
        'text' => 'To protect the platform, the number of actions per minute is limited. You can try again in :seconds seconds.',
        'text_no_delay' => 'To protect the platform, the number of actions per minute is limited. Wait a moment before trying again.',
        'action' => 'Try again',
    ],

    '500' => [
        'title' => 'Something went wrong',
        'text' => 'An error occurred on our side. The team has been notified; please try again in a few moments.',
        'action' => 'Back to home',
    ],

    '503' => [
        'title' => 'Maintenance in progress',
        'text' => 'Xomiton will be back in a few minutes. Thank you for your patience.',
        'action' => 'Refresh the page',
    ],

    'emergency' => [
        'title' => 'In danger?',
        'text' => 'Use the SOS button or call 117.',
        'sos' => 'SOS',
        'contacts' => 'Emergency contacts',
    ],

    'api' => [
        'unauthenticated' => 'Sign-in required.',
        'forbidden' => 'Action not allowed.',
        'not_found' => 'Resource not found.',
        'conflict' => 'Conflict: a newer version already exists.',
        'validation' => 'The submitted data is invalid.',
        'throttled' => 'Too many requests. You can try again in :seconds seconds.',
        'server' => 'Internal error. Try again later.',
        'maintenance' => 'Platform under maintenance. Try again in a few minutes.',
    ],

];

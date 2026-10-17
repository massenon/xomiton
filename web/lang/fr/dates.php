<?php

declare(strict_types=1);

/*
 * Dates, heures et nombres (VOICE_AND_TONE.md §6, NFR-11) : noms des mois et des jours, motifs ICU utilisés par
 * App\Support\LocaleFormatter (locale intl fr-BJ, repli fr-FR) et formulations relatives (« il y a 3 min »).
 * Texte : « 2 novembre 2026 », « 15 h » ; tableaux : « 02/11/2026 », « 15:00 » ; nombres : « 1 234 » (espace fine insécable).
 * Partagé avec Vue (Intl.DateTimeFormat('fr-BJ')) et Flutter (intl, fr_BJ) via lang:export.
 */
return [

    'months' => [
        1 => 'janvier',
        2 => 'février',
        3 => 'mars',
        4 => 'avril',
        5 => 'mai',
        6 => 'juin',
        7 => 'juillet',
        8 => 'août',
        9 => 'septembre',
        10 => 'octobre',
        11 => 'novembre',
        12 => 'décembre',
    ],

    'months_short' => [
        1 => 'janv.',
        2 => 'févr.',
        3 => 'mars',
        4 => 'avr.',
        5 => 'mai',
        6 => 'juin',
        7 => 'juil.',
        8 => 'août',
        9 => 'sept.',
        10 => 'oct.',
        11 => 'nov.',
        12 => 'déc.',
    ],

    // ISO 8601 : 1 = lundi … 7 = dimanche.
    'days' => [
        1 => 'lundi',
        2 => 'mardi',
        3 => 'mercredi',
        4 => 'jeudi',
        5 => 'vendredi',
        6 => 'samedi',
        7 => 'dimanche',
    ],

    'days_short' => [
        1 => 'lun.',
        2 => 'mar.',
        3 => 'mer.',
        4 => 'jeu.',
        5 => 'ven.',
        6 => 'sam.',
        7 => 'dim.',
    ],

    // Motifs ICU (IntlDateFormatter). L'espace avant « h » et « à » est insécable.
    'formats' => [
        'long' => 'd MMMM y',
        'long_with_day' => 'EEEE d MMMM y',
        'medium' => 'd MMM y',
        'short' => 'dd/MM/y',
        'day_month' => 'd MMMM',
        'day_month_short' => 'dd/MM',
        'month_year' => 'MMMM y',
        'time_text' => "H\u{00A0}'h'",
        'time_text_minutes' => "H\u{00A0}'h'\u{00A0}mm",
        'time_table' => 'HH:mm',
        'datetime_text' => "d MMMM y 'à' H\u{00A0}'h'",
        'datetime_text_minutes' => "d MMMM y 'à' H\u{00A0}'h'\u{00A0}mm",
        'datetime_table' => 'dd/MM/y HH:mm',
        'iso_date' => 'yyyy-MM-dd',
    ],

    // Formulations relatives (prototype x_ago()).
    'relative' => [
        'now' => 'à l’instant',
        'minutes' => '{1} il y a :count min|[2,*] il y a :count min',
        'hours' => '{1} il y a :count h|[2,*] il y a :count h',
        'days' => '{1} il y a :count j|[2,*] il y a :count j',
        'in_minutes' => 'dans :count min',
        'in_hours' => '{1} dans :count h|[2,*] dans :count h',
        'in_days' => '{1} dans :count j|[2,*] dans :count j',
        'yesterday' => 'hier',
        'today' => 'aujourd’hui',
        'tomorrow' => 'demain',
        'on_date' => 'le :date',
        'at_time' => 'à :time',
        'ago_prefix' => 'il y a',
    ],

    'range' => [
        'dates' => 'du :from au :to',
        'times' => 'de :from à :to',
        'since' => 'depuis le :date',
        'until' => 'jusqu’au :date',
        'duration_minutes' => '{1} :count minute|[2,*] :count minutes',
        'duration_hours' => '{1} :count heure|[2,*] :count heures',
        'duration_days' => '{1} :count jour|[2,*] :count jours',
        'duration_weeks' => '{1} :count semaine|[2,*] :count semaines',
        'duration_months' => '{1} :count mois|[2,*] :count mois',
        'duration_years' => '{1} :count an|[2,*] :count ans',
    ],

    'units' => [
        'percent' => ':value %',
        'meters' => ':value m',
        'kilometers' => ':value km',
        'kilobytes' => ':value Ko',
        'megabytes' => ':value Mo',
        'accuracy' => '± :value m',
        'per_minute' => ':value / min',
    ],

    'labels' => [
        'date' => 'Date',
        'time' => 'Heure',
        'datetime' => 'Date et heure',
        'period' => 'Période',
        'from' => 'Du',
        'to' => 'Au',
        'timezone' => 'Heure du Bénin (UTC+1)',
        'no_end' => 'Sans date de fin',
        'undated' => 'Date inconnue',
        'all_day' => 'Toute la journée',
    ],

    'periods' => [
        'today' => 'Aujourd’hui',
        'yesterday' => 'Hier',
        'this_week' => 'Cette semaine',
        'last_week' => 'Semaine dernière',
        'this_month' => 'Ce mois-ci',
        'last_month' => 'Mois dernier',
        'last_30_days' => '30 derniers jours',
        'last_12_months' => '12 derniers mois',
        'this_year' => 'Cette année',
        'custom' => 'Période personnalisée',
    ],

];

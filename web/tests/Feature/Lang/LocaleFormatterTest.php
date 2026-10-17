<?php

declare(strict_types=1);

use App\Support\LocaleFormatter;
use App\Support\Locales;
use Carbon\CarbonImmutable;

/*
 * App\Support\LocaleFormatter et Locales (P3-05, NFR-11) : dates longues « 2 novembre 2026 », courtes « 02/11/2026 »,
 * heures « 15 h », nombres « 1 234 » (espace fine insécable), fr-BJ avec repli fr-FR via intl.
 */

const NBSP = "\u{00A0}";
const NNBSP = "\u{202F}";

beforeEach(function (): void {
    config(['app.timezone' => 'UTC']);
    app()->setLocale('fr');
    $this->fmt = LocaleFormatter::make('fr', 'UTC');
    $this->date = CarbonImmutable::create(2026, 11, 2, 15, 0, 0, 'UTC');
});

it('résout la locale intl fr-BJ avec repli fr-FR et refuse les locales incomplètes', function (): void {
    expect(Locales::intlTag('fr'))->toBe('fr_BJ')
        ->and(Locales::bcp47('fr'))->toBe('fr-BJ')
        ->and(Locales::htmlLang('fr'))->toBe('fr')
        ->and(Locales::intlTag('fon'))->toBeIn(['fon_BJ', 'fr_BJ'])
        ->and(Locales::available())->toBe(['fr'])
        ->and(Locales::planned())->toBe(['en', 'fon', 'yo'])
        ->and(Locales::all())->toBe(['fr', 'en', 'fon', 'yo'])
        ->and(Locales::isAvailable('fr'))->toBeTrue()
        ->and(Locales::isAvailable('en'))->toBeFalse()
        ->and(Locales::isPlanned('yo'))->toBeTrue()
        ->and(Locales::default())->toBe('fr')
        ->and($this->fmt->intlTag())->toBe('fr_BJ');

    config(['xomiton.intl_locales.zz' => ['zz-ZZ', 'fr-FR']]);

    expect(Locales::intlTag('zz'))->toBe('fr_FR');
});

it('formate les dates en texte et en tableau', function (): void {
    expect($this->fmt->longDate($this->date))->toBe('2 novembre 2026')
        ->and($this->fmt->longDateWithDay($this->date))->toBe('lundi 2 novembre 2026')
        ->and($this->fmt->mediumDate($this->date))->toBe('2 nov. 2026')
        ->and($this->fmt->shortDate($this->date))->toBe('02/11/2026')
        ->and($this->fmt->dayMonth($this->date))->toBe('2 novembre')
        ->and($this->fmt->dayMonthShort($this->date))->toBe('02/11')
        ->and($this->fmt->monthYear($this->date))->toBe('novembre 2026')
        ->and($this->fmt->isoDate($this->date))->toBe('2026-11-02')
        ->and($this->fmt->longDate('2026-11-02 15:00:00'))->toBe('2 novembre 2026')
        ->and($this->fmt->longDate($this->date->getTimestamp()))->toBe('2 novembre 2026')
        ->and($this->fmt->longDate(null))->toBe('')
        ->and($this->fmt->longDate('pas une date'))->toBe('');
});

it('formate les heures « 15 h », « 15 h 30 » et « 15:00 »', function (): void {
    expect($this->fmt->time($this->date))->toBe('15'.NBSP.'h')
        ->and($this->fmt->time($this->date->setTime(15, 30)))->toBe('15'.NBSP.'h'.NBSP.'30')
        ->and($this->fmt->time($this->date->setTime(9, 5)))->toBe('9'.NBSP.'h'.NBSP.'05')
        ->and($this->fmt->tableTime($this->date))->toBe('15:00')
        ->and($this->fmt->tableTime($this->date->setTime(9, 5)))->toBe('09:05')
        ->and($this->fmt->dateTime($this->date))->toBe('2 novembre 2026 à 15'.NBSP.'h')
        ->and($this->fmt->dateTime($this->date->setTime(15, 30)))->toBe('2 novembre 2026 à 15'.NBSP.'h'.NBSP.'30')
        ->and($this->fmt->tableDateTime($this->date))->toBe('02/11/2026 15:00')
        ->and($this->fmt->time(null))->toBe('');
});

it('convertit vers le fuseau d’affichage', function (): void {
    $benin = LocaleFormatter::make('fr', 'Africa/Porto-Novo');

    expect($benin->time($this->date))->toBe('16'.NBSP.'h')
        ->and($benin->tableDateTime(CarbonImmutable::create(2026, 11, 2, 23, 30, 0, 'UTC')))->toBe('03/11/2026 00:30');
});

it('formate les nombres avec espace fine insécable et virgule décimale', function (): void {
    expect($this->fmt->number(1234))->toBe('1'.NNBSP.'234')
        ->and($this->fmt->number(1234567))->toBe('1'.NNBSP.'234'.NNBSP.'567')
        ->and($this->fmt->number(999))->toBe('999')
        ->and($this->fmt->number(1234.5, 1))->toBe('1'.NNBSP.'234,5')
        ->and($this->fmt->number(0.5, 2))->toBe('0,50')
        ->and($this->fmt->percent(96))->toBe('96'.NBSP.'%')
        ->and($this->fmt->percent(12.5, 1))->toBe('12,5'.NBSP.'%')
        ->and($this->fmt->fileSize(850 * 1024))->toBe('850'.NBSP.'Ko')
        ->and($this->fmt->fileSize((int) (1.2 * 1024 * 1024)))->toBe('1,2'.NBSP.'Mo')
        ->and($this->fmt->accuracy(12))->toBe('±'.NBSP.'12'.NBSP.'m');
});

it('exprime les durées relatives comme le prototype', function (): void {
    $now = CarbonImmutable::create(2026, 11, 2, 15, 0, 0, 'UTC');

    expect($this->fmt->ago($now->subSeconds(30), $now))->toBe('à l’instant')
        ->and($this->fmt->ago($now->subMinutes(5), $now))->toBe('il y a 5 min')
        ->and($this->fmt->ago($now->subHours(3), $now))->toBe('il y a 3 h')
        ->and($this->fmt->ago($now->subDays(2), $now))->toBe('il y a 2 j')
        ->and($this->fmt->ago($now->subDays(10), $now))->toBe('23/10/2026')
        ->and($this->fmt->ago($now->addMinutes(5), $now))->toBe('dans 5 min')
        ->and($this->fmt->ago($now->addHours(2), $now))->toBe('dans 2 h')
        ->and($this->fmt->ago(null, $now))->toBe('');
});

it('nomme les mois et les jours depuis lang/fr/dates.php', function (): void {
    expect($this->fmt->monthName(11))->toBe('novembre')
        ->and($this->fmt->monthName(2, true))->toBe('févr.')
        ->and($this->fmt->dayName(1))->toBe('lundi')
        ->and($this->fmt->dayName(7, true))->toBe('dim.');
});

it('utilise la locale intl de la langue demandée et retombe sur les motifs français par défaut', function (): void {
    $en = LocaleFormatter::make('en', 'UTC');

    expect($en->intlTag())->toBe('en_GB')
        ->and($en->longDate($this->date))->toBe('2 November 2026')
        ->and($en->number(1234.5, 1))->toBe('1,234.5');
});

<?php

declare(strict_types=1);

use App\Domain\Feed\ContentFilter;
use App\Domain\Feed\PollBallot;
use App\Domain\Feed\PostAudience;
use App\Domain\Moderation\AutoHideRule;
use App\Enums\BadWordSeverity;
use App\Enums\PostVisibility;

/*
 * P7-01 : règles pures du fil — visibilité (communauté, abonné·es, moi, blocage), filtre de mots (casse, accents, mots
 * entiers), bulletin de sondage (remplacement, idempotence, choix unique) et RG-16 (seuil paramétrable).
 */

describe('PostAudience', function (): void {
    it('montre une publication publique à tout le réseau et la sienne même masquée', function (): void {
        expect(PostAudience::allows(PostVisibility::Public, isPublished: true, isAuthor: false))->toBeTrue()
            ->and(PostAudience::allows(PostVisibility::Me, isPublished: false, isAuthor: true))->toBeTrue();
    });

    it('réserve « abonné·es » aux abonné·es et « moi » à l’auteur·rice', function (): void {
        expect(PostAudience::allows(PostVisibility::Followers, true, false, followsAuthor: true))->toBeTrue()
            ->and(PostAudience::allows(PostVisibility::Followers, true, false, followsAuthor: false))->toBeFalse()
            ->and(PostAudience::allows(PostVisibility::Me, true, false, followsAuthor: true))->toBeFalse();
    });

    it('retire une publication non publiée ou d’un compte bloqué', function (): void {
        expect(PostAudience::allows(PostVisibility::Public, isPublished: false, isAuthor: false))->toBeFalse()
            ->and(PostAudience::allows(PostVisibility::Public, true, false, blocked: true))->toBeFalse();
    });

    it('traite une publication anonyme « abonné·es » comme « moi » (Q72 : suivre n’identifie personne)', function (): void {
        expect(PostAudience::allows(PostVisibility::Followers, true, false, followsAuthor: true, anonymous: true))->toBeFalse()
            ->and(PostAudience::allows(PostVisibility::Followers, true, true, anonymous: true))->toBeTrue()
            ->and(PostAudience::allows(PostVisibility::Public, true, false, anonymous: true))->toBeTrue();
    });
});

describe('ContentFilter', function (): void {
    $terms = [
        ['word' => 'tarée', 'severity' => BadWordSeverity::Block],
        ['word' => 'sale gouine', 'severity' => BadWordSeverity::Block],
        ['word' => 'arnaque', 'severity' => BadWordSeverity::Flag],
    ];

    it('bloque un terme sans tenir compte de la casse ni des accents', function () use ($terms): void {
        $verdict = (new ContentFilter($terms))->check('Tu es complètement TAREE');

        expect($verdict->isBlocked())->toBeTrue()->and($verdict->blockingTerms)->toBe(['tarée']);
    });

    it('compare sur mots entiers et ramène les espaces multiples des expressions', function () use ($terms): void {
        $filter = new ContentFilter($terms);

        expect($filter->check('Un acte notarée')->isClean())->toBeTrue()
            ->and($filter->check("quelle  sale\ngouine")->isBlocked())->toBeTrue();
    });

    it('laisse passer un terme « à surveiller » en le marquant', function () use ($terms): void {
        $verdict = (new ContentFilter($terms))->check('Attention à cette ARNAQUE !');

        expect($verdict->isBlocked())->toBeFalse()->and($verdict->isFlagged())->toBeTrue()
            ->and($verdict->flaggingTerms)->toBe(['arnaque']);
    });

    it('ne signale rien sur un texte vide', function () use ($terms): void {
        expect((new ContentFilter($terms))->check('', '   ')->isClean())->toBeTrue();
    });
});

describe('PollBallot', function (): void {
    it('ajoute un premier vote et compte une personne de plus', function (): void {
        $ballot = PollBallot::cast([], [2], [1, 2, 3], multiple: false);

        expect($ballot->toAdd)->toBe([2])->and($ballot->toRemove)->toBe([])
            ->and($ballot->votesDelta())->toBe(1)->and($ballot->votersDelta())->toBe(1);
    });

    it('remplace un vote sans changer le nombre de participant·es', function (): void {
        $ballot = PollBallot::cast([1], [3], [1, 2, 3], multiple: false);

        expect($ballot->toAdd)->toBe([3])->and($ballot->toRemove)->toBe([1])
            ->and($ballot->votesDelta())->toBe(0)->and($ballot->votersDelta())->toBe(0);
    });

    it('ne change rien quand les mêmes choix sont renvoyés (idempotent)', function (): void {
        $ballot = PollBallot::cast([3, 1], [1, 3, 3], [1, 2, 3], multiple: true);

        expect($ballot->changes())->toBeFalse()->and($ballot->choices)->toBe([1, 3]);
    });

    it('refuse aucun choix, une option étrangère ou deux choix en choix unique', function (array $requested, bool $multiple): void {
        PollBallot::cast([], $requested, [1, 2, 3], $multiple);
    })->with([
        'aucun choix' => [[], true],
        'option étrangère' => [[9], true],
        'deux choix en choix unique' => [[1, 2], false],
    ])->throws(InvalidArgumentException::class);

    it('retire tous les votes, sans effet si la personne n’avait pas voté', function (): void {
        $retract = PollBallot::retract([1, 2]);

        expect($retract->toRemove)->toBe([1, 2])->and($retract->votesDelta())->toBe(-2)->and($retract->votersDelta())->toBe(-1)
            ->and(PollBallot::retract([])->changes())->toBeFalse();
    });
});

describe('AutoHideRule (RG-16)', function (): void {
    it('masque au seuil de signalements en attente, pas avant', function (): void {
        $rule = new AutoHideRule(3);

        expect($rule->shouldHide(2, true))->toBeFalse()
            ->and($rule->shouldHide(3, true))->toBeTrue()
            ->and($rule->shouldHide(5, false))->toBeFalse();
    });

    it('se désactive avec un seuil à zéro', function (): void {
        expect((new AutoHideRule(0))->shouldHide(50, true))->toBeFalse();
    });
});

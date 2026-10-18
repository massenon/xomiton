<?php

declare(strict_types=1);

use App\Application\Import\PrototypeImporter;
use App\Models\AssistantKb;
use App\Models\Faq;
use Database\Seeders\ContentSeeder;

/*
 * Contenu semé pour l'accueil (P4-01) : 6 FAQ « accueil » (Q16), réponses ≤ 60 mots (FAQPage), vocabulaire (Q29, Q36),
 * typographie (’ et espaces insécables) sans doublon quand une base porte encore l'ancienne graphie, ni régression
 * quand `xomiton:import-prototype` repasse après le seeder (JSON du prototype : « 24h/24… vers un humain », vous, ').
 */

it('sème six FAQ d’accueil aux réponses courtes', function (): void {
    $this->seed(ContentSeeder::class);

    $home = Faq::onHome()->ordered()->get();

    expect($home)->toHaveCount(6)
        ->and($home->first()->question)->toBe("Qu’est-ce que Xomiton\u{00A0}?");

    foreach ($home as $faq) {
        expect(count(preg_split('/\s+/u', trim($faq->answer)) ?: []))->toBeLessThanOrEqual(60);
    }
});

it('ne sème ni « dénonciation » ni « 24 h/24 » sans réponse humaine sous 24 h', function (): void {
    $this->seed(ContentSeeder::class);

    foreach (Faq::all() as $faq) {
        $text = $faq->question.' '.$faq->answer;

        expect($text)->not->toMatch('/d[ée]nonciation/iu')
            ->not->toContain("'");

        if (preg_match('/24\s?h\s?\/\s?24/u', $text) === 1) {
            expect($text)->toContain('sous 24 h');
        }
    }
});

it('retrouve une question semée avec l’ancienne graphie au lieu de la dupliquer', function (): void {
    Faq::factory()->create(['question' => "Qu'est-ce que Xomiton ?", 'show_on_home' => true]);

    $this->seed(ContentSeeder::class);

    expect(Faq::count())->toBe(10)
        ->and(Faq::query()->where('question', "Qu'est-ce que Xomiton ?")->exists())->toBeFalse();
});

it('garde FAQ et base de l’assistant semées quand l’import du prototype repasse après le seeder', function (): void {
    $this->seed(ContentSeeder::class);
    $faqs = Faq::query()->orderBy('id')->pluck('answer', 'question')->all();
    $kb = AssistantKb::query()->orderBy('id')->pluck('answer', 'question')->all();
    AssistantKb::query()->update(['hits_count' => 4]);

    (new PrototypeImporter)->import(directory: base_path('../prototype_social/data'), only: ['faqs', 'assistant_kb']);

    expect(Faq::count())->toBe(10)
        ->and(Faq::query()->orderBy('id')->pluck('answer', 'question')->all())->toBe($faqs)
        ->and(Faq::onHome()->count())->toBe(6)
        ->and(AssistantKb::count())->toBe(6)
        ->and(AssistantKb::query()->orderBy('id')->pluck('answer', 'question')->all())->toBe($kb)
        ->and(AssistantKb::query()->where('hits_count', 4)->count())->toBe(6);

    foreach (Faq::all() as $faq) {
        expect($faq->question.' '.$faq->answer)->not->toContain("'")->not->toContain('vers un humain');
    }

    expect(Faq::query()->where('answer', 'like', '%24 h/24%')->firstOrFail()->answer)->toContain('sous 24 h')
        ->and(AssistantKb::query()->where('question', "Comment porter plainte\u{00A0}?")->firstOrFail()->answer)->toContain('Tu peux');
});

it('écrit les questions de l’assistant avec l’espace insécable et renomme l’ancienne graphie sans la dupliquer', function (): void {
    AssistantKb::factory()->create(['question' => 'Comment porter plainte ?', 'hits_count' => 3]);

    $this->seed(ContentSeeder::class);

    expect(AssistantKb::count())->toBe(6)
        ->and(AssistantKb::query()->where('question', "Comment porter plainte\u{00A0}?")->value('hits_count'))->toBe(3);

    foreach (AssistantKb::query()->pluck('question') as $question) {
        expect($question)->not->toMatch('/ [?!:;]/u');
    }
});

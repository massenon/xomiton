<?php

declare(strict_types=1);

use App\Enums\BadWordSeverity;
use App\Enums\CccKind;
use App\Enums\ContentStatus;
use App\Enums\ResourceKind;
use App\Models\AssistantConversation;
use App\Models\AssistantKb;
use App\Models\AssistantMessage;
use App\Models\BadWord;
use App\Models\Category;
use App\Models\CccTool;
use App\Models\Faq;
use App\Models\Law;
use App\Models\Resource;
use App\Models\Thematique;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Lot 3 « content » : une création par factory et une assertion métier par table
 * (unicité, cast chiffré, scope). Tourne avec RefreshDatabase (tests/Pest.php) à l'intégration des lots.
 */

describe('thematiques', function (): void {
    it('publie une fiche identifiée par son slug et caste statut, JSON et date', function (): void {
        $thematique = Thematique::factory()->create(['slug' => 'consentement']);

        expect($thematique->status)->toBe(ContentStatus::Published)
            ->and($thematique->status->label())->toBe('Publié')
            ->and($thematique->status->badge())->toBe('success')
            ->and($thematique->sections)->toBeArray()
            ->and($thematique->published_at)->not->toBeNull()
            ->and($thematique->category)->toBeInstanceOf(Category::class)
            ->and($thematique->getRouteKey())->toBe('consentement');

        expect(fn () => Thematique::factory()->create(['slug' => 'consentement']))->toThrow(QueryException::class);
    });

    it('ne montre au public que les fiches publiées à date passée et les supprime logiquement', function (): void {
        $published = Thematique::factory()->create();
        Thematique::factory()->draft()->create();
        Thematique::factory()->hidden()->create();
        Thematique::factory()->scheduled()->create();

        expect(Thematique::published()->count())->toBe(1)
            ->and($published->isPublished())->toBeTrue();

        $published->delete();

        expect(Thematique::published()->count())->toBe(0)
            ->and(Thematique::withTrashed()->count())->toBe(4);
    });

    it('refuse la suppression d’une catégorie encore utilisée', function (): void {
        $thematique = Thematique::factory()->create();

        expect(fn () => $thematique->category->delete())->toThrow(QueryException::class);
    });
});

describe('resources', function (): void {
    it('crée une ressource typée, filtre par nature et détache la thématique supprimée définitivement', function (): void {
        $thematique = Thematique::factory()->create();
        $pdf = Resource::factory()->pdf()->forThematique($thematique)->create();
        Resource::factory()->article()->create();
        Resource::factory()->draft()->create();

        expect($pdf->kind)->toBe(ResourceKind::Pdf)
            ->and($pdf->kind->hasFile())->toBeTrue()
            ->and($pdf->is_offline_available)->toBeTrue()
            ->and(Resource::published()->count())->toBe(2)
            ->and(Resource::news()->count())->toBe(1)
            ->and(Resource::ofKind(ResourceKind::Pdf)->offlineAvailable()->count())->toBe(1)
            ->and($thematique->resources()->count())->toBe(1);

        $thematique->forceDelete();

        expect($pdf->fresh()->thematique_id)->toBeNull();

        expect(fn () => Resource::factory()->create(['slug' => $pdf->slug]))->toThrow(QueryException::class);
    });
});

describe('faqs', function (): void {
    it('ordonne les questions et n’affiche sur l’accueil que les questions publiées', function (): void {
        Faq::factory()->onHome()->create(['position' => 2]);
        Faq::factory()->onHome()->unpublished()->create(['position' => 1]);
        Faq::factory()->create(['position' => 3]);

        expect(Faq::published()->count())->toBe(2)
            ->and(Faq::onHome()->count())->toBe(1)
            ->and(Faq::ordered()->first()->position)->toBe(1);
    });
});

describe('laws', function (): void {
    it('identifie une loi par slug unique et filtre par catégorie', function (): void {
        $law = Law::factory()->create(['slug' => 'loi-2011-26', 'category' => 'Violences']);
        Law::factory()->unpublished()->create(['category' => 'Violences']);

        expect(Law::published()->inCategory('Violences')->count())->toBe(1)
            ->and($law->getRouteKey())->toBe('loi-2011-26');

        expect(fn () => Law::factory()->create(['slug' => 'loi-2011-26']))->toThrow(QueryException::class);
    });
});

describe('ccc_tools', function (): void {
    it('distingue manuel, boîtes à images et BD par clé unique avec leurs planches', function (): void {
        $module = CccTool::factory()->create(['key' => 'm1']);
        $comic = CccTool::factory()->comic()->create();
        CccTool::factory()->imageBox()->unpublished()->create();

        expect($module->kind)->toBe(CccKind::Manual)
            ->and($module->chapters)->toHaveCount(4)
            ->and($comic->pagesCount())->toBe(4)
            ->and($comic->pages[0])->toHaveKeys(['image', 'caption', 'alt'])
            ->and(CccTool::published()->count())->toBe(2)
            ->and(CccTool::ofKind(CccKind::Comic)->count())->toBe(1)
            ->and(CccKind::ImageBox->label())->toBe('Boîte à images');

        expect(fn () => CccTool::factory()->create(['key' => 'm1']))->toThrow(QueryException::class);
    });
});

describe('assistant_kb', function (): void {
    it('stocke les mots-clés en JSON et apparie un message sans tenir compte de la casse ni des accents', function (): void {
        $entry = AssistantKb::factory()->withKeywords(['dépistage', 'vih'])->create();
        AssistantKb::factory()->inactive()->create();

        expect($entry->keywords)->toBe(['dépistage', 'vih'])
            ->and($entry->matches('Où faire un DEPISTAGE gratuit ?'))->toBeTrue()
            ->and($entry->matches('Comment porter plainte ?'))->toBeFalse()
            ->and(AssistantKb::active()->count())->toBe(1);

        $entry->recordHit();

        expect($entry->fresh()->hits_count)->toBe(1);
    });
});

describe('assistant_conversations', function (): void {
    it('appartient à un·e membre ou à une session anonyme et se signale pour relecture', function (): void {
        $user = User::factory()->create();
        $owned = AssistantConversation::factory()->create(['user_id' => $user->id]);
        $anonymous = AssistantConversation::factory()->anonymous('sess-123')->create();

        $anonymous->flag('Mots bloquants détectés');
        $anonymous->escalate();

        expect($owned->user->is($user))->toBeTrue()
            ->and(AssistantConversation::ownedBy($user)->count())->toBe(1)
            ->and(AssistantConversation::ownedBy(null, 'sess-123')->count())->toBe(1)
            ->and($anonymous->isAnonymous())->toBeTrue()
            ->and(AssistantConversation::flagged()->escalated()->count())->toBe(1);

        $user->forceDelete();

        expect($owned->fresh()->user_id)->toBeNull();
    });
});

describe('assistant_messages', function (): void {
    it('chiffre le corps, ne garde que created_at et disparaît avec la conversation', function (): void {
        $kb = AssistantKb::factory()->create();
        $question = AssistantMessage::factory()->create(['body' => 'Où faire un dépistage ?']);
        $answer = AssistantMessage::factory()->fromAssistant($kb)->create([
            'conversation_id' => $question->conversation_id,
            'body' => $kb->answer,
        ]);

        $raw = DB::table('assistant_messages')->where('id', $question->id)->value('body');

        expect($raw)->not->toBe('Où faire un dépistage ?')
            ->and($question->fresh()->body)->toBe('Où faire un dépistage ?')
            ->and($question->created_at)->not->toBeNull()
            ->and($question->isFromUser())->toBeTrue()
            ->and($answer->matchedKb->is($kb))->toBeTrue()
            ->and($question->conversation->messages()->count())->toBe(2)
            ->and(AssistantMessage::fromAssistant()->count())->toBe(1);

        $question->conversation->delete();

        expect(AssistantMessage::count())->toBe(0);
    });
});

describe('bad_words', function (): void {
    it('normalise et rend unique chaque terme, et détecte les mots entiers sans accents', function (): void {
        $blocked = BadWord::factory()->blocking()->create(['word' => '  Va Mourir ']);
        $flagged = BadWord::factory()->create(['word' => 'tarée']);
        BadWord::factory()->inactive()->create();

        expect($blocked->word)->toBe('va mourir')
            ->and($blocked->severity)->toBe(BadWordSeverity::Block)
            ->and($blocked->severity->badge())->toBe('danger')
            ->and($blocked->appearsIn('Toi, VA MOURIR !'))->toBeTrue()
            ->and($flagged->appearsIn('Elle est taree'))->toBeTrue()
            ->and($flagged->appearsIn('notarée'))->toBeFalse()
            ->and(BadWord::active()->count())->toBe(2)
            ->and(BadWord::active()->blocking()->count())->toBe(1);

        expect(fn () => BadWord::factory()->create(['word' => 'va mourir']))->toThrow(QueryException::class);
    });
});

describe('seeders', function (): void {
    it('charge les contenus du prototype et reste idempotent', function (): void {
        $this->seed(ContentSeeder::class);
        $this->seed(ContentSeeder::class);

        $vbg = Thematique::query()->where('slug', 'quest-ce-que-la-violence-basee-sur-le-genre-vbg')->firstOrFail();

        expect(Thematique::count())->toBe(29)
            ->and(Thematique::published()->count())->toBe(29)
            ->and($vbg->category->slug)->toBe('vbg')
            ->and($vbg->reading_minutes)->toBe(3)
            ->and($vbg->image_path)->toBe('assets/images/xomiton/hero-09.jpg')
            ->and($vbg->cover_path)->toBe('assets/images/xomiton/covers/th-1.jpg')
            ->and(array_column($vbg->sections, 'title'))->toBe(['Points clés', 'Que faire ?', 'Cadre légal', 'À retenir'])
            ->and($vbg->related()->count())->toBe(3)
            ->and($vbg->content_warning)->not->toBeNull()
            ->and(Law::count())->toBe(7)
            ->and(Faq::count())->toBe(10)
            ->and(Faq::onHome()->count())->toBe(8)
            ->and(Category::ofKind(Category::KIND_FAQ)->count())->toBe(4)
            ->and(Category::ofKind(Category::KIND_FAQ)->where('slug', 'signal')->value('label'))->toBe('Signalement & urgences')
            ->and(CccTool::count())->toBe(14)
            ->and(CccTool::ofKind(CccKind::Manual)->count())->toBe(5)
            ->and(CccTool::ofKind(CccKind::ImageBox)->count())->toBe(6)
            ->and(CccTool::ofKind(CccKind::Comic)->firstOrFail()->pagesCount())->toBe(4)
            ->and(AssistantKb::count())->toBe(6)
            ->and(AssistantKb::query()->where('question', 'Dépistage VIH / IST')->value('keywords'))->toBe(['vih', 'ist', 'dépistage', 'sida'])
            ->and(BadWord::count())->toBe(7)
            ->and(BadWord::blocking()->count())->toBe(3);

        // Une fiche du prototype supprimée logiquement est rétablie au prochain seed, sans doublon.
        $vbg->delete();
        $this->seed(ContentSeeder::class);

        expect(Thematique::count())->toBe(29)
            ->and($vbg->fresh()->trashed())->toBeFalse();
    });
});

<?php

declare(strict_types=1);

use App\Application\Content\ConversationsBd\ConversationsBdSource;
use App\Application\Content\ConversationsBd\ImportConversationsBd;
use App\Enums\BdConversationStatus;
use App\Models\BdBubble;
use App\Models\BdCharacter;
use App\Models\BdConversation;
use App\Models\BdPanel;
use App\Models\BdScene;
use Database\Seeders\ConversationsBdSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\File;

if (! function_exists('bdcNb')) {
    /** Typographie des fichiers de lang et des données : espace insécable avant « : ! ? ». */
    function bdcNb(string $text): string
    {
        return str_replace([' :', ' !', ' ?'], ["\u{00A0}:", "\u{00A0}!", "\u{00A0}?"], $text);
    }
}

/*
 * P20-05 : import idempotent des 12 conversations modèles (ImportConversationsBd, commande
 * `xomiton:conversations-bd:import`, ConversationsBdSeeder joué aussi par xomiton:install) — mêmes lignes après deux
 * exécutions, brouillon tant que les images manquent puis publication quand elles arrivent, contenu et statut retouchés
 * dans l'administration jamais réalignés, grammaire des bulles vérifiée avant toute écriture.
 */

/** Copie des données dans un dossier temporaire (les images des scènes de m01 pointent vers un fichier existant). */
function bdcTempData(bool $m01Images = false, ?callable $alter = null): string
{
    $dir = sys_get_temp_dir().'/bdc-'.uniqid();
    File::copyDirectory(database_path('seeders/data/content/conversations_bd'), $dir);

    if ($m01Images) {
        $scenes = json_decode((string) file_get_contents($dir.'/_scenes.json'), true);
        foreach ($scenes['scenes'] as &$scene) {
            if (str_starts_with($scene['key'], 'm01-')) {
                $scene['image'] = 'assets/images/xomiton/covers/og-default.jpg';
            }
        }
        file_put_contents($dir.'/_scenes.json', json_encode($scenes, JSON_UNESCAPED_UNICODE));
    }

    if ($alter !== null) {
        $alter($dir);
    }

    app()->instance(ConversationsBdSource::class, new ConversationsBdSource($dir));
    app()->forgetInstance(ImportConversationsBd::class);

    return $dir;
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/bdc-*') ?: [] as $dir) {
        File::deleteDirectory($dir);
    }
});

it('importe le casting, 60 scènes et 12 modèles de 5 cases, rejouable sans doublon', function (): void {
    $first = app(ImportConversationsBd::class)();
    $counts = [BdCharacter::query()->count(), BdScene::query()->count(), BdConversation::query()->count(), BdPanel::query()->count(), BdBubble::query()->count()];
    $second = app(ImportConversationsBd::class)();

    expect($first)->toMatchArray(['characters' => 23, 'scenes' => 60, 'conversations' => 12, 'created' => 12, 'drafts' => 12, 'published' => 0])
        ->and($second)->toMatchArray(['created' => 0, 'updated' => 12])
        ->and([BdCharacter::query()->count(), BdScene::query()->count(), BdConversation::query()->count(), BdPanel::query()->count(), BdBubble::query()->count()])->toBe($counts)
        ->and($counts[3])->toBe(60)
        ->and(BdBubble::query()->distinct()->pluck('type')->sort()->values()->all())->toBe(['cartouche', 'chuchote', 'cri', 'joie', 'message', 'parole', 'pensee', 'tremblee', 'voix_appareil']);

    $m01 = BdConversation::query()->where('model_key', 'm01')->firstOrFail();
    expect($m01->slug)->toBe('non-cest-non')
        ->and($m01->title)->toBe('Non, c’est non')
        ->and($m01->statusValue())->toBe(BdConversationStatus::Draft)
        ->and($m01->is_anonymous)->toBeTrue()
        ->and($m01->author_id)->toBeNull()
        ->and($m01->ulid)->not->toBeEmpty()
        ->and($m01->questions())->toHaveCount(3)
        ->and($m01->panels()->first()?->scene?->image_path)->toBe('assets/images/conversations-bd/non-cest-non/case-1.webp');

    // M09 : le commentaire insultant n'est jamais écrit (bulle masquée sans texte).
    expect(BdBubble::query()->where('is_masked', true)->pluck('text')->all())->toBe(['']);
    // Chaînes de bulles associées et superpositions conservées (M02 case 2, M04 case 1).
    expect(BdBubble::query()->whereNotNull('linked_to')->count())->toBeGreaterThanOrEqual(10)
        ->and(BdBubble::query()->where('is_overlapping', true)->count())->toBeGreaterThanOrEqual(5);
});

it('publie un modèle seulement si toutes ses images existent, puis le brouillon quand elles arrivent', function (): void {
    bdcTempData(m01Images: true);
    $report = app(ImportConversationsBd::class)();

    expect($report)->toMatchArray(['published' => 1, 'drafts' => 11]);
    $m01 = BdConversation::query()->where('model_key', 'm01')->firstOrFail();
    expect($m01->isPublished())->toBeTrue()->and($m01->published_at)->not->toBeNull();

    // m02 était un brouillon : ses images arrivent, l'import suivant le publie.
    bdcTempData(alter: function (string $dir): void {
        $scenes = json_decode((string) file_get_contents($dir.'/_scenes.json'), true);
        foreach ($scenes['scenes'] as &$scene) {
            if (str_starts_with($scene['key'], 'm01-') || str_starts_with($scene['key'], 'm02-')) {
                $scene['image'] = 'assets/images/xomiton/covers/og-default.jpg';
            }
        }
        file_put_contents($dir.'/_scenes.json', json_encode($scenes, JSON_UNESCAPED_UNICODE));
    });
    app(ImportConversationsBd::class)();
    expect(BdConversation::query()->where('model_key', 'm02')->firstOrFail()->isPublished())->toBeTrue();
});

it('ne réaligne jamais un contenu ni un statut retouchés dans l’administration', function (): void {
    bdcTempData(m01Images: true);
    app(ImportConversationsBd::class)();

    // Textes retouchés : laissés tels quels.
    $m01 = BdConversation::query()->where('model_key', 'm01')->firstOrFail();
    $m01->forceFill(['title' => 'Titre retouché', 'content_edited_at' => now()])->save();
    BdBubble::query()->whereIn('panel_id', $m01->panels()->pluck('id'))->where('position', 1)->update(['text' => 'Texte retouché']);
    // Dépubliée par l'équipe : jamais republiée par l'import.
    $m01->forceFill(['status' => BdConversationStatus::Draft, 'status_changed_at' => now()])->save();
    // Scène retouchée : laissée telle quelle.
    BdScene::query()->where('key', 'm02-case-1')->update(['description' => 'Description retouchée', 'content_edited_at' => now()]);

    $report = app(ImportConversationsBd::class)();

    $m01->refresh();
    expect($report['kept'])->toBe(1)
        ->and($m01->title)->toBe('Titre retouché')
        ->and($m01->statusValue())->toBe(BdConversationStatus::Draft)
        ->and(BdBubble::query()->where('text', 'Texte retouché')->count())->toBe(5)
        ->and(BdScene::query()->where('key', 'm02-case-1')->value('description'))->toBe('Description retouchée');
});

it('ne dépublie jamais une conversation publiée dont une image a disparu', function (): void {
    bdcTempData(m01Images: true);
    app(ImportConversationsBd::class)();
    bdcTempData();
    app(ImportConversationsBd::class)();

    expect(BdConversation::query()->where('model_key', 'm01')->firstOrFail()->isPublished())->toBeTrue();
});

it('refuse avant toute écriture un modèle qui enfreint la grammaire des bulles', function (): void {
    bdcTempData(alter: function (string $dir): void {
        $path = $dir.'/m01-non-cest-non.json';
        $data = json_decode((string) file_get_contents($path), true);
        $data['cases'][0]['bulles'][1]['texte'] = str_repeat('mot ', 21);
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE));
    });

    expect(fn () => app(ImportConversationsBd::class)())->toThrow(RuntimeException::class, 'm01 : '.bdcNb('Case 1, bulle 2 : 21 mots'));
    expect(BdConversation::query()->count())->toBe(0)->and(BdCharacter::query()->count())->toBe(0);
});

it('refuse une scène absente de la bibliothèque', function (): void {
    bdcTempData(alter: function (string $dir): void {
        $path = $dir.'/m03-tantie-il-faut-que-je-te-dise.json';
        $data = json_decode((string) file_get_contents($path), true);
        $data['cases'][2]['scene'] = 'inconnue';
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE));
    });

    expect(fn () => app(ImportConversationsBd::class)())->toThrow(RuntimeException::class, 'm03, case 3');
});

it('passe par la commande artisan et par le seeder de base', function (): void {
    $this->artisan('xomiton:conversations-bd:import')->expectsOutputToContain('12 conversations')->assertSuccessful();
    expect(DatabaseSeeder::REFERENCE_SEEDERS)->toContain(ConversationsBdSeeder::class);

    bdcTempData(alter: fn (string $dir) => File::delete($dir.'/_casting.json'));
    $this->artisan('xomiton:conversations-bd:import')->expectsOutputToContain('Import interrompu')->assertFailed();
});

it('respecte la grammaire dans chacun des 12 modèles livrés', function (): void {
    $source = new ConversationsBdSource;
    $grammar = new App\Application\Content\ConversationsBd\ConversationGrammar;

    foreach ($source->models() as $model) {
        $panels = array_map(fn (array $case): array => ['bubbles' => array_map(ImportConversationsBd::bubble(...), $case['bulles'])], $model['cases']);
        expect($grammar->check(['panels' => $panels]))->toBe([], $model['key'].' : aucun avertissement attendu');
    }
});

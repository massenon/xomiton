<?php

declare(strict_types=1);

use App\Application\Content\ConversationsBd\ConversationPdf;
use App\Application\Content\ConversationsBd\ImportConversationsBd;
use App\Enums\BdConversationOrigin;
use App\Enums\BdConversationStatus;
use App\Models\BdConversation;
use App\Models\BdScene;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Support\ConversationsBdFixtures;

/*
 * P20-09 : exports. PDF (serveur, dompdf, A4) : réponse, type MIME, nom de fichier, droits (publiée → tout le monde ;
 * création non publiée → son auteur·rice et la modération seulement), contenu (titre, texte des bulles en vrai texte posé
 * sur les formes vectorielles, message clé, questions d'animation, source et adresse), polices embarquées ; liens sur la
 * page publique, le mode séance et « Mes conversations ». PNG : produits dans le navigateur (Vitest) ; ici, les boutons
 * et les données qu'ils lisent.
 */

afterEach(function (): void {
    ConversationsBdFixtures::cleanup();
});

beforeEach(function (): void {
    // Images absentes : les modèles restent des brouillons (les tests publient eux-mêmes ce qu'il faut).
    ConversationsBdFixtures::source();
    $this->seed(RolesAndPermissionsSeeder::class);
    app(ImportConversationsBd::class)();
    BdConversation::query()->where('model_key', 'm01')->update(['status' => BdConversationStatus::Published->value, 'published_at' => now()]);
    BdScene::query()->update(['is_active' => true]);
});

/** Texte sélectionnable du PDF (poppler-utils), null si l'outil n'est pas installé. */
function bdxPdfText(string $pdf): ?string
{
    $binary = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));

    if ($binary === '') {
        return null;
    }

    $file = tempnam(sys_get_temp_dir(), 'bdx');
    file_put_contents($file, $pdf);
    $text = (string) shell_exec(escapeshellarg($binary).' -enc UTF-8 '.escapeshellarg($file).' - 2>/dev/null');
    unlink($file);

    return (string) preg_replace('/\s+/u', ' ', $text);
}

function bdxMemberCreation(User $author, BdConversationStatus $status = BdConversationStatus::Draft): BdConversation
{
    $conversation = BdConversation::factory()->withPanels(3)->create([
        'title' => 'Ma création privée',
        'status' => $status,
        'published_at' => $status === BdConversationStatus::Published ? now() : null,
        'position' => 0,
    ]);
    $conversation->forceFill(['origin' => BdConversationOrigin::Member->value, 'author_id' => $author->getKey(), 'model_key' => null])->save();

    return $conversation->refresh();
}

it('sert le PDF A4 d’une conversation publiée : type, nom, texte sélectionnable, questions et source', function (): void {
    $response = $this->get('/conversations-bd/non-cest-non/pdf')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('conversation-bd-non-cest-non.pdf')
        ->and($response->headers->get('X-Robots-Tag'))->toContain('noindex')
        ->and(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');

    $pdf = (string) $response->getContent();
    // Polices libres embarquées (sous-ensembles) : Liberation Sans et Montserrat.
    expect($pdf)->toContain('LiberationSans')->toContain('Montserrat');

    $text = bdxPdfText($pdf);
    if ($text !== null) {
        expect($text)->toContain('Non, c’est non')
            ->toContain('Il n’y aura personne.')
            ->toContain('Un non n’a pas besoin de justification')
            ->toContain('Comment dire non en une seule phrase')
            ->toContain(route('conversations-bd.show', ['slug' => 'non-cest-non']));
    }
});

it('pose le texte des bulles en vrai texte sur les formes vectorielles, avec questions, message clé et source', function (): void {
    $conversation = BdConversation::query()->where('slug', 'non-cest-non')->with(['panels.scene', 'panels.bubbles'])->firstOrFail();
    $service = app(ConversationPdf::class);
    $panel = app(App\Application\Content\ConversationsBd\ConversationsBdCatalog::class)->panels($conversation)[0];

    // Formes : SVG sans texte (vectoriel) ; textes : lignes positionnées en mm.
    $shapes = base64_decode(substr($service->shapes($panel['bubbles']), strlen('data:image/svg+xml;base64,')));
    expect($shapes)->toContain('<path')->not->toContain('<text');
    $texts = $service->texts($panel['bubbles']);
    expect($texts)->not->toBeEmpty()
        ->and(implode(' ', array_column($texts, 'html')))->toContain('Viens chez moi ce soir.')
        ->and($texts[0]['left'])->toBeGreaterThanOrEqual(0.0)
        ->and($texts[0]['width'])->toBeLessThanOrEqual(ConversationPdf::PANEL_MM + 1);
});

it('refuse le PDF public d’une conversation non publiée (404 neutre)', function (): void {
    $this->get('/conversations-bd/les-regles-du-groupe/pdf')->assertNotFound();
    $this->get('/conversations-bd/inconnue/pdf')->assertNotFound();

    $author = User::factory()->create();
    $author->syncRoles(['membre']);
    $draft = bdxMemberCreation($author);
    $this->get('/conversations-bd/'.$draft->slug.'/pdf')->assertNotFound();
});

it('réserve le PDF d’une création non publiée à son auteur·rice et à la modération', function (): void {
    $author = User::factory()->create();
    $author->syncRoles(['membre']);
    $draft = bdxMemberCreation($author);
    $url = '/mes-conversations-bd/'.$draft->ulid.'/pdf';

    $response = $this->actingAs($author)->get($url)->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    $text = bdxPdfText((string) $response->getContent());
    if ($text !== null) {
        expect($text)->toContain('Ma création privée')->toContain('Document privé')->toContain('Bonjour, case 1.');
    }

    $other = User::factory()->create();
    $other->syncRoles(['membre']);
    $this->actingAs($other)->get($url)->assertNotFound();

    $moderator = User::factory()->create();
    $moderator->syncRoles(['moderateur']);
    $this->actingAs($moderator)->get($url)->assertOk();
});

it('met les liens d’export sur la page publique, le mode séance et « Mes conversations »', function (): void {
    $html = (string) $this->get('/conversations-bd/non-cest-non')->assertOk()->getContent();
    expect($html)->toContain(route('conversations-bd.pdf', ['slug' => 'non-cest-non']))
        ->toContain('data-xm-bd-export="panels"')
        ->toContain('data-xm-bd-export="strip"')
        ->toContain('data-xm-bd-export-root="#conversation"');

    $seance = (string) $this->get('/conversations-bd/non-cest-non/seance')->assertOk()->getContent();
    expect($seance)->toContain(route('conversations-bd.pdf', ['slug' => 'non-cest-non']))->toContain(e(__('pages/conversations_bd.export.seance_pdf')));

    $author = User::factory()->create();
    $author->syncRoles(['membre']);
    $draft = bdxMemberCreation($author);
    $this->actingAs($author)->getJson('/api/v1/bd-conversations')->assertOk()
        ->assertJsonPath('data.0.pdf_url', route('mes-conversations-bd.pdf', ['ulid' => $draft->ulid]));
});

<?php

declare(strict_types=1);

use App\Application\Shared\MediaUploader;
use App\Enums\ContentStatus;
use App\Livewire\Admin\Content\Proposals as ProposalsScreen;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Resource;
use App\Models\ResourceProposal;
use App\Models\User;
use App\Notifications\TeamNotice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FeedFixtures as F;

/*
 * P18-12 : ressources proposées par les membres — formulaire (API multipart, page Vue), validation (type, lien https,
 * PDF réel), limiteur par compte et par jour (compté après validation), jamais publiques, file de validation
 * `/admin/contenu/propositions` (`resources.manage`) : accepter → ressource en brouillon (crédit facultatif), refuser →
 * motif, fichier supprimé ; notification de l'auteur·rice ; journal sans texte.
 */

const P18_PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->member = F::member();
    $this->admin = User::factory()->create();
    $this->admin->syncRoles(['admin']);
});

function p18Proposal(array $overrides = []): array
{
    return $overrides + [
        'title' => 'Guide des démarches après une agression',
        'kind' => 'lien',
        'summary' => 'Un guide clair qui explique les démarches pas à pas.',
        'external_url' => 'https://exemple.org/guide',
        'credit_author' => true,
    ];
}

it('enregistre une proposition par lien, jamais publique, journal sans texte', function (): void {
    F::as($this->member)->postJson('/api/v1/resource-proposals', p18Proposal())
        ->assertCreated()->assertJsonPath('data.status', 'proposee');

    $proposal = ResourceProposal::query()->sole();
    expect($proposal->user_id)->toBe($this->member->id)
        ->and($proposal->credit_author)->toBeTrue()
        ->and(Resource::query()->count())->toBe(0);

    $log = AuditLog::query()->where('action', 'resource_proposals.submitted')->sole();
    expect((string) $log->detail)->not->toContain('Guide');

    $this->getJson('/api/v1/ressources')->assertOk()->assertJsonMissing(['title' => 'Guide des démarches après une agression']);
});

it('valide le formulaire : type, lien https exigé pour un lien, PDF exigé pour un document', function (): void {
    F::as($this->member)->postJson('/api/v1/resource-proposals', p18Proposal(['kind' => 'video', 'external_url' => 'http://exemple.org', 'summary' => 'court']))
        ->assertUnprocessable()->assertJsonValidationErrors(['kind', 'external_url', 'summary']);
    F::as($this->member)->postJson('/api/v1/resource-proposals', p18Proposal(['external_url' => null]))
        ->assertUnprocessable()->assertJsonValidationErrors(['external_url']);
    F::as($this->member)->postJson('/api/v1/resource-proposals', p18Proposal(['kind' => 'pdf', 'external_url' => null]))
        ->assertUnprocessable()->assertJsonValidationErrors(['file']);

    $fake = UploadedFile::fake()->createWithContent('faux.pdf', 'pas un pdf');
    F::as($this->member)->post('/api/v1/resource-proposals', p18Proposal(['kind' => 'pdf', 'external_url' => null, 'file' => $fake]), ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['file']);

    expect(ResourceProposal::query()->count())->toBe(0);
});

it('stocke le PDF sur le disque privé', function (): void {
    Storage::fake(MediaUploader::privateDisk());
    $file = UploadedFile::fake()->createWithContent('guide.pdf', P18_PDF);

    F::as($this->member)->post('/api/v1/resource-proposals', p18Proposal(['kind' => 'pdf', 'external_url' => null, 'file' => $file]), ['Accept' => 'application/json'])
        ->assertCreated();

    $proposal = ResourceProposal::query()->sole();
    expect($proposal->file_path)->toStartWith('documents/');
    Storage::disk(MediaUploader::privateDisk())->assertExists((string) $proposal->file_path);
});

it('limite les propositions par compte et par jour, sans compter un formulaire refusé', function (): void {
    config(['xomiton.rate_limits.resource_proposals_per_day' => 2]);

    F::as($this->member)->postJson('/api/v1/resource-proposals', p18Proposal(['summary' => 'x']))->assertUnprocessable();
    F::as($this->member)->postJson('/api/v1/resource-proposals', p18Proposal())->assertCreated();
    F::as($this->member)->postJson('/api/v1/resource-proposals', p18Proposal())->assertCreated();
    F::as($this->member)->postJson('/api/v1/resource-proposals', p18Proposal())->assertTooManyRequests();

    F::as(F::member())->postJson('/api/v1/resource-proposals', p18Proposal())->assertCreated();
});

it('sert les options du formulaire et la page membre, refuse sans compte', function (): void {
    Category::factory()->create(['kind' => Category::KIND_RESOURCE, 'label' => 'Santé', 'is_active' => true]);
    $this->getJson('/api/v1/resource-proposals/options')->assertUnauthorized();
    $this->postJson('/api/v1/resource-proposals', p18Proposal())->assertUnauthorized();

    F::as($this->member)->getJson('/api/v1/resource-proposals/options')->assertOk()
        ->assertJsonPath('data.kinds.0.value', 'fiche')
        ->assertJsonFragment(['label' => 'Santé']);

    $this->actingAs($this->member)->get('/proposer-une-ressource')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Member/ResourceProposal')->has('options.kinds', 4));
});

it('accepte une proposition : ressource en brouillon avec crédit, notification, journal', function (): void {
    Notification::fake();
    F::as($this->member)->postJson('/api/v1/resource-proposals', p18Proposal())->assertCreated();
    $proposal = ResourceProposal::query()->sole();
    $this->actingAs($this->admin);

    Livewire::test(ProposalsScreen::class)
        ->call('open', $proposal->id)
        ->assertSet('proposalId', $proposal->id)
        ->set('credit', true)
        ->call('accept');

    $resource = Resource::query()->sole();
    expect($resource->status)->toBe(ContentStatus::Draft)
        ->and($resource->isPublished())->toBeFalse()
        ->and($resource->title)->toBe('Guide des démarches après une agression')
        ->and($resource->external_url)->toBe('https://exemple.org/guide')
        ->and((string) $resource->body)->toContain($this->member->pseudo)
        ->and($proposal->refresh()->proposalStatus()->value)->toBe('acceptee')
        ->and($proposal->resource_id)->toBe($resource->id)
        ->and(AuditLog::query()->where('action', 'resource_proposals.accepted')->count())->toBe(1);

    Notification::assertSentTo($this->member, TeamNotice::class, fn (TeamNotice $n): bool => $n->kind === TeamNotice::PROPOSAL_ACCEPTED);

    Livewire::test(ProposalsScreen::class)->call('open', $proposal->id)->call('accept');
    expect(Resource::query()->count())->toBe(1);
});

it('refuse avec un motif obligatoire, supprime le fichier et notifie', function (): void {
    Notification::fake();
    Storage::fake(MediaUploader::privateDisk());
    $file = UploadedFile::fake()->createWithContent('guide.pdf', P18_PDF);
    F::as($this->member)->post('/api/v1/resource-proposals', p18Proposal(['kind' => 'pdf', 'external_url' => null, 'file' => $file]), ['Accept' => 'application/json'])->assertCreated();
    $proposal = ResourceProposal::query()->sole();
    $path = (string) $proposal->file_path;
    $this->actingAs($this->admin);

    Livewire::test(ProposalsScreen::class)->call('open', $proposal->id)->call('refuse')->assertHasErrors('reason');
    Livewire::test(ProposalsScreen::class)->call('open', $proposal->id)->set('reason', 'Ressource déjà publiée.')->call('refuse')->assertHasNoErrors();

    expect($proposal->refresh()->proposalStatus()->value)->toBe('refusee')
        ->and($proposal->refusal_reason)->toBe('Ressource déjà publiée.')
        ->and($proposal->file_path)->toBeNull();
    Storage::disk(MediaUploader::privateDisk())->assertMissing($path);
    $log = AuditLog::query()->where('action', 'resource_proposals.refused')->sole();
    expect($log->detail)->toBeNull();
    Notification::assertSentTo($this->member, TeamNotice::class, fn (TeamNotice $n): bool => $n->kind === TeamNotice::PROPOSAL_REFUSED && $n->reason === 'Ressource déjà publiée.');
});

it('réserve la file de validation à resources.manage et verrouille la proposition ouverte', function (): void {
    $moderator = User::factory()->create();
    $moderator->syncRoles(['moderateur']);
    $this->actingAs($moderator)->get('/admin/contenu/propositions')->assertForbidden();

    $this->actingAs($this->admin)->get('/admin/contenu/propositions')->assertOk();
    expect(fn () => Livewire::test(ProposalsScreen::class)->set('proposalId', 1))->toThrow(Exception::class);
});

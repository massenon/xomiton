<?php

declare(strict_types=1);

use App\Application\Retention\PurgeExpiredReports;
use App\Application\Steering\Period;
use App\Application\Steering\Reports\ReportBuilder;
use App\Application\Steering\Reports\ReportCatalog;
use App\Application\Steering\Reports\ReportDataset;
use App\Application\Steering\Reports\ReportDelivery;
use App\Application\Steering\Reports\ReportDetail;
use App\Application\Steering\Reports\ReportSpec;
use App\Application\Steering\Reports\RunScheduledReports;
use App\Application\Steering\Reports\XlsxReportWriter;
use App\Enums\CaseKind;
use App\Enums\JobStatus;
use App\Enums\ReportFormat;
use App\Enums\ReportKind;
use App\Jobs\GenerateReport;
use App\Livewire\Admin\Reports\Index as Reports;
use App\Mail\TemplateMail;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\GeneratedReport;
use App\Models\MailLog;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Models\Zone;
use Carbon\CarbonImmutable;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/*
 * P10-07 : Rapports /admin/rapports — permissions, aperçu, génération par morceaux (Excel en flux, PDF, Word), registre
 * détaillé pseudonymisé sans colonne chiffrée, limites (PDF refusé au-delà de la limite de lignes : 422), masquage des
 * petits effectifs, historique (vider), téléchargement signé lié au compte, échec de job, planification récurrente et
 * commande reports:generate (envoi du fichier par e-mail, idempotence).
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(EmailTemplatesSeeder::class);
    $this->travelTo(now()->setDate(2026, 10, 14)->setTime(10, 0));
    Storage::fake('local');
});

function reportsAdmin(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

/** Dossiers de septembre 2026 pour les rapports. */
function septemberCases(int $count, array $attributes = []): void
{
    $pair = User::factory()->create();
    $zone = Zone::factory()->create(['name' => 'Atlantique '.fake()->unique()->numberBetween(1, 9999)]);

    for ($i = 0; $i < $count; $i++) {
        CaseRecord::factory()->vbg()->create(['zone_id' => $zone->id, 'collected_by' => $pair->id, 'created_at' => '2026-09-'.str_pad((string) (1 + $i % 27), 2, '0', STR_PAD_LEFT).' 10:00:00', 'occurred_on' => '2026-09-01'] + $attributes);
    }
}

function wizard(mixed $component, array $state = []): mixed
{
    $component->set('kind', 'personnalise')->set('from', '2026-09-01')->set('until', '2026-09-30')->set('title', 'Rapport de septembre');

    foreach ($state as $key => $value) {
        $component->set($key, $value);
    }

    return $component;
}

/** @return list<list<mixed>> lignes de la feuille $sheet d'un classeur. */
function sheetRows(string $path, string $sheet): array
{
    $reader = new XlsxReader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $candidate) {
        if ($candidate->getName() !== $sheet) {
            continue;
        }

        foreach ($candidate->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }

    $reader->close();

    return $rows;
}

/** @return list<string> */
function sheetNames(string $path): array
{
    $reader = new XlsxReader;
    $reader->open($path);
    $names = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        $names[] = $sheet->getName();
    }

    $reader->close();

    return $names;
}

it('sert l’écran à l’admin et au superadmin, le refuse au modérateur et au membre', function (): void {
    foreach (['admin', 'superadmin'] as $role) {
        $html = $this->actingAs(reportsAdmin($role))->get(route('admin.rapports'))
            ->assertOk()->assertSeeLivewire(Reports::class)->assertSee('Paramètres du rapport')->assertSee('Planification automatique')->getContent();
        expect($html)->toMatch('/data-xm-nav="rapports"\s+aria-current="page"/');
    }

    $this->actingAs(reportsAdmin('moderateur'))->get(route('admin.rapports'))->assertForbidden();
    $this->actingAs(reportsAdmin('membre'))->get(route('admin.rapports'))->assertForbidden();
});

it('remplit la période et le titre d’un type périodique, comme le prototype', function (): void {
    $this->actingAs(reportsAdmin());

    Livewire::test(Reports::class)->assertSet('from', '2026-01-01')->assertSet('until', '2026-12-31')->assertSet('title', 'Rapport annuel')
        ->set('kind', 'trimestriel')->assertSet('from', '2026-10-01')->assertSet('until', '2026-12-31')->assertSet('title', 'Rapport trimestriel')
        ->set('kind', 'semestriel')->assertSet('from', '2026-07-01')->assertSet('until', '2026-12-31')
        ->set('kind', 'mensuel')->assertSet('from', '2026-10-01')->assertSet('until', '2026-10-31')
        ->set('from', '2026-10-05')->assertSet('kind', 'personnalise');
});

it('calcule l’aperçu sur la période et masque les effectifs de 1 à 4', function (): void {
    $this->actingAs(reportsAdmin());
    septemberCases(6);
    Alert::factory()->sos()->count(3)->create(['created_at' => '2026-09-05 10:00:00']);
    Alert::factory()->count(5)->create(['created_at' => '2026-09-06 10:00:00']);

    $component = wizard(Livewire::test(Reports::class))->call('preview')->assertHasNoErrors();
    $dataset = $component->viewData('preview');

    expect($dataset->title)->toBe('Rapport de septembre')
        ->and($dataset->periodLabel)->toBe('01/09/2026 – 30/09/2026');

    $kpis = collect($dataset->kpis)->pluck('value', 'label');
    expect($kpis['Dossiers enregistrés'])->toBe(6)
        // 3 SOS : effectif masqué ; 8 alertes au total (3 SOS + 5) : affiché.
        ->and($kpis['SOS'])->toBeNull()
        ->and($kpis['Alertes reçues'])->toBe(8);

    $alerts = collect($dataset->tables)->firstWhere('title', 'Alertes et SOS');
    // Une cellule masquée : pas de ligne « Total », et le total de la ligne SOS est lui aussi masqué.
    expect(collect($alerts['rows'])->pluck(0)->all())->not->toContain('Total')
        ->and($dataset->hasMasked)->toBeTrue();
    $component->assertSee('données masquées');
});

it('ne laisse pas retrouver un effectif masqué par un total ou un pourcentage', function (): void {
    septemberCases(20);
    $zoneB = Zone::factory()->create(['name' => 'Borgou']);
    CaseRecord::factory()->vdh()->count(3)->create(['zone_id' => $zoneB->id, 'collected_by' => User::factory()->create()->id, 'created_at' => '2026-09-12 10:00:00']);

    $spec = new ReportSpec(ReportKind::Custom, 'Test', CarbonImmutable::create(2026, 9, 1), CarbonImmutable::create(2026, 9, 30), ['departement'], ['registre'], false, ReportFormat::Xlsx);
    $dataset = app(ReportBuilder::class)->build($spec);

    $register = collect($dataset->tables)->firstWhere('title', 'Registres VBG / VDH');
    $byZone = collect($dataset->tables)->firstWhere('title', __('steering.reports.dimension_by', ['dimension' => mb_strtolower(__('steering.reports.dimensions.departement'))]));

    // VDH : 3 dossiers = masqués ; ni pourcentage, ni total, ni différence avec VBG (20 + 3 = 23) ne permettent de les déduire.
    // Aucun total n'est publié ici, mais il l'est ailleurs (chiffres clés, autre tableau) : les deux cellules du groupe sont masquées.
    expect($register['rows'])->toBe([[CaseKind::Vbg->label(), null, null], [CaseKind::Vdh->label(), null, null]])
        ->and(collect($byZone['rows'])->pluck(0)->all())->not->toContain('Total');

    $borgou = collect($byZone['rows'])->first(fn (array $row): bool => $row[0] === 'Borgou');
    expect($borgou)->toBe(['Borgou', 0, null, null]);
});

it('génère un Excel par morceaux : registre pseudonymisé, aucun récit ni note, lignes dans l’ordre', function (): void {
    config(['xomiton.reports.rows_per_job' => 2]);
    $admin = reportsAdmin();
    $this->actingAs($admin);
    septemberCases(7, ['notes' => 'NOTE-CONFIDENTIELLE-à-ne-jamais-exporter']);

    Bus::fake();
    wizard(Livewire::test(Reports::class), ['fileFormat' => 'xlsx', 'detail' => true])->call('generate')->assertHasNoErrors();
    // 7 dossiers à 2 par job : 4 morceaux enchaînés.
    Bus::assertChained([GenerateReport::class, GenerateReport::class, GenerateReport::class, GenerateReport::class]);

    $report = GeneratedReport::query()->latest('id')->firstOrFail();
    expect($report->status)->toBe(JobStatus::Pending)->and($report->filters['parts'])->toBe(4)->and($report->rows_count)->toBe(7);
});

it('assemble le fichier Excel après tous les morceaux et supprime les fichiers temporaires', function (): void {
    config(['xomiton.reports.rows_per_job' => 3]);
    $admin = reportsAdmin();
    $this->actingAs($admin);
    septemberCases(7, ['notes' => 'NOTE-CONFIDENTIELLE-à-ne-jamais-exporter']);

    wizard(Livewire::test(Reports::class), ['fileFormat' => 'xlsx', 'detail' => true, 'dimensions' => ['departement', 'sexe']])->call('generate')->assertHasNoErrors();

    $report = GeneratedReport::query()->latest('id')->firstOrFail();
    expect($report->status)->toBe(JobStatus::Done)
        ->and($report->rows_count)->toBe(7)
        ->and($report->file_size)->toBeGreaterThan(0)
        ->and(Storage::disk('local')->exists($report->file_path))->toBeTrue()
        ->and(Storage::disk('local')->allFiles('reports/tmp'))->toBe([])
        ->and($report->expires_at->isSameDay(now()->addDays(30)))->toBeTrue();

    $path = Storage::disk('local')->path($report->file_path);
    $names = sheetNames($path);
    expect($names)->toContain('Synthèse', 'Registre détaillé')->and($names[0])->toBe('Synthèse');

    $rows = sheetRows($path, 'Registre détaillé');
    expect($rows)->toHaveCount(8)
        ->and($rows[0])->toBe(['Code du dossier', 'Registre', 'Date des faits', 'Type de violence', 'Statut', __('steering.reports.dimensions.departement'), 'Sexe'])
        ->and(collect($rows)->skip(1)->pluck(0)->unique()->count())->toBe(7);

    // Ni récit, ni notes, ni position dans aucune feuille.
    $all = collect($names)->flatMap(fn (string $name): array => sheetRows($path, $name))->flatten()->implode('|');
    expect($all)->not->toContain('NOTE-CONFIDENTIELLE')->and($all)->not->toContain('Latitude')->and($all)->not->toContain('Contact');
    expect(AuditLog::query()->where('action', 'report.request')->where('user_id', $admin->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'report.generated')->exists())->toBeTrue();
});

it('génère un PDF et un document Word', function (): void {
    $this->actingAs(reportsAdmin());
    septemberCases(6);

    wizard(Livewire::test(Reports::class), ['fileFormat' => 'pdf', 'detail' => true])->call('generate')->assertHasNoErrors();
    wizard(Livewire::test(Reports::class), ['fileFormat' => 'docx', 'title' => 'Rapport Word'])->call('generate')->assertHasNoErrors();

    $pdf = GeneratedReport::query()->where('format', 'pdf')->firstOrFail();
    $docx = GeneratedReport::query()->where('format', 'docx')->firstOrFail();
    expect($pdf->status)->toBe(JobStatus::Done)
        ->and(Storage::disk('local')->get($pdf->file_path))->toStartWith('%PDF')
        ->and($docx->status)->toBe(JobStatus::Done);

    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($docx->file_path));
    expect($zip->getFromName('word/document.xml'))->toContain('Rapport Word')->toContain('Chiffres clés');
    $zip->close();
});

it('refuse en 422 un PDF ou un Word au-delà de la limite de lignes, accepte Excel, refuse au-delà du maximum', function (): void {
    config(['xomiton.exports.pdf_max_rows' => 3, 'xomiton.reports.max_rows' => 6]);
    $this->actingAs(reportsAdmin());
    septemberCases(5);

    wizard(Livewire::test(Reports::class), ['fileFormat' => 'pdf', 'detail' => true])->call('generate')
        ->assertHasErrors(['fileFormat'])->assertSee('choisissez Excel');
    wizard(Livewire::test(Reports::class), ['fileFormat' => 'docx', 'detail' => true])->call('generate')->assertHasErrors(['fileFormat']);
    expect(GeneratedReport::query()->count())->toBe(0);

    wizard(Livewire::test(Reports::class), ['fileFormat' => 'xlsx', 'detail' => true])->call('generate')->assertHasNoErrors();
    expect(GeneratedReport::query()->done()->count())->toBe(1);

    // Sans registre détaillé, un PDF reste possible quel que soit le nombre de dossiers.
    wizard(Livewire::test(Reports::class), ['fileFormat' => 'pdf', 'detail' => false])->call('generate')->assertHasNoErrors();

    septemberCases(2);
    wizard(Livewire::test(Reports::class), ['fileFormat' => 'xlsx', 'detail' => true])->call('generate')->assertHasErrors(['detail']);
});

it('refuse une période inversée, trop longue, un destinataire invalide et plus de trois rapports en cours', function (): void {
    $admin = reportsAdmin();
    $this->actingAs($admin);

    wizard(Livewire::test(Reports::class), ['from' => '2026-09-30', 'until' => '2026-09-01'])->call('generate')->assertHasErrors(['until']);
    wizard(Livewire::test(Reports::class), ['from' => '2018-01-01', 'until' => '2026-09-01'])->call('generate')->assertHasErrors(['until']);
    wizard(Livewire::test(Reports::class), ['from' => '2026-02-31'])->call('generate')->assertHasErrors(['from']);
    wizard(Livewire::test(Reports::class), ['recipients' => 'pas-une-adresse'])->call('generate')->assertHasErrors(['recipients']);
    wizard(Livewire::test(Reports::class), ['fileFormat' => 'apercu'])->call('generate')->assertHasErrors(['fileFormat']);

    Bus::fake();
    foreach (range(1, 3) as $i) {
        wizard(Livewire::test(Reports::class))->call('generate')->assertHasNoErrors();
    }
    wizard(Livewire::test(Reports::class))->call('generate')->assertHasErrors(['fileFormat'])->assertSee('3 rapports en préparation');
    expect(GeneratedReport::query()->count())->toBe(3);
});

it('réserve le registre détaillé à cases.export', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(['admin.access', 'reports.generate']);
    $this->actingAs($user);
    septemberCases(5);

    Livewire::test(Reports::class)->assertSee('Réservé aux comptes autorisés à exporter les dossiers')
        ->set('detail', true)->call('generate')->assertForbidden();
    expect(GeneratedReport::query()->count())->toBe(0);

    wizard(Livewire::test(Reports::class), ['detail' => false])->call('generate')->assertHasNoErrors();
    expect(GeneratedReport::query()->done()->count())->toBe(1);
});

it('marque un rapport en échec avec un message sans donnée et supprime les morceaux', function (): void {
    $report = GeneratedReport::factory()->create(['status' => JobStatus::Running, 'format' => ReportFormat::Xlsx, 'filters' => ['sections' => [], 'detail' => true, 'parts' => 2]]);
    Storage::disk('local')->put('reports/tmp/'.$report->id.'/part-0.ndjson', "[\"X\"]\n");

    (new GenerateReport($report->id, 0, 2))->failed(new RuntimeException('Disque plein : dossier DOS-1234'));

    $report->refresh();
    expect($report->status)->toBe(JobStatus::Failed)
        ->and($report->error)->toBe('La génération a échoué. Relancez le rapport ou contactez l’équipe technique.')
        ->and($report->error)->not->toContain('DOS-1234')
        ->and(Storage::disk('local')->exists('reports/tmp/'.$report->id.'/part-0.ndjson'))->toBeFalse();

    // Un job rejoué sur un rapport terminé ne fait rien.
    (new GenerateReport($report->id, 0, 1))->handle(app(App\Application\Steering\Reports\FinishReport::class), app(ReportDetail::class));
    expect($report->fresh()->status)->toBe(JobStatus::Failed);
});

it('télécharge par lien signé lié au compte, journalise, et refuse tout autre accès', function (): void {
    $admin = reportsAdmin();
    $other = reportsAdmin('superadmin');
    $this->actingAs($admin);
    septemberCases(6);
    wizard(Livewire::test(Reports::class), ['fileFormat' => 'xlsx'])->call('generate');
    $report = GeneratedReport::query()->latest('id')->firstOrFail();

    $html = Livewire::test(Reports::class)->html();
    preg_match('#href="([^"]*rapports/'.$report->id.'/telecharger[^"]*)"#', $html, $matches);
    $link = html_entity_decode($matches[1] ?? '');
    expect($link)->not->toBe('');

    $response = $this->get($link);
    $response->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect($response->headers->get('content-disposition'))->toContain('rapport-de-septembre_2026-09-01_2026-09-30.xlsx')
        ->and(AuditLog::query()->where('action', 'report.downloaded')->where('user_id', $admin->id)->exists())->toBeTrue();

    // Lien non signé, lien d'une autre personne, rapport expiré ou en cours : refus.
    $this->get(route('admin.rapports.telecharger', ['report' => $report->id, 'u' => $admin->id]))->assertForbidden();
    $this->actingAs($other)->get($link)->assertForbidden();
    $this->actingAs($admin);
    $report->forceFill(['expires_at' => now()->subMinute()])->save();
    $this->get($link)->assertNotFound();
    $report->forceFill(['expires_at' => now()->addDay(), 'status' => JobStatus::Running])->save();
    $this->get($link)->assertNotFound();

    $this->actingAs(reportsAdmin('moderateur'))->get($link)->assertForbidden();
});

it('vide l’historique en gardant les rapports en cours, supprime les fichiers et journalise', function (): void {
    $admin = reportsAdmin();
    $this->actingAs($admin);
    septemberCases(6);
    wizard(Livewire::test(Reports::class), ['fileFormat' => 'xlsx'])->call('generate');
    $done = GeneratedReport::query()->done()->firstOrFail();
    $running = GeneratedReport::factory()->create(['status' => JobStatus::Running]);
    $failed = GeneratedReport::factory()->create(['status' => JobStatus::Failed]);

    expect(Storage::disk('local')->exists($done->file_path))->toBeTrue();

    Livewire::test(Reports::class)->call('clearHistory')->assertDispatched('toast');

    expect(GeneratedReport::query()->pluck('id')->all())->toBe([$running->id])
        ->and(Storage::disk('local')->exists($done->file_path))->toBeFalse()
        ->and(AuditLog::query()->where('action', 'report.history_cleared')->exists())->toBeTrue();

    Livewire::test(Reports::class)->call('deleteReport', $running->id)->assertDispatched('toast');
    expect(GeneratedReport::query()->count())->toBe(1);
    expect($failed->exists())->toBeTrue();
});

it('envoie le fichier en pièce jointe aux destinataires et journalise chaque envoi', function (): void {
    Mail::fake();
    $this->actingAs(reportsAdmin());
    septemberCases(6);

    wizard(Livewire::test(Reports::class), ['fileFormat' => 'xlsx', 'recipients' => "Direction@Exemple.org, bailleur@exemple.org\ndirection@exemple.org"])->call('generate')->assertHasNoErrors();

    $report = GeneratedReport::query()->firstOrFail();
    expect($report->recipients)->toBe(['direction@exemple.org', 'bailleur@exemple.org']);
    Mail::assertSent(TemplateMail::class, 2);
    Mail::assertSent(TemplateMail::class, fn (TemplateMail $mail): bool => $mail->hasTo('direction@exemple.org')
        && str_contains($mail->htmlBody, 'Rapport de septembre')
        && count($mail->diskAttachments) === 1
        && $mail->diskAttachments[0]['name'] === 'rapport-de-septembre_2026-09-01_2026-09-30.xlsx');
    expect(MailLog::query()->where('template_slug', 'rapport-genere')->where('ok', true)->count())->toBe(2)
        ->and($report->fresh()->error)->toBeNull();

    // « Renvoyer par e-mail » rejoue l'envoi.
    Livewire::test(Reports::class)->call('resend', $report->id)->assertDispatched('toast');
    Mail::assertSent(TemplateMail::class, 4);
});

it('signale un envoi impossible sans perdre le fichier', function (): void {
    config(['mail.default' => 'outbox']);
    $this->actingAs(reportsAdmin());
    septemberCases(6);

    wizard(Livewire::test(Reports::class), ['fileFormat' => 'xlsx', 'recipients' => 'direction@exemple.org'])->call('generate')->assertHasNoErrors();

    $report = GeneratedReport::query()->firstOrFail();
    expect($report->status)->toBe(JobStatus::Done)
        ->and($report->error)->toContain('aucun service d’e-mail')
        ->and(Storage::disk('local')->exists($report->file_path))->toBeTrue();
});

it('planifie un rapport récurrent, valide les champs, met en pause et supprime', function (): void {
    $admin = reportsAdmin();
    $this->actingAs($admin);

    Livewire::test(Reports::class)
        ->set('sKind', 'mensuel')->set('sFrequency', 'mensuel')->set('sDay', 5)->set('sFormats', ['pdf', 'xlsx'])->set('sRecipients', 'direction@exemple.org; bailleur@exemple.org')
        ->call('saveSchedule')->assertHasNoErrors()->assertDispatched('toast');

    $schedule = ReportSchedule::query()->firstOrFail();
    expect($schedule->kind)->toBe(ReportKind::Monthly)
        ->and($schedule->day_of_period)->toBe(5)
        ->and($schedule->formats)->toBe(['pdf', 'xlsx'])
        ->and($schedule->recipients)->toBe(['direction@exemple.org', 'bailleur@exemple.org'])
        ->and($schedule->next_run_at->toDateString())->toBe('2026-11-05')
        ->and($schedule->created_by)->toBe($admin->id);

    $invalid = fn (array $state) => Livewire::test(Reports::class)->set('sRecipients', 'a@exemple.org')->tap(function ($c) use ($state): void {
        foreach ($state as $key => $value) {
            $c->set($key, $value);
        }
    })->call('saveSchedule');

    $invalid(['sDay' => 29])->assertHasErrors(['sDay']);
    $invalid(['sDay' => 0])->assertHasErrors(['sDay']);
    $invalid(['sFormats' => []])->assertHasErrors(['sFormats']);
    $invalid(['sFormats' => ['apercu']])->assertHasErrors();
    $invalid(['sFrequency' => 'quotidien'])->assertHasErrors(['sFrequency']);
    $invalid(['sKind' => 'bailleur'])->assertHasErrors(['sKind']);
    Livewire::test(Reports::class)->set('sRecipients', '')->call('saveSchedule')->assertHasErrors(['sRecipients']);
    Livewire::test(Reports::class)->set('sRecipients', 'mauvaise')->call('saveSchedule')->assertHasErrors(['sRecipients']);
    expect(ReportSchedule::query()->count())->toBe(1);

    Livewire::test(Reports::class)->call('toggleSchedule', $schedule->id);
    expect($schedule->fresh()->is_active)->toBeFalse();
    Livewire::test(Reports::class)->call('toggleSchedule', $schedule->id);
    expect($schedule->fresh()->is_active)->toBeTrue();
    Livewire::test(Reports::class)->call('deleteSchedule', $schedule->id);
    expect(ReportSchedule::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'like', 'report.schedule_%')->count())->toBe(4);
});

it('accepte une planification hebdomadaire sur le jour de la semaine', function (): void {
    $this->actingAs(reportsAdmin());

    Livewire::test(Reports::class)->set('sFrequency', 'hebdomadaire')->set('sDay', 8)->set('sRecipients', 'a@exemple.org')->call('saveSchedule')->assertHasErrors(['sDay']);
    Livewire::test(Reports::class)->set('sFrequency', 'hebdomadaire')->set('sDay', 1)->set('sRecipients', 'a@exemple.org')->call('saveSchedule')->assertHasNoErrors();

    // 14/10/2026 est un mercredi : le prochain lundi est le 19/10.
    expect(ReportSchedule::query()->firstOrFail()->next_run_at->toDateString())->toBe('2026-10-19');

    $weekly = new ReportSchedule(['frequency' => 'hebdomadaire', 'day_of_period' => 3]);
    expect($weekly->computeNextRunAt(CarbonImmutable::create(2026, 10, 14, 9))->toDateString())->toBe('2026-10-21')
        ->and($weekly->computeNextRunAt(CarbonImmutable::create(2026, 10, 14, 0))->toDateString())->toBe('2026-10-21');
});

it('couvre la dernière période entière de chaque fréquence', function (): void {
    $now = CarbonImmutable::create(2026, 10, 14, 4, 15);

    expect(ReportCatalog::previousPeriod('hebdomadaire', $now)->label())->toBe('05/10/2026 – 11/10/2026')
        ->and(ReportCatalog::previousPeriod('mensuel', $now)->label())->toBe('09/2026')
        ->and(ReportCatalog::previousPeriod('trimestriel', $now)->label())->toBe('01/07/2026 – 30/09/2026')
        ->and(ReportCatalog::previousPeriod('semestriel', $now)->label())->toBe('01/01/2026 – 30/06/2026')
        ->and(ReportCatalog::previousPeriod('annuel', $now)->label())->toBe('01/01/2025 – 31/12/2025')
        ->and(ReportCatalog::previousPeriod('semestriel', CarbonImmutable::create(2026, 2, 3))->label())->toBe('01/07/2025 – 31/12/2025');
});

it('génère les rapports planifiés échus, les envoie par e-mail, et ne recommence pas', function (): void {
    Mail::fake();
    septemberCases(6);
    $creator = reportsAdmin();
    $due = ReportSchedule::factory()->create([
        'kind' => ReportKind::Monthly, 'frequency' => 'mensuel', 'day_of_period' => 1, 'formats' => ['pdf', 'xlsx'], 'recipients' => ['direction@exemple.org'],
        'dimensions' => ['departement'], 'is_active' => true, 'next_run_at' => now()->subHour(), 'created_by' => $creator->id,
    ]);
    $paused = ReportSchedule::factory()->create(['is_active' => false, 'next_run_at' => now()->subDay()]);
    $future = ReportSchedule::factory()->create(['is_active' => true, 'next_run_at' => now()->addDay()]);

    $this->artisan('reports:generate')->expectsOutputToContain('1 planification échue(s) : 2 rapports enfilé(s).')->assertSuccessful();

    $reports = GeneratedReport::query()->orderBy('id')->get();
    expect($reports)->toHaveCount(2)
        ->and($reports->pluck('format')->map->value->all())->toBe(['pdf', 'xlsx'])
        ->and($reports->every(fn ($r): bool => $r->status === JobStatus::Done))->toBeTrue()
        ->and($reports[0]->period_start->toDateString())->toBe('2026-09-01')
        ->and($reports[0]->period_end->toDateString())->toBe('2026-09-30')
        ->and($reports[0]->title)->toBe('Rapport mensuel — 09/2026')
        ->and($reports[0]->generated_by)->toBe($creator->id)
        ->and($reports[0]->filters['detail'])->toBeFalse()
        ->and($due->fresh()->last_run_at)->not->toBeNull()
        ->and($due->fresh()->next_run_at->toDateString())->toBe('2026-11-01')
        ->and($paused->fresh()->last_run_at)->toBeNull()
        ->and($future->fresh()->last_run_at)->toBeNull();

    Mail::assertSent(TemplateMail::class, 2);
    Mail::assertSent(TemplateMail::class, fn (TemplateMail $mail): bool => $mail->hasTo('direction@exemple.org') && count($mail->diskAttachments) === 1);

    // Second passage le même jour : rien n'est regénéré.
    $this->artisan('reports:generate')->expectsOutputToContain('0 planification échue(s)')->assertSuccessful();
    expect(GeneratedReport::query()->count())->toBe(2);
});

it('n’ajoute jamais le registre détaillé à un rapport planifié', function (): void {
    septemberCases(6);
    ReportSchedule::factory()->create(['formats' => ['xlsx'], 'recipients' => ['a@exemple.org'], 'is_active' => true, 'next_run_at' => now()->subMinute()]);

    app(RunScheduledReports::class)->run();

    $report = GeneratedReport::query()->firstOrFail();
    expect($report->filters['detail'])->toBeFalse()
        ->and($report->rows_count)->toBeNull()
        ->and(sheetNames(Storage::disk('local')->path($report->file_path)))->not->toContain('Registre détaillé');
});

it('lit le registre détaillé par tranches d’identifiants contiguës', function (): void {
    septemberCases(7);
    $detail = app(ReportDetail::class);
    $period = Period::month(2026, 9);

    $bounds = $detail->boundaries($period, 3);
    expect($bounds)->toHaveCount(3)->and($bounds[0][0])->toBe(0)->and($bounds[2][1])->toBeNull()
        ->and($bounds[1][0])->toBe($bounds[0][1])->and($bounds[2][0])->toBe($bounds[1][1]);

    $codes = [];
    foreach ($bounds as [$after, $until]) {
        foreach ($detail->rows($period, [], $after, $until) as $row) {
            $codes[] = $row[0];
        }
    }

    expect($codes)->toHaveCount(7)->and(array_unique($codes))->toHaveCount(7)
        ->and($detail->boundaries($period->label() === '' ? $period : Period::month(2025, 1), 3))->toBe([[0, null]]);
});

it('écrit un titre ou un libellé commençant par « = » comme du texte dans le fichier Excel, jamais comme une formule', function (): void {
    $title = '=HYPERLINK("http://exemple.invalid/x","Cliquez ici")';
    $dataset = new ReportDataset($title, '01/09/2026 – 30/09/2026', '14/10/2026', [['label' => '=1+1', 'value' => 7]], [
        ['title' => 'Tableau', 'head' => ['=cmd', 'Effectif'], 'rows' => [['=SUM(A1)', 8], ['+@ok', null]], 'note' => null],
    ], true);
    $path = tempnam(sys_get_temp_dir(), 'xlsx');

    (new XlsxReportWriter)->write($path, $dataset, ['Code', 'Note'], [['DOS-1', '=HYPERLINK("http://exemple.invalid")']]);

    $zip = new ZipArchive;
    $zip->open($path);

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);

        if (str_starts_with($name, 'xl/worksheets/')) {
            expect((string) $zip->getFromName($name))->not->toContain('<f>')->not->toContain('<f ');
        }
    }

    $zip->close();
    $summary = sheetRows($path, 'Synthèse');
    $kpiRow = collect($summary)->first(fn (array $row): bool => ($row[0] ?? null) === '=1+1');
    expect($summary[0][0])->toBe($title)->and($kpiRow[1])->toBe(7);
    expect(sheetRows($path, 'Tableau')[1][0])->toBe('=SUM(A1)')
        ->and(sheetRows($path, sheetNames($path)[2])[1][1])->toBe('=HYPERLINK("http://exemple.invalid")');
    unlink($path);
});

it('refuse en 422 un registre détaillé avec des destinataires et ne l’envoie jamais par e-mail', function (): void {
    Mail::fake();
    $this->actingAs(reportsAdmin());
    septemberCases(6);

    wizard(Livewire::test(Reports::class), ['fileFormat' => 'xlsx', 'detail' => true, 'recipients' => 'direction@exemple.org'])
        ->call('generate')->assertHasErrors(['recipients']);
    expect(GeneratedReport::query()->count())->toBe(0);
    Mail::assertNothingSent();

    // Sans destinataire : possible, téléchargement seulement.
    wizard(Livewire::test(Reports::class), ['fileFormat' => 'xlsx', 'detail' => true])->call('generate')->assertHasNoErrors();
    expect(GeneratedReport::query()->count())->toBe(1);

    // Garde de dernier recours : un fichier avec registre ne part pas, même s'il porte des destinataires (donnée ancienne, base modifiée).
    $report = GeneratedReport::query()->firstOrFail();
    $report->forceFill(['recipients' => ['direction@exemple.org']])->save();
    expect(app(ReportDelivery::class)->send($report->fresh()))->toBe(0);
    Livewire::test(Reports::class)->call('resend', $report->id)->assertDispatched('toast');
    Mail::assertNothingSent();
    expect(MailLog::query()->count())->toBe(0);
});

it('exige aussi cases.export pour télécharger un registre détaillé produit par une autre personne', function (): void {
    $producer = reportsAdmin();
    $reader = User::factory()->create();
    $reader->givePermissionTo(['admin.access', 'reports.generate']);
    $this->actingAs($producer);
    septemberCases(6);
    wizard(Livewire::test(Reports::class), ['fileFormat' => 'xlsx', 'detail' => true])->call('generate');
    $report = GeneratedReport::query()->firstOrFail();
    $aggregates = GeneratedReport::factory()->done(ReportFormat::Xlsx)->create(['filters' => ['sections' => [], 'detail' => false, 'parts' => 1]]);
    Storage::disk('local')->put($aggregates->file_path, 'xlsx');

    $this->actingAs($reader);
    $this->get(URL::temporarySignedRoute('admin.rapports.telecharger', now()->addMinutes(5), ['report' => $report->id, 'u' => $reader->id]))->assertForbidden();
    $this->get(URL::temporarySignedRoute('admin.rapports.telecharger', now()->addMinutes(5), ['report' => $aggregates->id, 'u' => $reader->id]))->assertOk();

    // L'historique ne signe pas de lien pour un registre à qui ne peut pas l'exporter.
    $html = Livewire::test(Reports::class)->html();
    expect($html)->not->toContain('rapports/'.$report->id.'/telecharger')->and($html)->toContain('rapports/'.$aggregates->id.'/telecharger');
});

it('masque par suppression complémentaire les chiffres clés et les mois qui se somment', function (): void {
    septemberCases(60);
    CaseRecord::factory()->vbg()->count(3)->create(['collected_by' => User::factory()->create()->id, 'created_at' => '2026-08-10 10:00:00']);

    $spec = new ReportSpec(ReportKind::Custom, 'Test', CarbonImmutable::create(2026, 8, 1), CarbonImmutable::create(2026, 9, 30), [], ['kpi', 'evol'], false, ReportFormat::Xlsx);
    $dataset = app(ReportBuilder::class)->build($spec);
    $kpis = collect($dataset->kpis)->pluck('value', 'label');

    // Total 63 = VBG 63 + VDH 0 : le total est affiché, mais VBG (63) et VDH (0) ne sont pas masqués (aucune cellule de 1 à 4).
    expect($kpis['Dossiers enregistrés'])->toBe(63)->and($kpis['Registre VBG'])->toBe(63);

    // Les mois : août (3, masqué) + septembre (60) = total connu 63 -> septembre est masqué avec lui.
    $monthly = collect($dataset->tables)->firstWhere('title', 'Évolution mensuelle des cas');
    expect(collect($monthly['rows'])->pluck(1)->all())->toBe([null, null]);
});

it('masque VBG et VDH ensemble quand l’un des deux compte de 1 à 4 dossiers (différence avec le total)', function (): void {
    septemberCases(40);
    CaseRecord::factory()->vdh()->count(3)->create(['collected_by' => User::factory()->create()->id, 'zone_id' => Zone::factory()->create()->id, 'created_at' => '2026-09-12 10:00:00']);

    $spec = new ReportSpec(ReportKind::Custom, 'Test', CarbonImmutable::create(2026, 9, 1), CarbonImmutable::create(2026, 9, 30), [], ['kpi'], false, ReportFormat::Xlsx);
    $kpis = collect(app(ReportBuilder::class)->build($spec)->kpis)->pluck('value', 'label');

    expect($kpis['Dossiers enregistrés'])->toBe(43)->and($kpis['Registre VBG'])->toBeNull()->and($kpis['Registre VDH'])->toBeNull();
});

it('masque par suppression complémentaire les lignes et colonnes d’une matrice', function (): void {
    septemberCases(30);
    Alert::factory()->sos()->count(3)->create(['created_at' => '2026-09-05 10:00:00']);
    Alert::factory()->count(30)->create(['type' => 'besoin', 'created_at' => '2026-09-06 10:00:00']);

    $spec = new ReportSpec(ReportKind::Custom, 'Test', CarbonImmutable::create(2026, 9, 1), CarbonImmutable::create(2026, 9, 30), [], ['alertes'], false, ReportFormat::Xlsx);
    $table = collect(app(ReportBuilder::class)->build($spec)->tables)->firstWhere('title', 'Alertes et SOS');
    $sosRow = collect($table['rows'])->firstWhere(0, 'SOS');

    // Une seule cellule masquée par ligne et par colonne serait déductible des totaux connus : jamais une cellule masquée seule.
    foreach ($table['rows'] as $row) {
        expect(count(array_filter(array_slice($row, 1), fn ($cell): bool => $cell === null)))->not->toBe(1);
    }

    // Colonne « Nouvelle » : SOS (3, masqué) + les autres alertes (30) se somment vers un total connu : les deux sont masqués.
    expect($sosRow)->not->toBeNull()->and($sosRow[1])->toBeNull();
    expect(collect($table['rows'])->pluck(1)->filter(fn ($cell): bool => $cell === null)->count())->toBeGreaterThanOrEqual(2);
});

it('purge les dossiers temporaires de morceaux abandonnés depuis plus de 24 h', function (): void {
    $disk = Storage::disk('local');
    $disk->put('reports/tmp/101/part-0.ndjson', '["X"]');
    $disk->put('reports/tmp/102/part-0.ndjson', '["Y"]');
    touch($disk->path('reports/tmp/101/part-0.ndjson'), now()->subDays(2)->getTimestamp());

    expect(app(PurgeExpiredReports::class)->purgeOrphanParts())->toBe(1)
        ->and($disk->exists('reports/tmp/101/part-0.ndjson'))->toBeFalse()
        ->and($disk->exists('reports/tmp/102/part-0.ndjson'))->toBeTrue();

    $this->artisan('reports:purge')->assertSuccessful();
    expect($disk->exists('reports/tmp/102/part-0.ndjson'))->toBeTrue();
});

<?php

declare(strict_types=1);

use App\Application\Admin\Dashboard\DashboardStats;
use App\Livewire\Admin\Dashboard\Community;
use App\Livewire\Admin\Dashboard\Index as Dashboard;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Message;
use App\Models\ModerationReport;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * P18-01 à P18-03 : indicateurs communautaires du tableau de bord — total, nouveaux du jour (minuit heure du Bénin),
 * rétention à 30 jours (cohorte inscrite il y a 30 à 60 jours, active dans les 30 derniers jours), sujets, réponses et
 * messages privés du jour et sur 7 jours (comptages seulement), signalements en attente dont priorité haute, taux de
 * résolution ; agrégats en cache 60 s ; composant enfant à sondage de 60 s sans comptage à chaque passage ; blocs
 * selon la permission.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    config(['xomiton.display_timezone' => 'Africa/Porto-Novo']);
    // 12/11/2026 à 00:30 heure du Bénin = 11/11/2026 23:30 UTC : « aujourd'hui » commence à 23:00 UTC la veille.
    $this->travelTo(now()->setTimezone('UTC')->setDate(2026, 11, 11)->setTime(23, 30));
    Cache::flush();
});

function p18Admin(string $role = 'admin'): User
{
    $user = User::factory()->create(['created_at' => now()->subYear()]);
    $user->syncRoles([$role]);

    return $user;
}

it('compte les nouveaux comptes du jour à partir de minuit heure du Bénin, pas minuit UTC', function (): void {
    $admin = p18Admin();
    User::factory()->create(['created_at' => now()->setTime(23, 5)]);   // 00:05 au Bénin : aujourd'hui
    User::factory()->create(['created_at' => now()->setTime(22, 55)]);  // 23:55 la veille au Bénin
    User::factory()->create(['created_at' => now()->subDays(3)]);

    $stats = app(DashboardStats::class)->computeCommunity();

    expect($stats['members']['today'])->toBe(1)
        ->and($stats['members']['total'])->toBe(4);
    unset($admin);
});

it('calcule la rétention à 30 jours sur la cohorte inscrite il y a 30 à 60 jours', function (): void {
    // Cohorte : 3 comptes inscrits entre J-60 et J-30, dont 2 vus depuis moins de 30 jours.
    User::factory()->create(['created_at' => now()->subDays(45), 'last_seen_at' => now()->subDays(2)]);
    User::factory()->create(['created_at' => now()->subDays(59), 'last_seen_at' => now()->subDays(29)]);
    User::factory()->create(['created_at' => now()->subDays(31), 'last_seen_at' => now()->subDays(31)]);
    // Hors cohorte : trop récent, trop ancien (bornes exclues / incluses).
    User::factory()->create(['created_at' => now()->subDays(29), 'last_seen_at' => now()]);
    User::factory()->create(['created_at' => now()->subDays(61), 'last_seen_at' => now()]);

    $retention = app(DashboardStats::class)->computeCommunity()['members']['retention'];

    expect($retention)->toBe(['rate' => 66.7, 'cohort' => 3, 'retained' => 2]);
});

it('rend une rétention nulle sans cohorte et un taux de résolution nul sans sujet', function (): void {
    $stats = app(DashboardStats::class)->computeCommunity();

    expect($stats['members']['retention'])->toBeNull()
        ->and($stats['resolution'])->toBeNull()
        ->and($stats['reports'])->toBe(['pending' => 0, 'high' => 0]);
});

it('compte sujets, réponses et messages privés du jour et sur 7 jours, sans lire le contenu', function (): void {
    $topic = ForumTopic::factory()->create(['created_at' => now()->setTime(23, 10)]);
    ForumTopic::factory()->create(['created_at' => now()->subDays(5)]);
    ForumTopic::factory()->create(['created_at' => now()->subDays(8)]);
    ForumReply::factory()->count(2)->create(['topic_id' => $topic->id, 'created_at' => now()->setTime(23, 20)]);
    ForumReply::factory()->create(['topic_id' => $topic->id, 'created_at' => now()->subDays(6)->setTime(23, 30)]);
    Message::factory()->count(3)->create(['created_at' => now()->setTime(23, 25)]);
    Message::factory()->create(['created_at' => now()->subDays(1)]);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $exchanges = app(DashboardStats::class)->computeCommunity()['exchanges'];

    expect($exchanges['topics'])->toBe(['today' => 1, 'week' => 2])
        ->and($exchanges['replies'])->toBe(['today' => 2, 'week' => 3])
        ->and($exchanges['messages'])->toBe(['today' => 3, 'week' => 4]);

    $messageQueries = array_values(array_filter($queries, static fn (string $sql): bool => str_contains($sql, '"messages"')));
    expect($messageQueries)->not->toBeEmpty();

    foreach ($messageQueries as $sql) {
        expect($sql)->toContain('count(*)')->not->toContain('body');
    }
});

it('compte les signalements en attente (un contenu = une ligne) dont la priorité haute, et le taux de résolution', function (): void {
    $high = ForumTopic::factory()->create();
    $low = ForumTopic::factory()->create();
    ModerationReport::factory()->count(2)->create(['subject_type' => $high->getMorphClass(), 'subject_id' => $high->id, 'kind' => 'sujet', 'status' => 'en_attente', 'priority' => 70]);
    ModerationReport::factory()->create(['subject_type' => $low->getMorphClass(), 'subject_id' => $low->id, 'kind' => 'sujet', 'status' => 'en_attente', 'priority' => 20]);
    ModerationReport::factory()->create(['subject_type' => $low->getMorphClass(), 'subject_id' => $low->id, 'kind' => 'sujet', 'status' => 'traite', 'priority' => 90]);

    $reply = ForumReply::factory()->create(['topic_id' => $high->id]);
    $high->forceFill(['accepted_reply_id' => $reply->id])->save();
    ForumTopic::factory()->create(['status' => 'masque']);

    $stats = app(DashboardStats::class)->computeCommunity();

    expect($stats['reports'])->toBe(['pending' => 2, 'high' => 1])
        ->and($stats['resolution'])->toBe(['rate' => 50.0, 'solved' => 1, 'topics' => 2]);
});

it('met les indicateurs en cache 60 s', function (): void {
    $stats = app(DashboardStats::class);
    expect($stats->community()['members']['total'])->toBe(0);

    User::factory()->create();
    expect($stats->community()['members']['total'])->toBe(0);

    $this->travel(61)->seconds();
    expect($stats->community()['members']['total'])->toBe(1);
});

it('affiche le bloc Communauté au modérateur (comptes et échanges), avec un sondage de 60 s', function (): void {
    $this->actingAs(p18Admin('moderateur'));
    Livewire::test(Dashboard::class)->assertViewHas('canCommunity', true)->assertSeeLivewire(Community::class);

    Livewire::test(Community::class)
        ->assertSee('Communauté')
        ->assertSee('Rétention à 30 jours')
        ->assertSee('Messages privés (aujourd’hui)')
        ->assertSee('Sujets résolus')
        ->assertSeeHtml('wire:poll.60s');

    expect(Community::POLL_SECONDS)->toBe(60);
});

it('ne relance aucun comptage lors d’un passage du sondage tant que le cache est valable', function (): void {
    $this->actingAs(p18Admin());
    $component = Livewire::test(Community::class);

    $queries = 0;
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'count(')) {
            $queries++;
        }
    });

    $component->call('$refresh')->assertSee('Communauté');

    expect($queries)->toBe(0);
});

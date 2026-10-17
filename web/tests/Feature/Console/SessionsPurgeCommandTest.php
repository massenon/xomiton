<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * `sessions:purge` (P1-16, D7) : sessions en base dont last_activity dépasse la durée de session + 1 jour.
 */

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 10, 17, 3));
    config(['session.lifetime' => 120, 'session.table' => 'sessions', 'session.connection' => null]);
});

function insertSession(int $lastActivity): string
{
    $id = Str::random(40);

    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => null,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => base64_encode('a:0:{}'),
        'last_activity' => $lastActivity,
    ]);

    return $id;
}

it('supprime les sessions mortes et garde celles encore dans la fenêtre', function (): void {
    $dead = insertSession(now()->subMinutes(120)->subDay()->subMinute()->getTimestamp());
    $justInside = insertSession(now()->subMinutes(120)->subDay()->addMinute()->getTimestamp());
    $expiredButRecent = insertSession(now()->subHours(3)->getTimestamp());
    $live = insertSession(now()->getTimestamp());

    $this->artisan('sessions:purge')
        ->expectsOutputToContain('1 session purgée')
        ->assertSuccessful();

    expect(DB::table('sessions')->where('id', $dead)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', $justInside)->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', $expiredButRecent)->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', $live)->exists())->toBeTrue();
});

it('est idempotente', function (): void {
    insertSession(now()->subDays(3)->getTimestamp());
    insertSession(now()->subDays(4)->getTimestamp());

    $this->artisan('sessions:purge')->expectsOutputToContain('2 sessions purgées')->assertSuccessful();
    $this->artisan('sessions:purge')->expectsOutputToContain('0 session purgée')->assertSuccessful();
});

it('ne fait rien quand la table de sessions n’existe pas', function (): void {
    config(['session.table' => 'sessions_absentes']);

    $this->artisan('sessions:purge')
        ->expectsOutputToContain('n’existe pas')
        ->assertSuccessful();
});

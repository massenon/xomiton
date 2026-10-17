<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

/*
 * P2-09 : service Audit::log et écouteurs d'audit sur les événements d'authentification.
 */

describe('Audit::log', function (): void {
    it('journalise avec le compte connecté, l’IP et le navigateur de la requête', function (): void {
        $actor = User::factory()->create(['email' => 'actrice@xomiton.bj']);
        $subject = User::factory()->create();

        $this->actingAs($actor);
        $this->app->instance('request', Request::create('/admin/utilisateurs', 'POST', server: [
            'REMOTE_ADDR' => '41.85.10.20',
            'HTTP_USER_AGENT' => 'PestBrowser/1.0',
        ]));

        $log = Audit::log('user.suspend', $subject, 'Motif : propos haineux');

        expect($log)->toBeInstanceOf(AuditLog::class)
            ->and($log->user_id)->toBe($actor->id)
            ->and($log->actor)->toBe('actrice@xomiton.bj')
            ->and($log->action)->toBe('user.suspend')
            ->and($log->subject_type)->toBe($subject->getMorphClass())
            ->and($log->subject_id)->toBe($subject->id)
            ->and($log->detail)->toBe('Motif : propos haineux')
            ->and($log->ip)->toBe('41.85.10.20')
            ->and($log->user_agent)->toBe('PestBrowser/1.0')
            ->and($log->created_at)->not->toBeNull();

        $this->assertDatabaseCount('audit_logs', 1);
    });

    it('accepte un acteur explicite et note « anonyme » sans compte', function (): void {
        $actor = User::factory()->create(['email' => 'explicite@xomiton.bj']);

        $explicit = Audit::log('auth.logout', null, null, $actor);
        $anonymous = Audit::log('auth.failed', null, 'identifiant=inconnue');

        expect($explicit->user_id)->toBe($actor->id)
            ->and($explicit->actor)->toBe('explicite@xomiton.bj')
            ->and($anonymous->user_id)->toBeNull()
            ->and($anonymous->actor)->toBe(AuditLog::ACTOR_ANONYMOUS)
            ->and($anonymous->actorLabel())->toBe(AuditLog::ACTOR_ANONYMOUS);
    });

    it('masque les champs sensibles dans le détail', function (): void {
        $log = Audit::logWith('settings.update', [
            'smtp_host' => 'mail.example.org',
            'smtp_password' => 'S3cret!',
            'otp_code' => '123456',
            'nested' => ['token' => 'abc', 'label' => 'ok'],
        ]);

        expect($log->detail)->toContain('mail.example.org', '"label":"ok"')
            ->not->toContain('S3cret!', '123456', 'abc')
            ->and(substr_count((string) $log->detail, Audit::MASK))->toBe(3);

        $text = Audit::log('auth.reset', null, 'email=awa@x.bj password=Hyper$ecret token: "xyz"; code_hash=ff00');

        expect($text->detail)->toContain('email=awa@x.bj')
            ->not->toContain('Hyper$ecret', 'xyz', 'ff00');
    });

    it('expose le masquage des tableaux et des clés sensibles', function (): void {
        expect(Audit::redact(['password' => 'a', 'name' => 'b', 'profile' => ['trusted_contact_phone' => '01', 'zone' => 'Littoral']]))
            ->toBe(['password' => Audit::MASK, 'name' => 'b', 'profile' => ['trusted_contact_phone' => Audit::MASK, 'zone' => 'Littoral']])
            ->and(Audit::isSensitiveKey('current_password'))->toBeTrue()
            ->and(Audit::isSensitiveKey('pseudo'))->toBeFalse()
            ->and(Audit::isSensitiveKey('code'))->toBeFalse();
    });

    it('tronque le détail en octets pour tenir dans la colonne TEXT', function (): void {
        $log = Audit::log('test.long', null, str_repeat('é', Audit::DETAIL_MAX_BYTES));

        expect(strlen((string) $log->detail))->toBeLessThanOrEqual(Audit::DETAIL_MAX_BYTES)
            ->and(mb_check_encoding((string) $log->detail, 'UTF-8'))->toBeTrue();
    });

    it('refuse toute modification d’une entrée écrite', function (): void {
        $log = Audit::log('test.immutable');

        expect(fn () => $log->update(['action' => 'autre']))->toThrow(LogicException::class);
    });
});

describe('écouteurs d’authentification', function (): void {
    it('journalise une connexion réussie une seule fois', function (): void {
        $user = User::factory()->create();

        event(new Login('web', $user, true));

        $this->assertDatabaseCount('audit_logs', 1);
        $log = AuditLog::query()->first();

        expect($log?->action)->toBe('auth.login')
            ->and($log?->user_id)->toBe($user->id)
            ->and($log?->subject_id)->toBe($user->id)
            ->and($log?->detail)->toContain('rester_connecte=oui');
    });

    it('journalise la déconnexion avec le compte quitté', function (): void {
        $user = User::factory()->create(['email' => 'sortie@xomiton.bj']);

        event(new Logout('web', $user));

        expect(AuditLog::query()->ofAction('auth.logout')->first()?->actor)->toBe('sortie@xomiton.bj');
    });

    it('journalise un échec sans jamais écrire le mot de passe', function (): void {
        $user = User::factory()->create(['email' => 'cible@xomiton.bj']);

        event(new Failed('web', $user, ['login' => 'Cible@xomiton.bj', 'password' => 'TopSecret!2026']));
        event(new Failed('web', null, ['email' => 'inconnue@xomiton.bj', 'password' => 'Autre$ecret']));

        $logs = AuditLog::query()->ofAction('auth.failed')->orderBy('id')->get();

        expect($logs)->toHaveCount(2)
            ->and($logs[0]->user_id)->toBeNull()
            ->and($logs[0]->actor)->toBe(AuditLog::ACTOR_ANONYMOUS)
            ->and($logs[0]->subject_id)->toBe($user->id)
            ->and($logs[0]->detail)->toContain('identifiant=cible@xomiton.bj')
            ->and($logs[1]->subject_id)->toBeNull()
            ->and($logs[1]->detail)->toContain('identifiant=inconnue@xomiton.bj');

        foreach ($logs as $log) {
            expect($log->detail)->not->toContain('TopSecret', 'Autre$ecret');
        }
    });

    it('journalise la réinitialisation du mot de passe et le verrouillage', function (): void {
        $user = User::factory()->create();

        event(new PasswordReset($user));
        event(new Lockout(Request::create('/connexion', 'POST', ['login' => 'Awa@xomiton.bj', 'password' => 'x'], server: ['REMOTE_ADDR' => '10.0.0.9'])));

        expect(AuditLog::query()->ofAction('auth.password_reset')->first()?->user_id)->toBe($user->id);

        $lockout = AuditLog::query()->ofAction('auth.lockout')->first();

        expect($lockout?->detail)->toContain('identifiant=awa@xomiton.bj', 'ip=10.0.0.9')
            ->and($lockout?->detail)->not->toContain('password');

        // Le formulaire de connexion peut nommer le champ `identifier` (e-mail ou pseudo) : même journal.
        event(new Lockout(Request::create('/connexion', 'POST', ['identifier' => 'Awa_Pseudo', 'password' => 'x'])));

        expect(AuditLog::query()->ofAction('auth.lockout')->latest('id')->first()?->detail)->toContain('identifiant=awa_pseudo');
    });

    it('n’expose aucune route de modification du journal (P2-09)', function (): void {
        $mutating = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => (bool) preg_match('/journal|audit/i', $route->uri().' '.(string) $route->getName()))
            ->filter(fn ($route): bool => array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods()) !== [])
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        expect($mutating)->toBe([]);
    });

    it('découvre chaque écouteur exactement une fois', function (string $event): void {
        $listeners = Event::getRawListeners()[$event] ?? [];

        expect(array_filter($listeners, fn (mixed $listener): bool => is_string($listener) && str_starts_with($listener, 'App\\Listeners\\Auth\\')))
            ->toHaveCount(1);
    })->with([Login::class, Logout::class, Failed::class, PasswordReset::class, Lockout::class]);
});

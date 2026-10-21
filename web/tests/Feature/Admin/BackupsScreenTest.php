<?php

declare(strict_types=1);

use App\Application\Platform\Backup\BackupService;
use App\Application\Platform\Backup\RestoreState;
use App\Enums\BackupKind;
use App\Livewire\Admin\Backups\Index as Backups;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * P13-03 : écran /admin/sauvegardes — lecture, création, téléchargement : admin et superadmin (403 modérateur et membre) ;
 * restauration et suppression : superadmin seul (403 admin) ; aperçu masqué ; saisie « RESTAURER » ; suivi de restauration ;
 * téléchargement journalisé.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    Storage::fake('backups');
    config(['xomiton.backup.disk' => 'backups', 'xomiton.backup.password' => 'mot-de-passe-de-test-1234']);
});

function backupsUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

it('sert l’écran à l’admin et au superadmin, le refuse au modérateur, à la pair·e et au membre', function (): void {
    foreach (['admin', 'superadmin'] as $role) {
        $html = $this->actingAs(backupsUser($role))->get(route('admin.sauvegardes'))
            ->assertOk()
            ->assertSeeLivewire(Backups::class)
            ->assertSee('Sauvegarder maintenant')
            ->assertSee('Indicateurs clés')
            ->getContent();
        expect($html)->toMatch('/data-xm-nav="sauvegardes"\s+aria-current="page"/');
    }

    foreach (['moderateur', 'pair', 'membre'] as $role) {
        $this->actingAs(backupsUser($role))->get(route('admin.sauvegardes'))->assertForbidden();
    }
});

it('crée une sauvegarde manuelle depuis l’écran et la liste avec son type, sa taille et ses lignes', function (): void {
    $admin = backupsUser('admin');
    $this->actingAs($admin);

    Livewire::test(Backups::class)
        ->call('create')
        ->assertDispatched('toast')
        ->assertSee('Manuelle')
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1);

    $backup = Backup::query()->firstOrFail();
    expect($backup->kind)->toBe(BackupKind::Manual)->and($backup->created_by)->toBe($admin->id)
        ->and(AuditLog::query()->where('action', 'backup.created')->exists())->toBeTrue();
});

it('montre l’aperçu avec écart et extrait masqué', function (): void {
    $this->actingAs(backupsUser('admin'));
    $backup = app(BackupService::class)->create(BackupKind::Manual);

    Livewire::test(Backups::class)
        ->call('preview', $backup->id)
        ->assertSee('Collections')
        ->assertSee('users')
        ->call('sample', 'users')
        ->assertSee('••••')
        ->assertDontSee('password_secret');
});

it('réserve la restauration et la suppression au superadmin (403 admin)', function (): void {
    $backup = app(BackupService::class)->create(BackupKind::Manual);

    $this->actingAs(backupsUser('admin'));
    Livewire::test(Backups::class)
        ->assertDontSee('Restaurer la sauvegarde')
        ->assertDontSee('Supprimer la sauvegarde')
        ->call('delete', $backup->id)->assertForbidden();
    Livewire::test(Backups::class)->call('confirmRestore', $backup->id)->assertForbidden();
    expect(Backup::query()->count())->toBe(1);

    $this->actingAs(backupsUser('superadmin'));
    Livewire::test(Backups::class)
        ->assertSee('Restaurer la sauvegarde')
        ->call('delete', $backup->id)
        ->assertDispatched('toast');
    expect(Backup::query()->count())->toBe(0);
});

it('exige la saisie exacte de « RESTAURER » puis restaure et affiche le suivi terminé', function (): void {
    $super = backupsUser('superadmin');
    $this->actingAs($super);
    $backup = app(BackupService::class)->create(BackupKind::Manual, $super);
    User::factory()->create(['pseudo' => 'apres-sauvegarde']);

    Livewire::test(Backups::class)
        ->call('confirmRestore', $backup->id)
        ->set('restoreWord', 'restaurer')
        ->call('startRestore')
        ->assertHasErrors(['restoreWord'])
        ->set('restoreWord', 'RESTAURER')
        ->call('startRestore')
        ->assertHasNoErrors()
        ->assertSee('Restauration terminée');

    expect(RestoreState::read()['status'])->toBe('done')
        ->and(User::query()->where('pseudo', 'apres-sauvegarde')->exists())->toBeFalse()
        ->and(Backup::query()->ofKind(BackupKind::BeforeRestore)->count())->toBe(1);
});

it('télécharge l’archive chiffrée pour l’admin, journalise, refuse le modérateur', function (): void {
    $backup = app(BackupService::class)->create(BackupKind::Manual);

    $this->actingAs(backupsUser('moderateur'))->get(route('admin.sauvegardes.telecharger', $backup))->assertForbidden();
    $this->actingAs(backupsUser('admin'))->get(route('admin.sauvegardes.telecharger', $backup))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=xomiton-sauvegarde-'.$backup->name.'.zip');

    expect(AuditLog::query()->where('action', 'backup.downloaded')->exists())->toBeTrue();
});

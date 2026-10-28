<?php

declare(strict_types=1);

use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/*
 * Recette (docs/GUIDE_RECETTE.md §5) : chaque compte de démonstration doit pouvoir se connecter de bout en bout
 * (formulaire + jeton CSRF, code OTP d'un appareil inconnu, redirection) et atteindre sa page d'arrivée.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    Mail::fake();
    config(['mail.default' => 'array']);
});

dataset('demo-accounts', [
    'super-administrateur·rice' => ['admin@xomiton.bj', 'Admin123!', '/admin'],
    'administrateur·rice' => ['moderation@xomiton.bj', 'Admin123!', '/admin'],
    'modérateur·rice' => ['moderateur@xomiton.bj', 'Modo123!', '/admin'],
    'membre' => ['membre@xomiton.bj', 'Membre123!', '/fil'],
    'membre (pseudo)' => ['awa_s', 'Membre123!', '/fil'],
    'second membre' => ['membre2@xomiton.bj', 'Membre123!', '/fil'],
    'pair éducateur·rice' => ['pair@xomiton.bj', 'Pair123!', '/fil'],
    'pair en attente' => ['pair.attente@xomiton.bj', 'Pair123!', '/fil'],
]);

it('connecte chaque compte de démonstration jusqu’à sa page d’arrivée', function (string $identifier, string $password, string $landing): void {
    $this->get('/connexion')->assertOk();

    // Appareil inconnu : le mot de passe mène à l'écran du code, jamais à une erreur.
    $this->post('/connexion', ['identifier' => $identifier, 'password' => $password])
        ->assertRedirect(route('otp', ['purpose' => 'login']));
    $this->assertGuest();

    $user = User::query()->where('email', $identifier)->orWhere('pseudo', $identifier)->firstOrFail();
    OtpCode::query()->forEmail($user->email, OtpPurpose::Login)->valid()->latest('id')->firstOrFail()
        ->forceFill(['code_hash' => OtpCode::hash('135790')])->save();

    $this->get(route('otp', ['purpose' => 'login']))->assertOk();
    $this->post('/code-de-verification', ['purpose' => 'login', 'code' => ['1', '3', '5', '7', '9', '0']])
        ->assertRedirect(url($landing));

    $this->assertAuthenticatedAs($user);
    $this->get($landing)->assertOk();
})->with('demo-accounts');

it('refuse un mot de passe de démonstration erroné sans ouvrir de session', function (): void {
    $this->post('/connexion', ['identifier' => 'membre@xomiton.bj', 'password' => 'Admin123!'])
        ->assertSessionHasErrors('identifier');
    $this->assertGuest();
});

it('garde les identifiants du guide de recette alignés sur le seeder des comptes de démonstration', function (): void {
    $guide = (string) file_get_contents(base_path('../docs/GUIDE_RECETTE.md'));

    foreach (DemoAccountsSeeder::accounts() as $account) {
        expect($guide)->toContain('`'.$account['email'].'`')->toContain('`'.$account['password'].'`');
    }
});

it('nomme tous les index en 64 caractères au plus, limite de MySQL qui fait échouer migrate:fresh', function (): void {
    foreach (Schema::getTables() as $table) {
        foreach (Schema::getIndexes($table['name']) as $index) {
            expect(strlen($index['name']))->toBeLessThanOrEqual(64, $table['name'].' : '.$index['name']);
        }
    }
});

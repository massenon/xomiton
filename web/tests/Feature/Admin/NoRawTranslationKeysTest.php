<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;

/* B4 (GUIDE_RECETTE §6) : aucun écran admin ne rend une clé de traduction brute ni une clé de pluriel non traitée. */

it('ne rend aucune clé de traduction brute sur les écrans admin (GET sans paramètre)', function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    $user = User::factory()->create();
    $user->syncRoles(['superadmin']);
    $this->actingAs($user);

    $patterns = '/\badmin(?:_[a-z]+)?\.[a-z_]+\.[a-z_.]+|\bnewsletter\.admin\.[a-z_.]+|\|\[2,\*\]|\{[01]\} /u';
    $failures = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_starts_with($route->getName() ?? '', 'admin.') || ! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{')) {
            continue;
        }
        if (str_contains($route->getName(), 'export') || str_contains($route->getName(), 'pdf')) {
            continue;
        }

        $response = $this->get('/'.$route->uri());
        if ($response->status() !== 200 || ! str_contains((string) $response->headers->get('Content-Type'), 'html')) {
            continue;
        }

        // Retire scripts, styles et attributs wire:snapshot (données JSON) avant de chercher.
        $html = preg_replace(['#<script\b.*?</script>#si', '#<style\b.*?</style>#si', '#\swire:[a-z.\-]+="[^"]*"#i'], '', $response->getContent());
        if (preg_match($patterns, strip_tags((string) $html), $m)) {
            $failures[$route->getName()] = $m[0];
        }
    }

    expect($failures)->toBe([]);
});

<?php

declare(strict_types=1);

use App\Models\BdConversation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/*
 * P20-03 : tables des conversations en BD (migrations idempotentes, rejouables) et permission `conversations_bd.manage`
 * posée sur une base déjà semée (migration de rattrapage) ; valeurs par défaut du modèle (anonyme, brouillon, modèle).
 */

it('crée les tables et les colonnes des lots A et B, et se rejoue sans erreur', function (): void {
    foreach (['bd_characters', 'bd_scenes', 'bd_conversations', 'bd_panels', 'bd_bubbles'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table);
    }
    expect(Schema::hasColumns('bd_conversations', ['ulid', 'slug', 'model_key', 'origin', 'author_id', 'is_anonymous', 'status', 'refusal_reason', 'discussion_questions', 'content_warning', 'submitted_at', 'reviewed_at', 'reviewed_by', 'published_at', 'withdrawn_at', 'content_edited_at', 'status_changed_at']))->toBeTrue()
        ->and(Schema::hasColumns('bd_bubbles', ['type', 'character_key', 'text', 'emphasis', 'x', 'y', 'tail_target', 'tail_x', 'tail_y', 'linked_to', 'is_overlapping', 'is_masked', 'position']))->toBeTrue()
        ->and(Schema::hasColumns('bd_scenes', ['key', 'image_path', 'description', 'characters', 'is_active']))->toBeTrue();

    $migration = require database_path('migrations/2026_12_08_090000_create_bd_conversations_tables.php');
    $migration->up();
    expect(Schema::hasTable('bd_bubbles'))->toBeTrue();
});

it('pose les valeurs par défaut : brouillon, anonyme, origine modèle ; supprime cases et bulles avec la conversation', function (): void {
    $user = User::factory()->create();
    $conversation = BdConversation::factory()->draft()->withPanels(3)->create();
    $conversation->forceFill(['author_id' => $user->id])->save();
    $fresh = BdConversation::query()->findOrFail($conversation->id);

    expect($fresh->is_anonymous)->toBeTrue()->and($fresh->isPublished())->toBeFalse()->and($fresh->panels()->count())->toBe(3);

    // Compte supprimé : la création reste, sans auteur·rice.
    $user->forceDelete();
    expect($fresh->fresh()->author_id)->toBeNull();

    $conversation->delete();
    expect(App\Models\BdPanel::query()->count())->toBe(0)->and(App\Models\BdBubble::query()->count())->toBe(0);
});

it('donne la permission aux rôles admin et superadmin d’une base déjà semée', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Permission::query()->where('name', 'conversations_bd.manage')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $migration = require database_path('migrations/2026_12_08_090100_grant_conversations_bd_permission.php');
    $migration->up();
    $migration->up();

    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    $moderator = User::factory()->create();
    $moderator->syncRoles(['moderateur']);

    expect($admin->can('conversations_bd.manage'))->toBeTrue()->and($moderator->can('conversations_bd.manage'))->toBeFalse();
});

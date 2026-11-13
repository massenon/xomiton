<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Filtre minimal de contenus (lot IOS-10, D-IOS-9) : catégorie des mots filtrés (insulte, haine, violence, autre),
     * liste blanche des faux positifs (`content_filter_allowlist`) et permission dédiée `moderation.filter`
     * (admin et superadmin) donnée sans attendre un rejeu du seeder. Index nommés explicitement (MySQL : 64 caractères).
     */
    public function up(): void
    {
        // Rejouable : MySQL ne revient pas sur un ALTER TABLE quand la migration échoue plus loin (une première
        // tentative interrompue a pu laisser la colonne ou la table en place).
        if (! Schema::hasColumn('bad_words', 'category')) {
            Schema::table('bad_words', function (Blueprint $table): void {
                $table->string('category', 20)->default('autre')->after('word');
            });
        }

        if (! Schema::hasTable('content_filter_allowlist')) {
            Schema::create('content_filter_allowlist', function (Blueprint $table): void {
                $table->id();
                $table->string('phrase', 80);
                $table->timestamps();

                $table->unique('phrase', 'cf_allowlist_phrase_unique');
            });
        }

        $roles = Role::query()->where('guard_name', 'web')->whereIn('name', ['admin', 'superadmin'])->get();

        if ($roles->isNotEmpty()) {
            $permission = Permission::findOrCreate('moderation.filter', 'web');

            foreach ($roles as $role) {
                $role->givePermissionTo($permission);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_filter_allowlist');

        if (Schema::hasColumn('bad_words', 'category')) {
            Schema::table('bad_words', function (Blueprint $table): void {
                $table->dropColumn('category');
            });
        }

        Permission::query()->where('guard_name', 'web')->where('name', 'moderation.filter')->first()?->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

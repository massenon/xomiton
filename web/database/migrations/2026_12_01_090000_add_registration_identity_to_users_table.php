<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inscription en 3 étapes (décision client du 11/11/2026) : prénom et nom saisis séparément. `users.name` reste
     * « Prénom Nom », calculé à l'inscription. Colonnes nullables : les comptes existants n'ont que `name`.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 60)->nullable()->after('pseudo');
            $table->string('last_name', 60)->nullable()->after('first_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};

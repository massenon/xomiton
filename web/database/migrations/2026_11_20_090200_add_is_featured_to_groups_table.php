<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * « Mettre en avant » du réseau social (P12-05) : les pages l'avaient déjà (`pages.is_featured`, P8-03) ; les groupes
     * reçoivent le même drapeau, qui les place en tête de la liste (GroupDirectory). Ni les événements (ordre
     * chronologique), ni les forums, ni les publications ne se mettent en avant.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });
    }
};

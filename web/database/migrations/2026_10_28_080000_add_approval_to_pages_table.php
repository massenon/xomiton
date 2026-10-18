<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Approbation des pages d'organisation (P8-03, P12-05 ; IMPLEMENTATION_PLAN.md P1-04 : `is_approved`, `is_featured`) :
     * une page n'est visible des autres membres qu'une fois approuvée par la modération (écran /admin/reseau, P12-05).
     * Les pages déjà présentes (import du prototype, semis) précèdent ce circuit : elles sont marquées approuvées.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('is_approved')->default(false)->after('is_verified');
            $table->timestamp('approved_at')->nullable()->after('is_approved');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->boolean('is_featured')->default(false)->after('approved_by');
            $table->index(['is_approved', 'is_active']);
        });

        DB::table('pages')->update(['is_approved' => true, 'approved_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex(['is_approved', 'is_active']);
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['is_approved', 'approved_at', 'is_featured']);
        });
    }
};

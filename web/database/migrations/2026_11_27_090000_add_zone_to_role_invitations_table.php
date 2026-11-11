<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zone (département) de rattachement facultative d'une invitation : à la validation du compte, la personne invitée
     * (rôle `pair`) est rattachée à cette zone (`users.zone_id`).
     */
    public function up(): void
    {
        Schema::table('role_invitations', function (Blueprint $table) {
            $table->foreignId('zone_id')->nullable()->after('role')->constrained('zones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('role_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zone_id');
        });
    }
};

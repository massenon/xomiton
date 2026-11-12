<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Score de priorité des signalements (P18-04, App\Domain\Moderation\ReportPriority) : `priority` (0 à 110), commun à
     * tous les signalements en attente d'un même contenu, index (status, priority) pour le tri par défaut de la file.
     * Les signalements déjà en attente sont notés aussitôt (`moderation:priorities`). Idempotente.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('moderation_reports', 'priority')) {
            Schema::table('moderation_reports', function (Blueprint $table): void {
                $table->unsignedSmallInteger('priority')->default(0);
                $table->index(['status', 'priority']);
            });
        }

        Artisan::call('moderation:priorities');
    }

    public function down(): void
    {
        if (Schema::hasColumn('moderation_reports', 'priority')) {
            Schema::table('moderation_reports', function (Blueprint $table): void {
                $table->dropIndex(['status', 'priority']);
                $table->dropColumn('priority');
            });
        }
    }
};

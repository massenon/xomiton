<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // users (0001_01_01) est créée avant zones et communes (lot 1) : les FK ne peuvent être posées qu'ici.
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('zone_id')->references('id')->on('zones')->nullOnDelete();
            $table->foreign('commune_id')->references('id')->on('communes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['zone_id']);
            $table->dropForeign(['commune_id']);
        });
    }
};

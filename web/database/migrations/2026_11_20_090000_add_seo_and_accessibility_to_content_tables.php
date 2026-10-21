<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Administration du contenu (P12-03) : image Open Graph 1200 × 630 par fiche, sous-titres WebVTT et transcription
     * d'une vidéo ou d'un audio (ACCESSIBILITE.md §5 : sans les deux, la ressource ne se publie pas).
     */
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->string('og_image_path', 255)->nullable()->after('image_path');
            $table->string('subtitles_path', 255)->nullable()->after('file_size');
            $table->longText('transcript')->nullable()->after('subtitles_path');
        });

        Schema::table('thematiques', function (Blueprint $table) {
            $table->string('og_image_path', 255)->nullable()->after('cover_path');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn(['og_image_path', 'subtitles_path', 'transcript']);
        });

        Schema::table('thematiques', function (Blueprint $table) {
            $table->dropColumn('og_image_path');
        });
    }
};

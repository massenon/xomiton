<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P8-01 (revue messagerie) : consentement et historique dans un groupe.
 *
 *  - `joined_after_message_id` : dernier message de la conversation au moment où la personne y entre (ajout ou retour) ;
 *    dans un groupe, elle ne lit que les messages suivants (null : tout l'historique, fondateur·rices du groupe) ;
 *  - `left_reason` : `left` (départ de son propre chef : personne ne la réintègre) ou `removed` (retirée par un·e admin :
 *    peut être ajoutée de nouveau) ; null tant qu'elle est présente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->unsignedBigInteger('joined_after_message_id')->nullable()->after('role');
            $table->string('left_reason', 10)->nullable()->after('left_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropColumn(['joined_after_message_id', 'left_reason']);
        });
    }
};

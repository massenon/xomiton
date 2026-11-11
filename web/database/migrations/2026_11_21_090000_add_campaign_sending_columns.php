<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P14-03 : envoi des campagnes par lots.
     * - `mail_outbox.headers` : en-têtes ajoutés à l'envoi (List-Unsubscribe, List-Unsubscribe-Post) ;
     * - `mail_outbox.newsletter_recipient_id` : destinataire de campagne à l'origine du dépôt (un rebond définitif
     *   constaté au renvoi est reporté sur lui) ;
     * - `newsletter_campaign_recipients.attempts` : échecs temporaires déjà essuyés (3 au plus, puis échec définitif).
     */
    public function up(): void
    {
        Schema::table('mail_outbox', function (Blueprint $table): void {
            $table->json('headers')->nullable()->after('text');
            $table->unsignedBigInteger('newsletter_recipient_id')->nullable()->after('template_slug')->index();
        });

        Schema::table('newsletter_campaign_recipients', function (Blueprint $table): void {
            $table->unsignedTinyInteger('attempts')->default(0)->after('error');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_campaign_recipients', function (Blueprint $table): void {
            $table->dropColumn('attempts');
        });

        Schema::table('mail_outbox', function (Blueprint $table): void {
            $table->dropIndex(['newsletter_recipient_id']);
            $table->dropColumn(['headers', 'newsletter_recipient_id']);
        });
    }
};

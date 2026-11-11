<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Application\Mail\EmailTemplateService;
use App\Application\Newsletter\UnsubscribeFromNewsletter;
use App\Domain\Newsletter\SubscriptionPageState;
use App\Models\PushSubscription;
use App\Models\SyncDevice;
use App\Models\User;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * DELETE /api/v1/me (P6-06, DATABASE_ENGINEERING.md §6 : « logique 30 j puis anonymisation ») : suppression logique du
 * compte (`users.deleted_at`) ; `accounts:anonymize` (App\Application\Retention\AnonymizeDeletedAccounts) efface les
 * données personnelles après `xomiton.accounts.deletion_grace_days` jours. Tout accès s'arrête tout de suite : jetons
 * Sanctum, sessions web, abonnements push et export RGPD en attente supprimés, appareils de synchronisation révoqués,
 * newsletter désabonnée (UnsubscribeFromNewsletter, motif « Compte supprimé » ; la ligne garde `user_id` jusqu'à
 * l'anonymisation, qui efface l'adresse). E-mail de confirmation
 * `compte-supprime` par la boîte d'envoi (aucun SMTP dans la requête, D7) : il prévient la personne si quelqu'un d'autre
 * a supprimé son compte. Journal `account.deletion_requested` (motif jamais journalisé). Les signalements anonymes déjà
 * transmis ne sont pas rattachés au compte et restent suivis (texte `profile.settings.delete.text`).
 */
final class RequestAccountDeletion
{
    public const CONFIRMATION_TEMPLATE = 'compte-supprime';

    public function __construct(
        private readonly PersonalDataExports $exports,
        private readonly MemberNewsletter $newsletter,
        private readonly UnsubscribeFromNewsletter $unsubscribe,
    ) {}

    /**
     * @return CarbonImmutable date à partir de laquelle les données sont effacées
     */
    public function __invoke(User $user): CarbonImmutable
    {
        $days = max(1, (int) config('xomiton.accounts.deletion_grace_days', 30));
        $erasedFrom = CarbonImmutable::now()->addDays($days);
        $email = (string) $user->email;
        $firstName = trim(Str::before(trim((string) $user->name), ' ')) ?: (string) $user->pseudo;

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            PushSubscription::query()->where('user_id', $user->getKey())->delete();
            SyncDevice::query()->where('user_id', $user->getKey())->active()->update(['is_revoked' => true, 'updated_at' => now()]);
            $this->closeWebSessions($user);
            $user->delete();
        });

        $this->exports->forget($user);
        $this->leaveNewsletter($user);

        Audit::log('account.deletion_requested', $user, sprintf('Effacement des données à partir du %s (%d jours).', $erasedFrom->format('d/m/Y'), $days), $user);

        EmailTemplateService::enqueue(self::CONFIRMATION_TEMPLATE, $email, [
            'user_first_name' => $firstName,
            'user_pseudo' => (string) $user->pseudo,
            'deletion_days' => (string) $days,
            'deletion_date' => $erasedFrom->format('d/m/Y'),
        ], 'compte');

        return $erasedFrom;
    }

    /** Plus aucune newsletter vers un compte supprimé (trace : consentement retiré, motif enregistré). */
    private function leaveNewsletter(User $user): void
    {
        $subscriber = $this->newsletter->find($user);

        if ($subscriber === null || $this->unsubscribe->state($subscriber) !== SubscriptionPageState::Unsubscribe) {
            return;
        }

        ($this->unsubscribe)($subscriber);
        $subscriber->forceFill(['unsubscribe_reason' => (string) __('account.deletion.newsletter_reason')])->save();
    }

    private function closeWebSessions(User $user): void
    {
        $table = (string) config('session.table', 'sessions');
        $connection = config('session.connection');
        $connection = is_string($connection) && $connection !== '' ? $connection : null;

        if (Schema::connection($connection)->hasTable($table)) {
            DB::connection($connection)->table($table)->where('user_id', $user->getKey())->delete();
        }
    }
}

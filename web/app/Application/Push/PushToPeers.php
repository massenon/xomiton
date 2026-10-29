<?php

declare(strict_types=1);

namespace App\Application\Push;

use App\Models\CaseRecord;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\RulesUpdated;
use App\Notifications\SyncRequested;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Pushs silencieux vers les pair·es (P15-19, P15-20) : `rules` (une règle de validation a changé : relire
 * `pair/validation-rules`) et `sync` (quelqu'un d'autre a changé ce que le pair a collecté : lancer un cycle). Données
 * seules, FCM seulement (aucune cloche, aucun texte), jamais bloquants : une panne du fournisseur est journalisée, la
 * requête d'administration réussit (le `heartbeat` et le prochain contact rattrapent). Le push n'est jamais l'unique
 * chemin : `heartbeat.rules_updated_at` et `sync/pull` donnent le même résultat.
 *
 * Anti-rafale : un même pair ne reçoit pas deux `sync` en moins de `xomiton.sync.push_debounce_seconds` secondes (une
 * modification de dossier, puis la résolution d'un conflit, partent en un seul réveil) ; `rules`, de même, une fois
 * par `xomiton.sync.rules_push_debounce_seconds` (un réglage enregistre souvent plusieurs règles à la suite).
 */
final class PushToPeers
{
    /** Les pair·es à réveiller pour les règles : rôle `pair` actif ayant au moins un jeton FCM. */
    public function rulesChanged(): int
    {
        if (! Cache::add('push:rules', 1, (int) config('xomiton.sync.rules_push_debounce_seconds', 20))) {
            return 0;
        }

        $peers = User::query()->active()->role('pair')
            ->whereHas('pushSubscriptions', static fn ($q) => $q->ofKind(PushSubscription::KIND_FCM))
            ->get();

        return $this->send($peers, new RulesUpdated);
    }

    /** Le pair qui a collecté le dossier, si quelqu'un d'autre vient de le modifier. */
    public function caseChangedBy(CaseRecord $case, User $by): int
    {
        $collector = $case->collected_by;

        if ($collector === null || (int) $collector === (int) $by->getKey()) {
            return 0;
        }

        $peer = User::query()->active()->whereKey($collector)->first();

        return $peer === null ? 0 : $this->syncRequested($peer);
    }

    /** Réveille un pair pour un cycle de synchro (dédoublonné par pair). */
    public function syncRequested(User $peer): int
    {
        if (! Cache::add('push:sync:'.$peer->getKey(), 1, (int) config('xomiton.sync.push_debounce_seconds', 20))) {
            return 0;
        }

        return $this->send(collect([$peer]), new SyncRequested);
    }

    /**
     * @param  iterable<int, User>  $users
     */
    private function send(iterable $users, RulesUpdated|SyncRequested $notification): int
    {
        $sent = 0;

        foreach ($users as $user) {
            try {
                Notification::sendNow($user, $notification);
                $sent++;
            } catch (Throwable $e) {
                Log::warning('Push silencieux non envoyé.', ['type' => $notification::class, 'error' => $e->getMessage()]);
            }
        }

        return $sent;
    }
}

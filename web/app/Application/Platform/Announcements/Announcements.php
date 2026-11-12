<?php

declare(strict_types=1);

namespace App\Application\Platform\Announcements;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementLevel;
use App\Models\Announcement;
use App\Models\AnnouncementDismissal;
use App\Models\User;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Annonce globale (P18-13, Q97 : **uniquement l'application et l'espace social**, jamais le site public ni les
 * visiteur·ses) :
 *  - `activeFor()` : l'annonce à montrer à un compte — en cours (début passé, fin à venir), du bon public (tous les
 *    membres de l'espace social, ou les pair·es seulement), pas encore fermée par la personne ; « Important » d'abord,
 *    puis la plus récente. Liste des annonces en cours en cache 60 s (oubliée à chaque écriture) ;
 *  - `dismiss()` : fermeture mémorisée par compte et par annonce (web et application partagent l'état) ;
 *  - `save()` / `end()` / `delete()` : écran `/admin/annonces` (`announcements.manage`), journal `announcements.*` sans
 *    texte.
 * Distincte du bandeau de maintenance (MaintenancePage::notice) : les deux peuvent coexister.
 *
 * @phpstan-type AnnouncementPayload array{id: int, title: string, message: string, level: string, link: string|null, starts_at: string|null, ends_at: string|null}
 */
final class Announcements
{
    public const CACHE_KEY = 'announcements:current';

    public const CACHE_SECONDS = 60;

    /**
     * @return AnnouncementPayload|null
     */
    public function activeFor(?User $user): ?array
    {
        if ($user === null || ! $user->can('social.access')) {
            return null;
        }

        $current = $this->current()->filter(static fn (Announcement $announcement): bool => $announcement->audience() === AnnouncementAudience::Members
            || $user->hasRole('pair'));

        if ($current->isEmpty()) {
            return null;
        }

        $dismissed = AnnouncementDismissal::query()->where('user_id', $user->getKey())
            ->whereIn('announcement_id', $current->modelKeys())->pluck('announcement_id')
            ->map(static fn (mixed $id): int => (int) $id)->all();

        $announcement = $current->first(static fn (Announcement $item): bool => ! in_array((int) $item->getKey(), $dismissed, true));

        return $announcement !== null ? self::payload($announcement) : null;
    }

    /** Mémorise la fermeture ; faux si l'annonce n'est pas en cours (rien à fermer). Idempotent. */
    public function dismiss(Announcement $announcement, User $user): bool
    {
        if (! $this->current()->contains(static fn (Announcement $item): bool => (int) $item->getKey() === (int) $announcement->getKey())) {
            return false;
        }

        AnnouncementDismissal::query()->firstOrCreate(
            ['announcement_id' => $announcement->getKey(), 'user_id' => $user->getKey()],
            ['dismissed_at' => now()],
        );

        return true;
    }

    /**
     * @param  array{title: string, message: string, link_path?: string|null, level: string, audience: string, starts_at: string, ends_at?: string|null}  $data
     *                                                                                                                                                           dates `Y-m-d\TH:i` à l'heure d'affichage
     */
    public function save(?Announcement $announcement, array $data, User $actor): Announcement
    {
        $timezone = (string) config('xomiton.display_timezone', 'UTC');
        $creating = $announcement === null;
        $announcement ??= new Announcement;
        $announcement->fill([
            'title' => trim($data['title']),
            'message' => trim($data['message']),
            'link_path' => ($data['link_path'] ?? null) !== null && trim((string) $data['link_path']) !== '' ? trim((string) $data['link_path']) : null,
            'level' => AnnouncementLevel::from($data['level'])->value,
            'audience' => AnnouncementAudience::from($data['audience'])->value,
            'starts_at' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['starts_at'], $timezone)?->utc(),
            'ends_at' => ($data['ends_at'] ?? null) !== null && $data['ends_at'] !== '' ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', (string) $data['ends_at'], $timezone)?->utc() : null,
        ]);

        if ($creating) {
            $announcement->created_by = $actor->getKey();
        }

        $announcement->save();
        Audit::log($creating ? 'announcements.created' : 'announcements.updated', $announcement, 'audience='.$announcement->audience()->value, $actor);
        self::flush();

        return $announcement;
    }

    /** Termine l'annonce maintenant (elle disparaît des bannières). */
    public function end(Announcement $announcement, User $actor): void
    {
        $announcement->forceFill(['ends_at' => now()])->save();
        Audit::log('announcements.ended', $announcement, null, $actor);
        self::flush();
    }

    public function delete(Announcement $announcement, User $actor): void
    {
        Audit::log('announcements.deleted', $announcement, null, $actor);
        $announcement->delete();
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Annonces en cours, « Important » d'abord puis la plus récente.
     *
     * @return Collection<int, Announcement>
     */
    private function current(): Collection
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, static fn (): array => Announcement::query()->current()
            ->orderByRaw("case when level = 'important' then 0 else 1 end")
            ->orderByDesc('starts_at')->orderByDesc('id')
            ->limit(10)->get()->map(static fn (Announcement $item): array => $item->getAttributes())->all());

        // Le cache garde les lignes ; une annonce finie entre-temps (fin dépassée dans la minute) est retirée ici.
        $now = now();

        return Announcement::hydrate($rows)->filter(static function (Announcement $item) use ($now): bool {
            $ends = $item->getAttributes()['ends_at'] ?? null;

            return $ends === null || $item->asDateTimePublic($ends)->greaterThan($now);
        })->values();
    }

    /**
     * @return AnnouncementPayload
     */
    public static function payload(Announcement $announcement): array
    {
        $attributes = $announcement->getAttributes();

        return [
            'id' => (int) $announcement->getKey(),
            'title' => (string) $announcement->title,
            'message' => (string) $announcement->message,
            'level' => $announcement->level()->value,
            'link' => is_string($attributes['link_path'] ?? null) && $attributes['link_path'] !== '' ? (string) $attributes['link_path'] : null,
            'starts_at' => ($attributes['starts_at'] ?? null) !== null ? $announcement->asDateTimePublic($attributes['starts_at'])->toIso8601String() : null,
            'ends_at' => ($attributes['ends_at'] ?? null) !== null ? $announcement->asDateTimePublic($attributes['ends_at'])->toIso8601String() : null,
        ];
    }
}

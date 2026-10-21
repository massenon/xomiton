<?php

declare(strict_types=1);

namespace App\Application\Admin\Content;

use App\Models\SlugRedirect;

/**
 * Historique des adresses (P12-03) : changer le slug d'une ressource ou d'une thématique garde l'ancien (301 par
 * RedirectRenamedSlug). Revenir à un ancien slug retire sa redirection ; un slug déjà pris par l'historique d'une
 * **autre** fiche reste interdit (rules : `ContentRules::slugAvailable`).
 */
final class SlugHistory
{
    public function renamed(string $type, int $subjectId, string $oldSlug, string $newSlug): void
    {
        if ($oldSlug === '' || $oldSlug === $newSlug) {
            return;
        }

        SlugRedirect::query()->where('subject_type', $type)->where('old_slug', $newSlug)->delete();
        SlugRedirect::query()->firstOrCreate(['subject_type' => $type, 'old_slug' => $oldSlug], ['subject_id' => $subjectId]);
        SlugRedirect::query()->where('subject_type', $type)->where('old_slug', $oldSlug)->update(['subject_id' => $subjectId]);
    }

    /** Le slug est-il libre pour cette fiche (ni slug actuel d'une autre, ni ancien slug d'une autre) ? */
    public static function usedByOther(string $type, string $slug, ?int $subjectId): bool
    {
        return SlugRedirect::query()->where('subject_type', $type)->where('old_slug', $slug)
            ->when($subjectId !== null, static fn ($query) => $query->where('subject_id', '!=', $subjectId))
            ->exists();
    }

    /**
     * @return list<string>
     */
    public function history(string $type, int $subjectId): array
    {
        return SlugRedirect::query()->where('subject_type', $type)->where('subject_id', $subjectId)->orderByDesc('id')->pluck('old_slug')->all();
    }
}

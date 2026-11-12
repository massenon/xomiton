<?php

declare(strict_types=1);

namespace App\Application\Content\ConversationsBd;

use App\Models\BdBubble;
use App\Models\BdCharacter;
use App\Models\BdConversation;
use App\Models\BdPanel;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lecture publique des conversations en BD (P20-06) : seules les conversations **publiées** existent pour le public
 * (brouillon, proposée, refusée, retirée → 404 neutre, comme une adresse inconnue). Prépare aussi les cases pour le
 * rendu (`x-bd.panel`, mode séance, futur export) : image de la scène si le fichier existe (sinon repli rose pâle avec la
 * description), description de la scène (texte alternatif), bulles au format de la grammaire dans l'ordre de lecture.
 */
final class ConversationsBdCatalog
{
    /** @return Collection<int, BdConversation> */
    public function published(): Collection
    {
        return BdConversation::query()->published()->ordered()->withCount('panels')->with('panels.scene')->get();
    }

    public function find(string $slug): ?BdConversation
    {
        return BdConversation::query()->published()->where('slug', $slug)->with(['panels.scene', 'panels.bubbles'])->first();
    }

    /** @return Collection<int, BdConversation> */
    public function others(BdConversation $conversation, int $limit = 3): Collection
    {
        return BdConversation::query()->published()->whereKeyNot($conversation->getKey())->ordered()->withCount('panels')->with('panels.scene')->limit($limit)->get();
    }

    /**
     * Cases prêtes pour le rendu.
     *
     * @return list<array{n: int, image: string|null, alt: string, bubbles: list<array<string, mixed>>, thoughts: bool}>
     */
    public function panels(BdConversation $conversation): array
    {
        $out = [];

        foreach ($conversation->panels->values() as $index => $panel) {
            /** @var BdPanel $panel */
            $bubbles = $panel->bubbles->map(static fn (BdBubble $bubble): array => $bubble->toGrammar())->values()->all();
            $out[] = [
                'n' => $index + 1,
                'image' => $this->image($panel),
                'alt' => $this->alt($panel),
                'bubbles' => $bubbles,
                'thoughts' => in_array('pensee', array_column($bubbles, 'type'), true),
            ];
        }

        return $out;
    }

    /** Image de la case, seulement si le fichier existe (les illustrations arrivent après l'import). */
    public function image(BdPanel $panel): ?string
    {
        $path = $panel->scene?->image_path;

        return MediaUrl::exists($path) ? MediaUrl::resolve($path, '') : null;
    }

    public function alt(BdPanel $panel): string
    {
        return trim((string) ($panel->scene_description ?: $panel->scene?->description));
    }

    /** Couverture (image de partage) : la première case illustrée, sinon null. */
    public function cover(BdConversation $conversation): ?string
    {
        foreach ($conversation->panels as $panel) {
            $image = $this->image($panel);
            if ($image !== null) {
                return $image;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public function names(): array
    {
        return BdCharacter::names();
    }

    /**
     * Texte lu de toute la conversation (description de la page, recherche plein texte plus tard).
     *
     * @param  list<array{bubbles: list<array<string, mixed>>}>  $panels
     */
    public function plainText(array $panels): string
    {
        $texts = [];

        foreach ($panels as $panel) {
            foreach ($panel['bubbles'] as $bubble) {
                $text = trim((string) ($bubble['texte'] ?? ''));
                if ($text !== '') {
                    $texts[] = $text;
                }
            }
        }

        return implode(' ', $texts);
    }
}

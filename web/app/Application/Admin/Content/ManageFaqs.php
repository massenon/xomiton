<?php

declare(strict_types=1);

namespace App\Application\Admin\Content;

use App\Models\Faq;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * FAQ de l'administration (P12-03, permission `faqs.manage`, policy FaqPolicy) : question / réponse (60 mots au plus,
 * ContentRules), catégorie, publication, « accueil » et ordre (monter / descendre). Le slug de l'adresse `/aide/{slug}`
 * est dérivé de la question à la création et reste stable ensuite (modèle Faq).
 */
final class ManageFaqs
{
    public function __construct(private readonly PublicContentCache $cache) {}

    /**
     * @param  array<string, mixed>  $data  données validées par ContentRules::faq()
     */
    public function save(?Faq $faq, array $data, User $actor): Faq
    {
        $faq ??= new Faq;
        $creating = ! $faq->exists;

        $faq->fill([
            'question' => trim((string) $data['question']),
            'answer' => trim((string) $data['answer']),
            'category_id' => $data['category_id'] ?? null,
            'is_published' => (bool) ($data['is_published'] ?? false),
            'show_on_home' => (bool) ($data['show_on_home'] ?? false),
        ]);

        if ($creating) {
            $faq->position = (int) Faq::query()->max('position') + 1;
        }

        $faq->save();
        Audit::log($creating ? 'content.faq_created' : 'content.faq_updated', $faq, null, $actor);
        $this->cache->flush();

        return $faq;
    }

    public function setPublished(Faq $faq, bool $published, User $actor): Faq
    {
        $faq->forceFill(['is_published' => $published])->save();
        Audit::log($published ? 'content.faq_published' : 'content.faq_unpublished', $faq, null, $actor);
        $this->cache->flush();

        return $faq;
    }

    /**
     * Échange la position de la question avec sa voisine (−1 : monter, +1 : descendre) dans l'ordre global de la liste.
     */
    public function move(Faq $faq, int $direction, User $actor): void
    {
        DB::transaction(function () use ($faq, $direction): void {
            $ordered = Faq::query()->ordered()->get(['id', 'position']);
            $index = $ordered->search(fn (Faq $row): bool => $row->getKey() === $faq->getKey());
            $neighbour = $index === false ? null : $ordered->get($index + ($direction < 0 ? -1 : 1));

            if ($index === false || $neighbour === null) {
                return;
            }

            // Positions renumérotées de 1 à n : deux questions de même rang ne bloquent jamais l'échange.
            $ids = $ordered->pluck('id')->all();
            [$ids[$index], $ids[$index + ($direction < 0 ? -1 : 1)]] = [$neighbour->getKey(), $faq->getKey()];

            foreach ($ids as $position => $id) {
                Faq::query()->whereKey($id)->update(['position' => $position + 1]);
            }
        });

        Audit::log('content.faq_moved', $faq, null, $actor);
        $this->cache->flush();
    }

    public function delete(Faq $faq, User $actor): void
    {
        $faq->delete();
        Audit::log('content.faq_deleted', $faq, null, $actor);
        $this->cache->flush();
    }
}

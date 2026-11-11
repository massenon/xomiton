<?php

declare(strict_types=1);

namespace App\Application\Admin\Newsletter;

use App\Enums\CampaignStatus;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterInterest;
use App\Models\NewsletterTag;
use App\Models\Profile;
use App\Models\User;
use App\Support\Audit;
use DomainException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Tags de segmentation et centres d'intérêt de la newsletter (P14-02, `newsletter.manage_subscribers`) : création,
 * renommage et suppression. Les tables n'ont que `slug` et `label` : pas d'ordre (tri par libellé) ni d'état actif.
 *
 * - Le slug est dérivé du libellé à la création (unique, 40 caractères au plus) puis **ne change plus** : il est écrit
 *   dans les colonnes json des abonné·es, les segments de campagne et `profiles.interests`.
 * - Suppression REFUSÉE tant que l'élément est utilisé (option sûre : la cascade des tables de liaison retirerait en
 *   silence le tag ou l'intérêt à chaque abonné·e, et un segment de campagne à envoyer ne viserait plus personne).
 *   « Utilisé » = au moins un·e abonné·e, un profil (intérêts), ou une campagne qui n'est pas encore terminée
 *   (brouillon, planifiée, en cours). Les campagnes envoyées ou annulées gardent le slug dans leur historique.
 * - Journal « newsletter_taxonomy.created », « .updated », « .deleted » : type, slug, libellé (aucune donnée d'abonné·e).
 */
final class ManageNewsletterTaxonomy
{
    public const TAG = 'tag';

    public const INTEREST = 'interest';

    public const KINDS = [self::TAG, self::INTEREST];

    public const LABEL_MIN = 2;

    public const LABEL_MAX = 60;

    public const SLUG_MAX = 40;

    /**
     * @throws DomainException libellé vide, sans lettre ni chiffre, ou déjà pris
     */
    public function create(User $by, string $kind, string $label): NewsletterTag|NewsletterInterest
    {
        $this->authorize($by);
        $label = $this->cleanLabel($label);
        $this->assertLabelFree($kind, $label, null);

        $slug = $this->uniqueSlug($kind, $label);
        $item = $kind === self::TAG
            ? NewsletterTag::query()->create(['slug' => $slug, 'label' => $label])
            : NewsletterInterest::query()->create(['slug' => $slug, 'label' => $label]);

        Audit::log('newsletter_taxonomy.created', $item, self::detail($kind, $item), $by);

        return $item;
    }

    /**
     * Renomme (le slug reste).
     *
     * @throws DomainException
     */
    public function rename(User $by, string $kind, NewsletterTag|NewsletterInterest $item, string $label): NewsletterTag|NewsletterInterest
    {
        $this->authorize($by);
        $label = $this->cleanLabel($label);
        $this->assertLabelFree($kind, $label, (int) $item->getKey());

        $before = $item->label;
        $item->forceFill(['label' => $label])->save();

        Audit::log('newsletter_taxonomy.updated', $item, self::detail($kind, $item).sprintf(' ; ancien libellé=%s', $before), $by);

        return $item;
    }

    /**
     * @return bool faux quand l'élément est utilisé : rien n'est supprimé
     */
    public function delete(User $by, string $kind, NewsletterTag|NewsletterInterest $item): bool
    {
        $this->authorize($by);

        if (array_sum($this->usage($kind, $item)) > 0) {
            return false;
        }

        $detail = self::detail($kind, $item);
        $item->delete();
        Audit::log('newsletter_taxonomy.deleted', null, $detail, $by);

        return true;
    }

    /**
     * Où l'élément sert : abonné·es (liaison), profils (intérêts seulement) et campagnes non terminées.
     *
     * @return array{subscribers: int, profiles: int, campaigns: int}
     */
    public function usage(string $kind, NewsletterTag|NewsletterInterest $item): array
    {
        $slug = (string) $item->slug;
        $key = $kind === self::TAG ? 'tags' : 'interests';

        return [
            'subscribers' => $item->subscribers()->count(),
            'profiles' => $kind === self::INTEREST ? Profile::query()->whereJsonContains('interests', $slug)->count() : 0,
            'campaigns' => NewsletterCampaign::query()
                ->whereNotIn('status', [CampaignStatus::Sent->value, CampaignStatus::Cancelled->value])
                ->whereJsonContains('segment->'.$key, $slug)
                ->count(),
        ];
    }

    public static function modelFor(string $kind, int $id): NewsletterTag|NewsletterInterest|null
    {
        return $kind === self::TAG ? NewsletterTag::query()->find($id) : ($kind === self::INTEREST ? NewsletterInterest::query()->find($id) : null);
    }

    private function authorize(User $by): void
    {
        Gate::forUser($by)->authorize('newsletter.manage_subscribers');
    }

    private function cleanLabel(string $label): string
    {
        $label = trim((string) preg_replace('/\s+/u', ' ', $label));

        if (mb_strlen($label) < self::LABEL_MIN || mb_strlen($label) > self::LABEL_MAX) {
            throw new DomainException((string) __('admin_newsletter_taxonomy.errors.label_length', ['min' => self::LABEL_MIN, 'max' => self::LABEL_MAX]));
        }

        if (Str::slug($label) === '') {
            throw new DomainException((string) __('admin_newsletter_taxonomy.errors.label_empty'));
        }

        return $label;
    }

    private function assertLabelFree(string $kind, string $label, ?int $ignoreId): void
    {
        $query = $kind === self::TAG ? NewsletterTag::query() : NewsletterInterest::query();
        $taken = $query->whereRaw('LOWER(label) = ?', [mb_strtolower($label)])
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($taken) {
            throw new DomainException((string) __('admin_newsletter_taxonomy.errors.label_taken'));
        }
    }

    private function uniqueSlug(string $kind, string $label): string
    {
        $base = Str::limit(Str::slug($label), self::SLUG_MAX, '');
        $base = rtrim($base, '-');
        $slug = $base;

        for ($n = 2; $this->slugExists($kind, $slug); $n++) {
            $suffix = '-'.$n;
            $slug = rtrim(Str::limit($base, self::SLUG_MAX - strlen($suffix), ''), '-').$suffix;
        }

        return $slug;
    }

    private function slugExists(string $kind, string $slug): bool
    {
        return ($kind === self::TAG ? NewsletterTag::query() : NewsletterInterest::query())->where('slug', $slug)->exists();
    }

    private static function detail(string $kind, NewsletterTag|NewsletterInterest $item): string
    {
        return sprintf('type=%s ; slug=%s ; libellé=%s', $kind, $item->slug, $item->label);
    }
}

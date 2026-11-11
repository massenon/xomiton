<?php

declare(strict_types=1);

namespace App\Application\Admin\Search;

use App\Enums\DirectoryRole;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Commune;
use App\Models\DirectoryContact;
use App\Models\Event;
use App\Models\Group;
use App\Models\Thematique;
use App\Models\User;
use App\Models\ViolenceType;
use App\Models\Zone;
use App\Policies\Scopes\AlertScope;
use App\Policies\Scopes\CaseRecordScope;
use App\Support\ClearColumns;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Recherche globale d'administration (P10-08, prototype admin/recherche.php) : utilisateur·rices, dossiers, alertes,
 * annuaire, thématiques, réseau (événements, groupes) et journal d'audit, **groupés**. Chaque groupe n'existe que pour
 * qui a la permission de l'écran correspondant (menu d'administration) et respecte le périmètre de la personne
 * (CaseRecordScope, AlertScope). **Jamais sur une colonne chiffrée** (ADR-33) : le prototype cherchait dans le récit
 * de l'alerte ; ici seules des colonnes en clair sont interrogées, vérifiées par ClearColumns avant chaque requête
 * (récits, notes, GPS, contacts, adresses exactes restent introuvables). Le terme n'est ni journalisé, ni écrit en base.
 */
final class GlobalSearch
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 80;

    /** Résultats affichés par groupe. */
    public const PER_GROUP = 6;

    /**
     * Colonnes en clair interrogées, par modèle (contrat testé : aucune n'est chiffrée).
     *
     * @var array<class-string<Model>, list<string>>
     */
    public const COLUMNS = [
        User::class => ['name', 'email', 'pseudo'],
        CaseRecord::class => ['code', 'referral'],
        Alert::class => ['category', 'pseudo', 'tracking_code'],
        DirectoryContact::class => ['name', 'org'],
        Thematique::class => ['title', 'summary'],
        Event::class => ['title', 'place', 'description'],
        Group::class => ['name', 'description'],
        AuditLog::class => ['actor', 'action', 'detail'],
    ];

    /** Terme nettoyé (espaces réduits, longueur bornée) ; chaîne vide si trop court pour chercher. */
    public static function normalize(?string $term): string
    {
        $term = trim((string) preg_replace('/\s+/u', ' ', (string) $term));
        $term = Str::limit($term, self::MAX_LENGTH, '');

        return mb_strlen($term) >= self::MIN_LENGTH ? $term : '';
    }

    /**
     * @return list<array{key: string, icon: string, color: string, url: string|null, total: int, rows: list<array{title: string, detail: string, url: string|null}>}>
     */
    public function search(User $user, string $term): array
    {
        $term = self::normalize($term);

        if ($term === '') {
            return [];
        }

        $groups = [];

        foreach ([
            'users' => fn () => $user->can('users.view') ? $this->users($term) : null,
            'cases' => fn () => $user->can('cases.view_all') ? $this->cases($user, $term) : null,
            'alerts' => fn () => ($user->can('alerts.view') || $user->can('alerts.view_routed')) ? $this->alerts($user, $term) : null,
            'directory' => fn () => $user->can('directory.manage') ? $this->directory($term) : null,
            'thematiques' => fn () => $user->can('thematiques.manage') ? $this->thematiques($term) : null,
            'network' => fn () => $user->can('posts.moderate') ? $this->network($term) : null,
            'journal' => fn () => $user->can('audit_logs.view') ? $this->journal($term) : null,
        ] as $key => $resolve) {
            $group = $resolve();

            if ($group !== null && $group['total'] > 0) {
                $groups[] = ['key' => $key, 'icon' => self::ICONS[$key][0], 'color' => self::ICONS[$key][1]] + $group;
            }
        }

        return $groups;
    }

    /** @var array<string, array{0: string, 1: string}> */
    private const ICONS = [
        'users' => ['people', 'primary'],
        'cases' => ['journal-bookmark', 'success'],
        'alerts' => ['bell', 'danger'],
        'directory' => ['person-lines-fill', 'info'],
        'thematiques' => ['journal-text', 'violet'],
        'network' => ['diagram-3', 'primary'],
        'journal' => ['clock-history', 'neutral'],
    ];

    /**
     * @return array{url: string|null, total: int, rows: list<array{title: string, detail: string, url: string|null}>}
     */
    private function users(string $term): array
    {
        $query = $this->contains(User::query(), User::class, $term);

        return $this->group('utilisateurs', $query, static fn (User $u): array => [
            'title' => (string) ($u->name ?: $u->pseudo),
            'detail' => implode(' · ', array_filter([(string) $u->email, $u->getRoleNames()->first(), $u->status->label()])),
            'url' => self::route('utilisateurs', ['q' => (string) $u->email]),
        ], fn (Builder $q) => $q->orderBy('name')->with('roles'));
    }

    /**
     * @return array{url: string|null, total: int, rows: list<array{title: string, detail: string, url: string|null}>}
     */
    private function cases(User $user, string $term): array
    {
        ClearColumns::assert(new CaseRecord, self::COLUMNS[CaseRecord::class]);
        $like = LikePattern::contains($term);
        $query = CaseRecordScope::visibleTo($user)->where(function (Builder $where) use ($like): void {
            foreach (self::COLUMNS[CaseRecord::class] as $column) {
                $where->orWhereRaw(LikePattern::clause('cases.'.$column), [$like]);
            }

            $where->orWhereIn('zone_id', Zone::query()->select('id')->whereRaw(LikePattern::clause('name'), [$like]))
                ->orWhereIn('commune_id', Commune::query()->select('id')->whereRaw(LikePattern::clause('name'), [$like]))
                ->orWhereIn('violence_type_id', ViolenceType::query()->select('id')->whereRaw(LikePattern::clause('label'), [$like]))
                ->orWhereIn('collected_by', User::query()->select('id')->whereRaw(LikePattern::clause('pseudo'), [$like]));
        });

        return $this->group('registres', $query, static fn (CaseRecord $c): array => [
            'title' => (string) $c->code,
            'detail' => implode(' · ', array_filter([$c->kind->label(), $c->violenceType?->label, $c->commune?->name ?? $c->zone?->name, $c->status->label()])),
            'url' => self::route('registres', ['q' => (string) $c->code]),
        ], fn (Builder $q) => $q->latest('id')->with(['zone', 'commune', 'violenceType']));
    }

    /**
     * @return array{url: string|null, total: int, rows: list<array{title: string, detail: string, url: string|null}>}
     */
    private function alerts(User $user, string $term): array
    {
        ClearColumns::assert(new Alert, self::COLUMNS[Alert::class]);
        $like = LikePattern::contains($term);
        $id = preg_match('/^#?(\d{1,9})$/', $term, $matches) === 1 ? (int) $matches[1] : null;
        $query = AlertScope::routedTo($user)->where(function (Builder $where) use ($like, $id): void {
            foreach (self::COLUMNS[Alert::class] as $column) {
                $where->orWhereRaw(LikePattern::clause('alerts.'.$column), [$like]);
            }

            $where->orWhereIn('zone_id', Zone::query()->select('id')->whereRaw(LikePattern::clause('name'), [$like]))
                ->orWhereIn('commune_id', Commune::query()->select('id')->whereRaw(LikePattern::clause('name'), [$like]));

            if ($id !== null) {
                $where->orWhere('alerts.id', $id);
            }
        });

        return $this->group('alertes', $query, static fn (Alert $a): array => [
            'title' => (string) __('admin_search.alert_title', ['id' => $a->id, 'type' => $a->type->label()]),
            'detail' => implode(' · ', array_filter([$a->commune?->name ?? $a->zone?->name, $a->status->label()])),
            'url' => self::route('alertes', ['q' => '#'.$a->id]),
        ], fn (Builder $q) => $q->latestFirst()->with(['zone', 'commune']));
    }

    /**
     * @return array{url: string|null, total: int, rows: list<array{title: string, detail: string, url: string|null}>}
     */
    private function directory(string $term): array
    {
        ClearColumns::assert(new DirectoryContact, self::COLUMNS[DirectoryContact::class]);
        $like = LikePattern::contains($term);
        $query = DirectoryContact::query()->where(function (Builder $where) use ($like, $term): void {
            foreach (self::COLUMNS[DirectoryContact::class] as $column) {
                $where->orWhereRaw(LikePattern::clause('directory_contacts.'.$column), [$like]);
            }

            $where->orWhereIn('commune_id', Commune::query()->select('id')->whereRaw(LikePattern::clause('name'), [$like]));

            // La fonction est une énumération stockée par son code (`point_focal`) : on cherche dans son libellé affiché.
            $roles = array_values(array_map(
                static fn (DirectoryRole $role): string => $role->value,
                array_filter(DirectoryRole::cases(), static fn (DirectoryRole $role): bool => mb_stripos($role->label(), $term) !== false),
            ));

            if ($roles !== []) {
                $where->orWhereIn('directory_contacts.role', $roles);
            }
        });

        return $this->group('annuaire', $query, static fn (DirectoryContact $c): array => [
            'title' => (string) $c->name,
            'detail' => implode(' · ', array_filter([$c->role->label(), (string) $c->org, $c->commune?->name])),
            'url' => self::route('annuaire', ['q' => (string) $c->name]),
        ], fn (Builder $q) => $q->orderBy('name')->with('commune'));
    }

    /**
     * @return array{url: string|null, total: int, rows: list<array{title: string, detail: string, url: string|null}>}
     */
    private function thematiques(string $term): array
    {
        $query = $this->contains(Thematique::query(), Thematique::class, $term);

        return $this->group('contenu', $query, static fn (Thematique $t): array => [
            'title' => (string) $t->title,
            'detail' => Str::limit((string) $t->summary, 110),
            'url' => self::route('contenu', ['tab' => 'thematiques']),
        ], fn (Builder $q) => $q->orderBy('title'));
    }

    /**
     * Événements puis groupes. Le total additionne les deux familles ; les lignes affichées sont les premières de chacune.
     *
     * @return array{url: string|null, total: int, rows: list<array{title: string, detail: string, url: string|null}>}
     */
    private function network(string $term): array
    {
        $events = $this->contains(Event::query(), Event::class, $term);
        $groups = $this->contains(Group::query(), Group::class, $term);
        $rows = [];

        foreach ((clone $events)->orderBy('starts_at', 'desc')->limit(self::PER_GROUP)->get() as $event) {
            $rows[] = ['title' => (string) $event->title, 'detail' => implode(' · ', array_filter([$event->starts_at?->format('d/m/Y'), (string) $event->place])), 'url' => self::route('reseau')];
        }

        foreach ((clone $groups)->orderBy('name')->limit(self::PER_GROUP)->get() as $group) {
            $rows[] = ['title' => (string) __('admin_search.group_title', ['name' => $group->name]), 'detail' => (string) $group->privacy->label(), 'url' => self::route('reseau')];
        }

        return ['url' => self::route('reseau'), 'total' => $events->count() + $groups->count(), 'rows' => array_slice($rows, 0, self::PER_GROUP)];
    }

    /**
     * @return array{url: string|null, total: int, rows: list<array{title: string, detail: string, url: string|null}>}
     */
    private function journal(string $term): array
    {
        $query = $this->contains(AuditLog::query(), AuditLog::class, $term);

        return $this->group('journal', $query, static fn (AuditLog $l): array => [
            'title' => (string) $l->action.' · '.$l->actor,
            'detail' => implode(' · ', array_filter([Str::limit((string) $l->detail, 90), $l->created_at?->format('d/m/Y H:i')])),
            'url' => self::route('journal', ['ac' => (string) $l->action]),
        ], fn (Builder $q) => $q->latest('id'));
    }

    /**
     * Requête « une des colonnes en clair contient le terme ».
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  class-string<TModel>  $model
     * @return Builder<TModel>
     */
    private function contains(Builder $query, string $model, string $term): Builder
    {
        $columns = self::COLUMNS[$model];
        ClearColumns::assert($query->getModel(), $columns);
        $like = LikePattern::contains($term);

        return $query->where(static function (Builder $where) use ($columns, $like): void {
            foreach ($columns as $column) {
                $where->orWhereRaw(LikePattern::clause($where->qualifyColumn($column)), [$like]);
            }
        });
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): array{title: string, detail: string, url: string|null}  $row
     * @param  callable(Builder<TModel>): Builder<TModel>  $order
     * @return array{url: string|null, total: int, rows: list<array{title: string, detail: string, url: string|null}>}
     */
    private function group(string $route, Builder $query, callable $row, callable $order): array
    {
        return [
            'url' => self::route($route),
            'total' => (clone $query)->toBase()->getCountForPagination(),
            'rows' => $order(clone $query)->limit(self::PER_GROUP)->get()->map($row)->values()->all(),
        ];
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private static function route(string $name, array $parameters = []): ?string
    {
        return Route::has('admin.'.$name) ? route('admin.'.$name, $parameters) : null;
    }
}

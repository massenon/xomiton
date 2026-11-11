<?php

declare(strict_types=1);

namespace App\Domain\Alerts\ValueObjects;

use App\Enums\DirectoryRole;

/**
 * Carte besoin → rôle d'annuaire (`needs.routed_role`, P1-01 : aucune liste codée en dur). Un besoin sans rôle
 * (hébergement, sécurité…) ou inconnu est ignoré au niveau 1 du routage (api.php l.17).
 */
final readonly class NeedRoleMap
{
    /**
     * @param  array<string, DirectoryRole|null>  $roles  slug => rôle ciblé (null : point focal)
     */
    public function __construct(private array $roles = []) {}

    /**
     * @param  iterable<array{slug: string, routed_role: string|DirectoryRole|null}|object>  $needs  lignes de Referentials::needs() ou modèles Need
     */
    public static function fromNeeds(iterable $needs): self
    {
        $roles = [];

        foreach ($needs as $need) {
            $slug = is_array($need) ? ($need['slug'] ?? null) : ($need->slug ?? null);
            $role = is_array($need) ? ($need['routed_role'] ?? null) : ($need->routed_role ?? null);

            if (! is_string($slug) || $slug === '') {
                continue;
            }

            $roles[$slug] = $role instanceof DirectoryRole ? $role : (is_string($role) ? DirectoryRole::tryFrom($role) : null);
        }

        return new self($roles);
    }

    public function roleFor(string $slug): ?DirectoryRole
    {
        return $this->roles[$slug] ?? null;
    }

    public function has(string $slug): bool
    {
        return array_key_exists($slug, $this->roles);
    }

    /** Premier besoin (slug) dont le rôle ciblé est `$role`, pour la demande de rappel depuis l'annuaire. */
    public function needFor(DirectoryRole $role): ?string
    {
        foreach ($this->roles as $slug => $target) {
            if ($target === $role) {
                return $slug;
            }
        }

        return null;
    }
}

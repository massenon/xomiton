<?php

declare(strict_types=1);

namespace App\Http\Requests\Member;

use App\Enums\PostTag;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pages du fil de l'espace membre (P7-03, P7-06 : `/fil`, `/fil/enregistres`, `/fil/{ulid}`). Lecture seule : aucune
 * règle bloquante. `?tag=` inconnu ou vide = « Tout » (le prototype ignorait un filtre inconnu ; l'API, elle, répond
 * 422). L'accès au réseau (`social.access`) est vérifié par le contrôleur : sans lui, la page s'affiche avec ses accès
 * rapides de crise et un fil vide, jamais un 403 (redirection d'après connexion vers /fil).
 */
final class FeedPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function viewer(): User
    {
        $user = $this->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    public function tag(): ?PostTag
    {
        $tag = $this->query('tag');

        return is_string($tag) && $tag !== '' ? PostTag::tryFrom($tag) : null;
    }
}

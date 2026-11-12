<?php

declare(strict_types=1);

namespace App\Application\Content\ViolencesNumeriques;

use App\Enums\ProfileVisibility;
use App\Enums\UserStatus;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Émetteur officiel des publications de la plateforme dans le fil (P19-11 : BD du pilote publiées « comme une
 * actualité »). Aucun émetteur n'existait : le fil n'affiche que des comptes (`posts.user_id` ; `posts.page_id` n'est pas
 * rendu par PostResource) et aucune page « Xomiton » n'est semée en production. D'où un **compte système minimal** :
 *
 *   - adresse en `.invalid` (EMAIL, RFC 2606 : jamais livrable, donc aucune réinitialisation de mot de passe possible),
 *     mot de passe aléatoire jamais conservé : personne ne peut s'y connecter ;
 *   - pseudonyme « xomiton » (repli `xomiton_officiel` s'il est déjà pris), nom « Xomiton », avatar = icône de l'appli ;
 *   - aucun rôle ni permission (le compte ne fait rien d'autre que porter les publications créées par la commande) ;
 *   - profil visible de la communauté, non trouvable par la recherche de membres.
 *
 * Retrouvé par son adresse (même supprimé : il est rétabli) ; idempotent.
 */
final class OfficialAccount
{
    public const AVATAR = 'assets/images/xomiton/brand/icone-appli-bleu.svg';

    public const EMAIL = 'compte-officiel@xomiton.invalid';

    /** @var list<string> */
    private const PSEUDOS = ['xomiton', 'xomiton_officiel', 'equipe_xomiton'];

    public static function email(): string
    {
        return self::EMAIL;
    }

    public function find(): ?User
    {
        return User::query()->where('email', self::email())->first();
    }

    public function resolve(): User
    {
        $user = User::query()->withTrashed()->where('email', self::email())->first();

        if ($user === null) {
            $user = new User;
            $user->forceFill([
                'email' => self::email(),
                'pseudo' => $this->freePseudo(),
                'name' => 'Xomiton',
                'password' => Str::random(64),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ])->save();
        } elseif ($user->trashed()) {
            $user->restore();
        }

        Profile::query()->updateOrCreate(['user_id' => $user->getKey()], [
            'bio' => (string) __('pages/bd.official_account_bio'),
            'avatar_path' => self::AVATAR,
            'visibility' => ProfileVisibility::Community,
            'searchable' => false,
            'show_zone' => false,
        ]);

        return $user;
    }

    private function freePseudo(): string
    {
        foreach (self::PSEUDOS as $pseudo) {
            if (! User::query()->withTrashed()->where('pseudo', $pseudo)->exists()) {
                return $pseudo;
            }
        }

        return 'xomiton_'.Str::lower(Str::random(6));
    }
}

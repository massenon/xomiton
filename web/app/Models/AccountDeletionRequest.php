<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Demande publique de suppression de compte (page /supprimer-mon-compte) : trace sans donnée personnelle lisible
 * (empreinte HMAC de l'adresse, SHA-256 du jeton). Voir App\Application\Account\PublicAccountDeletion.
 *
 * @property int $id
 * @property string $email_hash
 * @property string|null $token_hash
 * @property string $outcome
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 */
class AccountDeletionRequest extends Model
{
    public const SENT = 'sent';

    public const NO_ACCOUNT = 'no_account';

    public const BLOCKED = 'blocked';

    public const DONE = 'done';

    /** @var list<string> */
    protected $fillable = ['email_hash', 'token_hash', 'outcome', 'user_id', 'expires_at', 'confirmed_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }
}

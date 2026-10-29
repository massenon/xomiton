<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande du rôle pair faite par un membre depuis son profil (D-MOB-15, table `role_requests`). Une seule demande en
 * cours par compte (`open_user_id` unique tant que `pending`). Jamais d'attribution automatique : le rôle n'est donné
 * que par la décision d'un compte habilité (App\Application\Account\RoleRequests\ReviewRoleRequest).
 */
class RoleRequest extends Model
{
    use HasUlid;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const ROLE_PEER = 'pair';

    public const REASON_MAX = 200;

    /** @var list<string> */
    protected $fillable = [
        'ulid', 'user_id', 'open_user_id', 'role', 'status', 'reason', 'decided_by', 'decided_at', 'decision_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * @param  Builder<RoleRequest>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }
}

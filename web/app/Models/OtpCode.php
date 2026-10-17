<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OtpPurpose;
use Database\Factories\OtpCodeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    /** @use HasFactory<OtpCodeFactory> */
    use HasFactory;

    /**
     * Seul created_at est conservé (dictionnaire : [sans timestamps : created_at seul]).
     */
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'purpose',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
        'ip',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'code_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Les codes ne sont jamais stockés en clair : SHA-256 du code saisi.
     */
    public static function hash(string $code): string
    {
        return hash('sha256', $code);
    }

    /**
     * Codes encore utilisables : ni consommés ni expirés.
     *
     * @param  Builder<OtpCode>  $query
     */
    public function scopeValid(Builder $query): void
    {
        $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    /**
     * @param  Builder<OtpCode>  $query
     */
    public function scopeForEmail(Builder $query, string $email, OtpPurpose $purpose): void
    {
        $query->where('email', mb_strtolower($email))->where('purpose', $purpose);
    }

    /**
     * Codes à purger (expirés depuis plus de 24 h, DATABASE_ENGINEERING.md §6).
     *
     * @param  Builder<OtpCode>  $query
     */
    public function scopeExpiredForPurge(Builder $query): void
    {
        $query->where('expires_at', '<', now()->subDay());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function matches(string $code): bool
    {
        return hash_equals($this->code_hash, self::hash($code));
    }
}

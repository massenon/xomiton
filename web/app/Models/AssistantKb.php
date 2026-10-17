<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AssistantKbFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Entrée de la base de connaissances de l'assistant (DATABASE_DICTIONARY.md §3 `assistant_kb`).
 */
class AssistantKb extends Model
{
    /** @use HasFactory<AssistantKbFactory> */
    use HasFactory;

    /** Le dictionnaire fige le nom au singulier. */
    protected $table = 'assistant_kb';

    /** @var list<string> */
    protected $fillable = [
        'question',
        'answer',
        'keywords',
        'is_active',
        'hits_count',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'is_active' => 'boolean',
            'hits_count' => 'integer',
        ];
    }

    /** @return HasMany<AssistantMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class, 'matched_kb_id');
    }

    /** @param  Builder<AssistantKb>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Appariement simple : l'entrée répond si l'un de ses mots-clés apparaît dans le message
     * (comparaison insensible à la casse et aux accents).
     */
    public function matches(string $message): bool
    {
        $haystack = Str::lower(Str::ascii($message));

        foreach ($this->keywords ?? [] as $keyword) {
            $needle = trim(Str::lower(Str::ascii((string) $keyword)));

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function recordHit(): void
    {
        $this->increment('hits_count');
    }
}

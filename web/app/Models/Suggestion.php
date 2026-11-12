<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SuggestionCategory;
use App\Enums\SuggestionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Suggestion de la boîte à idées (P18-14 ; DATABASE_DICTIONARY.md `suggestions`). Anonyme (`is_anonymous`) : le compte
 * reste en base pour le limiteur et la réponse de l'équipe, mais l'administration ne l'affiche jamais.
 */
class Suggestion extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'category',
        'message',
        'is_anonymous',
        'status',
        'reply',
        'replied_by',
        'replied_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'replied_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function suggestionCategory(): SuggestionCategory
    {
        return SuggestionCategory::tryFrom((string) $this->getAttributes()['category']) ?? SuggestionCategory::Other;
    }

    public function suggestionStatus(): SuggestionStatus
    {
        return SuggestionStatus::tryFrom((string) $this->getAttributes()['status']) ?? SuggestionStatus::New;
    }
}

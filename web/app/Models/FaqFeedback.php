<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réponse à « Cet article vous a-t-il aidé·e ? » d'une question du centre d'aide (P4-05, DATABASE_DICTIONARY.md §3
 * `faq_feedback`). Une ligne par question et par votant·e (`fingerprint` = KeyedHash, ni IP ni identifiant en clair) ;
 * écrite par RecordFaqFeedback, lue par le compteur « utile » de /admin/contenu (P12-03).
 */
class FaqFeedback extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'faq_feedback';

    /** @var list<string> */
    protected $fillable = [
        'faq_id',
        'helpful',
        'fingerprint',
    ];

    /** @var list<string> */
    protected $hidden = ['fingerprint'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'helpful' => 'boolean',
        ];
    }

    /** @return BelongsTo<Faq, $this> */
    public function faq(): BelongsTo
    {
        return $this->belongsTo(Faq::class);
    }
}

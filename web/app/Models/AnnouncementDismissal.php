<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fermeture d'une annonce globale par un compte (P18-13) : l'annonce ne revient plus, ni sur le web ni dans l'application.
 */
class AnnouncementDismissal extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['announcement_id', 'user_id', 'dismissed_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['dismissed_at' => 'datetime'];
    }

    /** @return BelongsTo<Announcement, $this> */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Compteur quotidien d'un événement de contenu (migration content_events) : ni identifiant, ni IP, ni agent utilisateur.
 */
class ContentEvent extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['kind', 'day', 'hits', 'updated_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['day' => 'date', 'hits' => 'integer', 'updated_at' => 'datetime'];
    }
}

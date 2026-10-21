<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ancien slug d'une ressource ou d'une thématique (P12-03) : l'adresse publique correspondante redirige en 301 vers la
 * fiche actuelle (App\Http\Middleware\RedirectRenamedSlug). Un slug n'est jamais réattribué à une autre fiche.
 */
class SlugRedirect extends Model
{
    public const TYPE_RESOURCE = 'resource';

    public const TYPE_THEMATIQUE = 'thematique';

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['subject_type', 'subject_id', 'old_slug'];
}

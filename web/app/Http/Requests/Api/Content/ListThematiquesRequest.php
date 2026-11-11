<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Content;

use App\Http\Requests\Api\Content\Concerns\ContentListingRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/v1/thematiques (P6-08, simulateur `thematiques`) : `?categorie=` et `?q=` ; liste complète des fiches
 * publiées (bornée à xomiton.content.max_per_load), gardée hors connexion par l'application (ETag, cache Hive).
 */
final class ListThematiquesRequest extends FormRequest
{
    use ContentListingRequest;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return $this->listingRules();
    }
}

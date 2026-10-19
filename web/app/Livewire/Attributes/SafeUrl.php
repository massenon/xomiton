<?php

declare(strict_types=1);

namespace App\Livewire\Attributes;

use App\Support\TypedValue;
use Attribute;
use Livewire\Attributes\Url;
use TypeError;
use ValueError;

/**
 * `#[Url]` tolérant (P10-02, revue admin-socle) : une valeur d'URL mal formée pour le type de la propriété
 * (`?par=abc`, `?q[]=x`, `?tri[]=x`, `?ac[]=x`) est ignorée au lieu de lever une TypeError à l'hydratation (500) ;
 * la propriété garde sa valeur par défaut. Une chaîne est relue brute dans l'URL (Livewire la décoderait en JSON :
 * `?q=1.50` resterait « 1.50 »). Mêmes arguments que `#[Url]` (as, except, history, keep, nullable).
 */
#[Attribute]
final class SafeUrl extends Url
{
    public function setValue($value, ?bool $nullable = false): void
    {
        $target = $this->getSubTarget() ?? $this->getComponent();
        $property = (string) ($this->getSubName() ?? $this->getName());
        $raw = $this->getFromUrlQueryString($this->urlName());
        $candidate = is_string($raw) && TypedValue::typeName($target, $property) !== 'array' ? $raw : $value;
        $result = TypedValue::coerce($target, $property, $candidate);

        if (! $result['ok']) {
            return;
        }

        try {
            parent::setValue($result['value'], $nullable);
        } catch (TypeError|ValueError) {
            // Type non géré (énumération, objet) et valeur invalide : défaut conservé.
        }
    }
}

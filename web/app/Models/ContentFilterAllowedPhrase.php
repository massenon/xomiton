<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Liste blanche du filtre de contenus (`content_filter_allowlist`, lot IOS-10) : expression légitime retirée du texte
 * avant la recherche des mots filtrés (faux positifs : un nom de lieu, un mot de métier contenant un terme).
 */
class ContentFilterAllowedPhrase extends Model
{
    public const CACHE_KEY = 'feed:content_filter_allowlist';

    protected $table = 'content_filter_allowlist';

    /** @var list<string> */
    protected $fillable = ['phrase'];

    protected static function booted(): void
    {
        $forget = static fn (): bool => Cache::forget(self::CACHE_KEY);

        static::saved($forget);
        static::deleted($forget);
    }

    public function setPhraseAttribute(string $value): void
    {
        $this->attributes['phrase'] = Str::lower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }
}

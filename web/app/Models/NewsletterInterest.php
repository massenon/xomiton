<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NewsletterInterestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Centre d'intérêt des abonné·es (DATABASE_DICTIONARY.md §9 `newsletter_interests`, ADR-20).
 */
class NewsletterInterest extends Model
{
    /** @use HasFactory<NewsletterInterestFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'label',
    ];

    protected static function booted(): void
    {
        static::creating(function (NewsletterInterest $interest): void {
            if (empty($interest->slug)) {
                $interest->slug = Str::slug((string) $interest->label);
            }
        });
    }

    /**
     * @return BelongsToMany<NewsletterSubscriber, $this>
     */
    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(NewsletterSubscriber::class, 'newsletter_subscriber_interests', 'interest_id', 'subscriber_id')
            ->using(NewsletterSubscriberInterest::class);
    }

    /**
     * @param  Builder<NewsletterInterest>  $query
     */
    public function scopeWhereSlug(Builder $query, string $slug): void
    {
        $query->where('slug', $slug);
    }

    /**
     * @param  Builder<NewsletterInterest>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('label');
    }
}

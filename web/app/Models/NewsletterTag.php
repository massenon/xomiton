<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NewsletterTagFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Tag de segmentation des abonné·es (DATABASE_DICTIONARY.md §9 `newsletter_tags`, ADR-20).
 */
class NewsletterTag extends Model
{
    /** @use HasFactory<NewsletterTagFactory> */
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
        static::creating(function (NewsletterTag $tag): void {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug((string) $tag->label);
            }
        });
    }

    /**
     * @return BelongsToMany<NewsletterSubscriber, $this>
     */
    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(NewsletterSubscriber::class, 'newsletter_subscriber_tags', 'tag_id', 'subscriber_id')
            ->using(NewsletterSubscriberTag::class);
    }

    /**
     * @param  Builder<NewsletterTag>  $query
     */
    public function scopeWhereSlug(Builder $query, string $slug): void
    {
        $query->where('slug', $slug);
    }

    /**
     * @param  Builder<NewsletterTag>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('label');
    }
}

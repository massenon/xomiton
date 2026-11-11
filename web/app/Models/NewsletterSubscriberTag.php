<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NewsletterSubscriberTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Liaison abonné·e ↔ tag (DATABASE_DICTIONARY.md §9 `newsletter_subscriber_tags`) : sans horodatage.
 */
class NewsletterSubscriberTag extends Pivot
{
    /** @use HasFactory<NewsletterSubscriberTagFactory> */
    use HasFactory;

    public $incrementing = true;

    public $timestamps = false;

    protected $table = 'newsletter_subscriber_tags';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subscriber_id',
        'tag_id',
    ];

    /**
     * @return BelongsTo<NewsletterSubscriber, $this>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'subscriber_id');
    }

    /**
     * @return BelongsTo<NewsletterTag, $this>
     */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(NewsletterTag::class, 'tag_id');
    }
}

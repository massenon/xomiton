<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NewsletterSubscriberInterestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Liaison abonné·e ↔ centre d'intérêt (DATABASE_DICTIONARY.md §9 `newsletter_subscriber_interests`) :
 * sans horodatage.
 */
class NewsletterSubscriberInterest extends Pivot
{
    /** @use HasFactory<NewsletterSubscriberInterestFactory> */
    use HasFactory;

    public $incrementing = true;

    public $timestamps = false;

    protected $table = 'newsletter_subscriber_interests';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subscriber_id',
        'interest_id',
    ];

    /**
     * @return BelongsTo<NewsletterSubscriber, $this>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'subscriber_id');
    }

    /**
     * @return BelongsTo<NewsletterInterest, $this>
     */
    public function interest(): BelongsTo
    {
        return $this->belongsTo(NewsletterInterest::class, 'interest_id');
    }
}

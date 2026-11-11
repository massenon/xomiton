<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NewsletterCampaign;
use App\Models\User;

/**
 * Campagnes de la lettre d'information : `newsletter.manage_campaigns` édite, `newsletter.send` envoie ou planifie.
 */
final class NewsletterCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('newsletter.manage_campaigns');
    }

    public function view(User $user, NewsletterCampaign $campaign): bool
    {
        return $user->can('newsletter.manage_campaigns');
    }

    public function create(User $user): bool
    {
        return $user->can('newsletter.manage_campaigns');
    }

    public function update(User $user, NewsletterCampaign $campaign): bool
    {
        return $user->can('newsletter.manage_campaigns');
    }

    public function delete(User $user, NewsletterCampaign $campaign): bool
    {
        return $user->can('newsletter.manage_campaigns');
    }

    public function send(User $user, NewsletterCampaign $campaign): bool
    {
        return $user->can('newsletter.send');
    }

    public function sendTest(User $user, NewsletterCampaign $campaign): bool
    {
        return $user->can('newsletter.manage_campaigns');
    }

    public function cancel(User $user, NewsletterCampaign $campaign): bool
    {
        return $user->can('newsletter.send');
    }
}

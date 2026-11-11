<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NewsletterSubscriber;
use App\Models\User;

/**
 * Abonné·es à la lettre d'information : tout relève de `newsletter.manage_subscribers`
 * (les jetons ne sont jamais exposés hors de l'administration, ROLE_ET_PERMISSIONS.md §5).
 */
final class NewsletterSubscriberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('newsletter.manage_subscribers');
    }

    public function view(User $user, NewsletterSubscriber $subscriber): bool
    {
        return $user->can('newsletter.manage_subscribers');
    }

    public function create(User $user): bool
    {
        return $user->can('newsletter.manage_subscribers');
    }

    public function update(User $user, NewsletterSubscriber $subscriber): bool
    {
        return $user->can('newsletter.manage_subscribers');
    }

    public function delete(User $user, NewsletterSubscriber $subscriber): bool
    {
        return $user->can('newsletter.manage_subscribers');
    }

    public function import(User $user): bool
    {
        return $user->can('newsletter.manage_subscribers');
    }

    public function export(User $user): bool
    {
        return $user->can('newsletter.manage_subscribers');
    }
}

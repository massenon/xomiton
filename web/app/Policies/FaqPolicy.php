<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Faq;
use App\Models\User;

/**
 * Questions fréquentes : lecture publique quand le contenu est publié (RG-01), édition réservée à `faqs.manage`.
 */
final class FaqPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Faq $faq): bool
    {
        if ($user?->can('faqs.manage')) {
            return true;
        }

        return $this->isPublished($faq);
    }

    public function create(User $user): bool
    {
        return $user->can('faqs.manage');
    }

    public function update(User $user, Faq $faq): bool
    {
        return $user->can('faqs.manage');
    }

    public function delete(User $user, Faq $faq): bool
    {
        return $user->can('faqs.manage');
    }

    public function publish(User $user, Faq $faq): bool
    {
        return $user->can('faqs.manage');
    }

    private function isPublished(Faq $faq): bool
    {
        return Faq::query()->published()->whereKey($faq->getKey())->exists();
    }
}

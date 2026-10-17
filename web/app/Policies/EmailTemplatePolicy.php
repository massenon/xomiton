<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmailTemplate;
use App\Models\User;

/**
 * Templates e-mails (ROLE_ET_PERMISSIONS.md §3) : `email_templates.manage` pour tout, sauf la suppression
 * d'un template système qui exige `email_templates.delete_system` (superadmin).
 */
final class EmailTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('email_templates.manage');
    }

    public function view(User $user, EmailTemplate $template): bool
    {
        return $user->can('email_templates.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('email_templates.manage');
    }

    public function update(User $user, EmailTemplate $template): bool
    {
        return $user->can('email_templates.manage');
    }

    public function sendTest(User $user, EmailTemplate $template): bool
    {
        return $user->can('email_templates.manage');
    }

    public function delete(User $user, EmailTemplate $template): bool
    {
        if ($template->is_system) {
            return $this->deleteSystem($user, $template);
        }

        return $user->can('email_templates.manage');
    }

    public function deleteSystem(User $user, EmailTemplate $template): bool
    {
        return $user->can('email_templates.delete_system');
    }
}

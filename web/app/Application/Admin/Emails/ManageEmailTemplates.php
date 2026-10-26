<?php

declare(strict_types=1);

namespace App\Application\Admin\Emails;

use App\Application\Mail\SmtpSettings;
use App\Domain\Newsletter\Exceptions\CommunicationRefused;
use App\Enums\TemplateCategory;
use App\Models\EmailTemplate;
use App\Models\MailOutbox;
use App\Models\User;
use App\Support\Audit;
use App\Support\EmailHtml;
use Illuminate\Support\Str;

/**
 * Templates e-mails de l'administration (P14-01, `email_templates.manage`) : créer, modifier, dupliquer, activer / désactiver,
 * supprimer, envoyer un test. RG-10 : le slug d'un template système est immuable et sa suppression exige `email_templates.delete_system`
 * (superadmin) et la saisie du slug. Le HTML est nettoyé (HTMLPurifier, profil `email`). Le journal d'audit nomme le slug et
 * jamais le contenu ni l'adresse de test. Un template inactif refuse tout envoi (EmailTemplateService::render()).
 */
final class ManageEmailTemplates
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const SLUG_MAX = 60;

    public const TEST_PER_DAY = 20;

    /**
     * @param  array{name: string, slug?: ?string, category: string, subject: string, preheader?: ?string, body: string, is_active?: bool}  $data
     *
     * @throws CommunicationRefused
     */
    public function save(?EmailTemplate $template, array $data, User $actor): EmailTemplate
    {
        $attributes = [
            'name' => trim($data['name']),
            'category' => TemplateCategory::from($data['category']),
            'subject' => trim($data['subject']),
            'preheader' => trim((string) ($data['preheader'] ?? '')),
            'body' => EmailHtml::clean($data['body']),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'updated_by' => $actor->getKey(),
        ];

        if (trim(strip_tags($attributes['body'])) === '') {
            throw new CommunicationRefused('body_empty');
        }

        self::assertKnownVariables($attributes['subject'], $attributes['preheader'], $attributes['body']);

        if ($template === null) {
            $attributes['slug'] = $this->availableSlug(trim((string) ($data['slug'] ?? '')) !== '' ? trim((string) $data['slug']) : Str::slug($attributes['name']), null);
            $attributes['is_system'] = false;
            $template = EmailTemplate::query()->create($attributes);
            Audit::log('email_template.created', $template, $template->slug, $actor);

            return $template;
        }

        // RG-10 : le slug d'un système ne change jamais ; celui des autres change seulement s'il reste libre et valide.
        $slug = trim((string) ($data['slug'] ?? ''));

        if ($template->canRenameSlug() && $slug !== '' && $slug !== $template->slug) {
            $attributes['slug'] = $this->availableSlug($slug, $template);
        }

        $template->forceFill($attributes)->save();
        Audit::log('email_template.updated', $template, $template->slug, $actor);

        return $template;
    }

    /** Copie désactivée au slug `-copie` (jamais système). */
    public function duplicate(EmailTemplate $template, User $actor): EmailTemplate
    {
        $copy = EmailTemplate::query()->create([
            'name' => Str::limit($template->name.' (copie)', 120, ''),
            'slug' => $this->availableSlug(Str::limit($template->slug, self::SLUG_MAX - 6, '').'-copie', null),
            'category' => $template->category,
            'is_system' => false,
            'subject' => $template->subject,
            'preheader' => $template->preheader,
            'body' => $template->body,
            'is_active' => false,
            'updated_by' => $actor->getKey(),
        ]);
        Audit::log('email_template.duplicated', $copy, $template->slug.' → '.$copy->slug, $actor);

        return $copy;
    }

    public function toggle(EmailTemplate $template, User $actor): EmailTemplate
    {
        $template->forceFill(['is_active' => ! $template->is_active, 'updated_by' => $actor->getKey()])->save();
        Audit::log($template->is_active ? 'email_template.activated' : 'email_template.deactivated', $template, $template->slug, $actor);

        return $template;
    }

    /**
     * @throws CommunicationRefused si le slug saisi ne correspond pas (RG-10)
     */
    public function delete(EmailTemplate $template, string $typedSlug, User $actor): void
    {
        if ($template->is_system && trim($typedSlug) !== $template->slug) {
            throw new CommunicationRefused('slug_mismatch');
        }

        $template->delete();
        Audit::log('email_template.deleted', null, $template->slug.($template->is_system ? ' (système)' : ''), $actor);
    }

    /**
     * Dépose un envoi de test dans la boîte d'envoi (aucun SMTP dans une requête web) : sujet préfixé « [TEST] »,
     * données fictives, expédié par `mail:flush-outbox` dans la minute.
     *
     * @throws CommunicationRefused
     */
    public function sendTest(string $subject, string $preheader, string $body, string $to, User $actor): void
    {
        $to = trim($to);

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new CommunicationRefused('test_address_invalid');
        }

        self::assertKnownVariables($subject, $preheader, EmailHtml::clean($body));

        if (! \Illuminate\Support\Facades\RateLimiter::attempt('email-template-test-day:'.$actor->getKey(), self::TEST_PER_DAY, static fn (): bool => true, 86400)) {
            throw new CommunicationRefused('test_quota');
        }

        $rendered = EmailPreview::render($subject, $preheader, $body);

        if (trim(strip_tags($rendered['text'])) === '') {
            throw new CommunicationRefused('body_empty');
        }

        MailOutbox::query()->create([
            'to' => $to,
            'from_address' => SmtpSettings::fromAddress(),
            'from_name' => mb_substr(SmtpSettings::fromName(), 0, 80),
            'reply_to' => SmtpSettings::replyTo(),
            'subject' => mb_substr('[TEST] '.$rendered['subject'], 0, 160),
            'html' => $rendered['html'],
            'text' => $rendered['text'],
            'template_slug' => 'test',
        ]);
        Audit::log('email_template.test_queued', null, null, $actor);
    }

    /**
     * Variables autorisées : celles de la plateforme et des templates système. Une variable inconnue ne serait jamais
     * remplacée et s'afficherait telle quelle dans l'e-mail.
     *
     * @throws CommunicationRefused
     */
    private static function assertKnownVariables(string ...$texts): void
    {
        $known = array_keys(EmailPreview::sampleVars());

        EmailTemplate::query()->where('is_system', true)->get(['variables'])->each(static function (EmailTemplate $template) use (&$known): void {
            $known = array_merge($known, array_map('strval', (array) $template->variables));
        });

        $unknown = array_values(array_diff(EmailTemplate::extractVariables(...$texts), $known));

        if ($unknown !== []) {
            throw new CommunicationRefused('variables_unknown', ['list' => implode(', ', $unknown)]);
        }
    }

    /**
     * @throws CommunicationRefused
     */
    private function availableSlug(string $wanted, ?EmailTemplate $except): string
    {
        $base = Str::limit(Str::slug($wanted), self::SLUG_MAX, '');

        if (preg_match(self::SLUG_PATTERN, $base) !== 1) {
            throw new CommunicationRefused('slug_invalid');
        }

        $slug = $base;
        $i = 2;

        while (EmailTemplate::query()->whereSlug($slug)->when($except, fn ($q) => $q->whereKeyNot($except->getKey()))->exists()) {
            $suffix = '-'.$i++;
            $slug = Str::limit($base, self::SLUG_MAX - strlen($suffix), '').$suffix;
        }

        return $slug;
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Mail;

use App\Application\Mail\Exceptions\TemplateException;
use App\Application\Mail\Exceptions\TemplateInactive;
use App\Application\Mail\Exceptions\TemplateNotFound;
use App\Enums\MailTransport;
use App\Mail\TemplateMail;
use App\Models\EmailTemplate;
use App\Models\MailLog;
use App\Models\MailOutbox;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * Envoi d'un e-mail à partir d'un template en base (P3-01, port de prototype_social/inc/x_mailer.php).
 *
 *   EmailTemplateService::send('code-verification', 'awa@exemple.bj', ['otp_code' => '482913', 'otp_expiry' => '10 minutes']);
 *
 * 1. le template est lu par son slug (`email_templates`) ; 2. les variables `{{nom}}` du sujet, du pré-en-tête et du
 * corps sont remplacées (valeurs échappées dans le HTML) ; 3. le corps est habillé dans la mise en page Xomiton
 * (resources/views/mail/layout.blade.php) ; 4. l'envoi part par le SMTP réglé dans `settings`, sinon par MAIL_MAILER
 * (.env) ; sans transport utilisable (`MAIL_MAILER=outbox`) ou en cas d'échec de connexion, l'e-mail complet est déposé
 * dans `mail_outbox` (renvoyé par `mail:flush-outbox`). Chaque tentative est consignée dans `mail_logs`.
 */
final class EmailTemplateService
{
    /** Valeur de MAIL_MAILER qui désigne explicitement la boîte d'envoi (aucun transport direct). */
    public const OUTBOX_MAILER = 'outbox';

    private function __construct() {}

    /**
     * Rend un template : sujet, HTML complet (mise en page incluse), version texte et variables non fournies.
     *
     * @param  array<string, scalar|\Stringable|null>  $vars
     * @return array{subject: string, html: string, text: string, unknown_vars: list<string>}
     *
     * @throws TemplateNotFound|TemplateInactive
     */
    public static function render(string $slug, array $vars = []): array
    {
        $template = EmailTemplate::query()->whereSlug($slug)->first();

        if ($template === null) {
            throw TemplateNotFound::forSlug($slug);
        }

        if (! $template->is_active) {
            throw TemplateInactive::forSlug($slug);
        }

        $vars = self::normalizeVars($vars) + self::defaultVars();

        $subject = TemplateRenderer::render((string) $template->subject, $vars, false);
        $preheader = TemplateRenderer::render((string) $template->preheader, $vars, false);
        $bodyHtml = TemplateRenderer::render((string) $template->body, $vars);

        $layout = EmailLayout::render(
            (string) $vars['site_name'],
            (string) $vars['support_email'],
            $preheader,
            $bodyHtml,
            isset($vars['unsubscribe_link']) ? (string) $vars['unsubscribe_link'] : null,
            isset($vars['tracking_pixel']) ? (string) $vars['tracking_pixel'] : null,
        );

        return [
            'subject' => $subject,
            'html' => $layout['html'],
            'text' => $layout['text'],
            'unknown_vars' => TemplateRenderer::unknownVariables(
                $vars,
                (string) $template->subject,
                (string) $template->preheader,
                (string) $template->body,
            ),
        ];
    }

    /**
     * Envoie le template `$slug` à `$to` et renvoie la ligne de journal (`ok` à false si rien n'a pu partir).
     * Le sujet journalisé masque les valeurs sensibles fournies (code OTP…) : `mail_logs` est lisible en administration.
     *
     * @param  array<string, scalar|\Stringable|null>  $vars
     */
    public static function send(string $slug, string $to, array $vars = [], ?string $context = null): MailLog
    {
        $prepared = self::prepare($slug, $to, $vars, $context);

        if ($prepared instanceof MailLog) {
            return $prepared;
        }

        return self::deliver(trim($to), $prepared, self::redactedSubject($prepared->subjectLine, $vars), $context);
    }

    /**
     * Dépôt différé (P5-03, D7) : mêmes contrôles et même rendu que send(), mais l'e-mail est déposé directement dans
     * la boîte d'envoi, sans aucune connexion SMTP (le SMTP n'est pas HTTP et bloquerait une requête SOS ou de
     * signalement) ; `mail:flush-outbox` l'expédie dans la minute. Journal `transport = outbox`.
     *
     * @param  array<string, scalar|\Stringable|null>  $vars
     */
    public static function enqueue(string $slug, string $to, array $vars = [], ?string $context = null): MailLog
    {
        $prepared = self::prepare($slug, $to, $vars, $context);

        if ($prepared instanceof MailLog) {
            return $prepared;
        }

        self::deposit(trim($to), $prepared, null);

        return self::log(trim($to), self::redactedSubject($prepared->subjectLine, $vars), $slug, $context, MailTransport::Outbox, true, null);
    }

    /**
     * Contrôle l'adresse et rend le template ; renvoie la ligne de journal d'échec quand rien ne peut partir.
     *
     * @param  array<string, scalar|\Stringable|null>  $vars
     */
    private static function prepare(string $slug, string $to, array $vars, ?string $context): TemplateMail|MailLog
    {
        $to = trim($to);

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return self::log($to, '(adresse invalide)', $slug, $context, MailTransport::None, false, 'Adresse destinataire invalide.');
        }

        try {
            $rendered = self::render($slug, $vars);
        } catch (TemplateException $e) {
            Log::warning('E-mail non envoyé : '.$e->getMessage(), ['slug' => $slug, 'context' => $context]);

            return self::log($to, '(template : '.$slug.')', $slug, $context, MailTransport::None, false, $e->getMessage());
        }

        if ($rendered['unknown_vars'] !== []) {
            Log::warning('Template e-mail : variables non fournies.', ['slug' => $slug, 'variables' => $rendered['unknown_vars']]);
        }

        return new TemplateMail(
            subjectLine: $rendered['subject'],
            htmlBody: $rendered['html'],
            textBody: $rendered['text'],
            fromAddress: SmtpSettings::fromAddress(),
            fromName: SmtpSettings::fromName(),
            replyToAddress: SmtpSettings::replyTo(),
            templateSlug: $slug,
        );
    }

    /**
     * Remet l'e-mail au transport disponible, ou le dépose dans la boîte d'envoi ; une ligne de journal dans tous les cas.
     */
    private static function deliver(string $to, TemplateMail $mailable, string $loggedSubject, ?string $context): MailLog
    {
        $slug = (string) $mailable->templateSlug;
        $mailer = self::resolveMailer();

        if ($mailer === null) {
            self::deposit($to, $mailable, null);

            return self::log($to, $loggedSubject, $slug, $context, MailTransport::Outbox, true, null);
        }

        try {
            $sent = $mailer->to($to)->send($mailable);
        } catch (Throwable $e) {
            // Serveur injoignable ou refus : l'e-mail complet attend dans la boîte d'envoi, rejoué par le cron.
            $error = Str::limit('SMTP : '.$e->getMessage(), 255, '');
            self::deposit($to, $mailable, $error);

            return self::log($to, $loggedSubject, $slug, $context, MailTransport::Outbox, true, $error);
        }

        return self::log($to, $loggedSubject, $slug, $context, MailTransport::Smtp, true, null, $sent?->getMessageId());
    }

    /**
     * Sujet tel qu'il est journalisé : les valeurs des variables sensibles (clés reconnues par Audit::isSensitiveKey,
     * par exemple `otp_code`) sont remplacées par le masque, sans toucher à l'e-mail envoyé.
     *
     * @param  array<string, mixed>  $vars
     */
    public static function redactedSubject(string $subject, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $value = is_scalar($value) || $value instanceof \Stringable ? trim((string) $value) : '';

            if ($value !== '' && Audit::isSensitiveKey((string) $key)) {
                $subject = str_replace($value, Audit::MASK, $subject);
            }
        }

        return $subject;
    }

    /**
     * Mailer à utiliser : SMTP des paramètres de la plateforme, sinon MAIL_MAILER ; null quand rien ne peut partir.
     */
    public static function resolveMailer(): ?Mailer
    {
        $smtp = SmtpSettings::mailerConfig();

        if ($smtp !== null) {
            return Mail::build($smtp);
        }

        $default = (string) config('mail.default', '');

        if ($default === '' || $default === self::OUTBOX_MAILER || config('mail.mailers.'.$default) === null) {
            return null;
        }

        return Mail::mailer($default);
    }

    /**
     * Valeurs toujours disponibles dans les templates (x_mail_variables() du prototype).
     *
     * @return array<string, string>
     */
    public static function defaultVars(): array
    {
        return [
            'site_name' => Settings::string('site_name', (string) config('app.name', 'Xomiton')),
            'support_email' => Settings::string('contact_email', (string) config('mail.from.address', 'contact@xomiton.bj')),
            'current_year' => now()->format('Y'),
            'site_url' => (string) config('app.url'),
            'login_link' => Route::has('connexion') ? route('connexion') : url('/connexion'),
        ];
    }

    private static function deposit(string $to, TemplateMail $mail, ?string $error): MailOutbox
    {
        return MailOutbox::query()->create([
            'to' => $to,
            'from_address' => $mail->fromAddress,
            'from_name' => Str::limit($mail->fromName, 80, ''),
            'reply_to' => $mail->replyToAddress,
            'subject' => Str::limit($mail->subjectLine, 160, ''),
            'html' => $mail->htmlBody,
            'text' => $mail->textBody,
            'template_slug' => $mail->templateSlug,
            'attempts' => $error === null ? 0 : 1,
            'last_error' => $error,
        ]);
    }

    private static function log(
        string $to,
        string $subject,
        string $slug,
        ?string $context,
        MailTransport $transport,
        bool $ok,
        ?string $error,
        ?string $messageId = null,
    ): MailLog {
        return MailLog::query()->create([
            'to' => Str::limit($to, 190, ''),
            'subject' => Str::limit($subject, 160, ''),
            'template_slug' => Str::limit($slug, 60, ''),
            'context' => $context === null ? null : Str::limit($context, 40, ''),
            'transport' => $transport,
            'ok' => $ok,
            'error' => $error === null ? null : Str::limit($error, 255, ''),
            'message_id' => $messageId === null ? null : Str::limit($messageId, 190, ''),
        ]);
    }

    /**
     * Clés en minuscules, valeurs scalaires (null devient chaîne vide à l'affichage).
     *
     * @param  array<string, mixed>  $vars
     * @return array<string, scalar|\Stringable|null>
     */
    private static function normalizeVars(array $vars): array
    {
        $normalized = [];

        foreach ($vars as $key => $value) {
            if (is_scalar($value) || $value === null || $value instanceof \Stringable) {
                $normalized[mb_strtolower((string) $key)] = $value;
            }
        }

        return $normalized;
    }
}

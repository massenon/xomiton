<?php

declare(strict_types=1);

namespace App\Application\Steering\Reports;

use App\Application\Mail\EmailTemplateService;
use App\Application\Mail\Exceptions\TemplateException;
use App\Application\Mail\SmtpSettings;
use App\Enums\MailTransport;
use App\Mail\TemplateMail;
use App\Models\AuditLog;
use App\Models\GeneratedReport;
use App\Models\MailLog;
use App\Support\Audit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Envoi du fichier d'un rapport par e-mail (P10-07) : modèle `rapport-genere` (EmailTemplateService::render), fichier en
 * pièce jointe, SMTP des paramètres de la plateforme. Un envoi par destinataire, chacun journalisé dans `mail_logs`
 * (le fichier n'y figure pas). Sans transport utilisable, ou en cas d'échec, le rapport reste téléchargeable et son
 * champ `error` le dit : « Renvoyer par e-mail » le rejoue. Un fichier qui contient le registre détaillé n'est jamais
 * joint (aucun envoi, ni à la génération ni au renvoi) : il reste téléchargeable par lien signé.
 */
final class ReportDelivery
{
    public const CONTEXT = 'report';

    /**
     * @return int nombre d'envois réussis
     */
    public function send(GeneratedReport $report): int
    {
        $recipients = array_values(array_filter((array) $report->recipients, is_string(...)));

        if ($recipients === [] || ! $report->hasFile()) {
            return 0;
        }

        // Garde de dernier recours : un fichier qui contient le registre détaillé ne part jamais par e-mail.
        if ($report->hasDetail()) {
            $report->forceFill(['error' => (string) __('steering.reports.errors.detail_no_mail')])->save();

            return 0;
        }

        $mailer = EmailTemplateService::resolveMailer();

        if ($mailer === null) {
            $report->forceFill(['error' => (string) __('steering.reports.errors.no_transport')])->save();
            Log::warning('Rapport non envoyé : aucun transport e-mail.', ['report' => $report->getKey()]);

            return 0;
        }

        try {
            $rendered = EmailTemplateService::render('rapport-genere', [
                'report_title' => $report->title,
                'report_period' => $report->periodLabel(),
                'report_format' => $report->format->label(),
            ]);
        } catch (TemplateException $exception) {
            $report->forceFill(['error' => Str::limit($exception->getMessage(), 255, '')])->save();

            return 0;
        }

        $sent = 0;
        $errors = [];

        foreach ($recipients as $to) {
            try {
                $mail = new TemplateMail(
                    subjectLine: $rendered['subject'],
                    htmlBody: $rendered['html'],
                    textBody: $rendered['text'],
                    fromAddress: SmtpSettings::fromAddress(),
                    fromName: SmtpSettings::fromName(),
                    replyToAddress: SmtpSettings::replyTo(),
                    templateSlug: 'rapport-genere',
                );
                $mail->attachFromStorageDisk((string) config('xomiton.reports.disk', 'local'), (string) $report->file_path, ReportStorage::downloadName($report), ['mime' => $report->format->mimeType()]);
                $result = $mailer->to($to)->send($mail);
                $this->log($to, $rendered['subject'], true, null, $result?->getMessageId());
                $sent++;
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
                $this->log($to, $rendered['subject'], false, 'SMTP : '.$exception->getMessage(), null);
            }
        }

        $report->forceFill(['error' => $errors === [] ? null : Str::limit((string) __('steering.reports.errors.mail_failed', ['count' => count($errors)]), 255, '')])->save();
        Audit::logWithoutRequest('report.mailed', $report, trans_choice('steering.reports.audit.mailed', $sent, ['count' => $sent]), AuditLog::ACTOR_SYSTEM);

        return $sent;
    }

    private function log(string $to, string $subject, bool $ok, ?string $error, ?string $messageId): void
    {
        MailLog::query()->create([
            'to' => Str::limit($to, 190, ''),
            'subject' => Str::limit($subject, 160, ''),
            'template_slug' => 'rapport-genere',
            'context' => self::CONTEXT,
            'transport' => MailTransport::Smtp,
            'ok' => $ok,
            'error' => $error === null ? null : Str::limit($error, 255, ''),
            'message_id' => $messageId === null ? null : Str::limit($messageId, 190, ''),
        ]);
    }
}

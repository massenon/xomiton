<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

/**
 * E-mail déjà rendu par EmailTemplateService (sujet, HTML complet, version texte) : aucune vue Blade à la
 * construction, pour que le même contenu puisse être déposé tel quel dans `mail_outbox` et rejoué plus tard.
 */
class TemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $htmlBody,
        public readonly string $textBody,
        public readonly string $fromAddress,
        public readonly string $fromName,
        public readonly ?string $replyToAddress = null,
        public readonly ?string $templateSlug = null,
        /** @var array<string, string> en-têtes ajoutés à l'envoi (List-Unsubscribe…) */
        public readonly array $extraHeaders = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            replyTo: $this->replyToAddress === null ? [] : [new Address($this->replyToAddress)],
            subject: $this->subjectLine,
            // Version texte ajoutée au moment de l'envoi (pas de closure dans le constructeur : le Mailable reste sérialisable).
            using: [function (Email $message): void {
                $message->text($this->textBody);

                foreach ($this->extraHeaders as $name => $value) {
                    $message->getHeaders()->addTextHeader($name, $value);
                }
            }],
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->htmlBody);
    }
}

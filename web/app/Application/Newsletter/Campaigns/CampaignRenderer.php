<?php

declare(strict_types=1);

namespace App\Application\Newsletter\Campaigns;

use App\Application\Mail\EmailLayout;
use App\Application\Mail\EmailTemplateService;
use App\Application\Mail\TemplateRenderer;
use App\Application\Newsletter\NewsletterLinks;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignRecipient;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Str;

/**
 * Rendu d'un e-mail de campagne pour une personne (P14-03) : variables de personnalisation (`{{user_first_name}}`…,
 * valeurs échappées), liens web du corps réécrits en liens de clic signés HMAC (NewsletterLinks, P4-07), pixel
 * d'ouverture signé, lien de désinscription unique et en-têtes `List-Unsubscribe` / `List-Unsubscribe-Post` (RFC 8058).
 * Sans destinataire (aperçu, test), aucun lien de suivi n'est posé. Le corps est nettoyé (HTMLPurifier) à l'enregistrement.
 */
final class CampaignRenderer
{
    /** Variables proposées à la rédaction (nom => libellé de lang/fr/admin_newsletter.php). */
    public const VARIABLES = ['user_first_name', 'user_name', 'user_email', 'site_name', 'current_year', 'login_link', 'unsubscribe_link'];

    public function __construct(private readonly NewsletterLinks $links) {}

    /**
     * @return array<string, string>
     */
    public function variables(NewsletterSubscriber $subscriber, ?int $campaignId = null): array
    {
        $name = trim((string) $subscriber->name);
        $first = $name === '' ? (string) __('admin_newsletter.render.first_name_fallback') : Str::before($name, ' ');

        return [
            'user_first_name' => $first,
            'user_name' => $name === '' ? $first : $name,
            'user_email' => (string) $subscriber->email,
            'unsubscribe_link' => $this->links->unsubscribeUrl($subscriber, $campaignId),
        ] + EmailTemplateService::defaultVars();
    }

    /**
     * @param  NewsletterCampaign|array{subject: string, preheader?: ?string, body: string}  $campaign
     * @return array{subject: string, html: string, text: string, headers: array<string, string>}
     */
    public function render(NewsletterCampaign|array $campaign, NewsletterSubscriber $subscriber, ?NewsletterCampaignRecipient $recipient = null): array
    {
        $draft = $campaign instanceof NewsletterCampaign
            ? ['subject' => (string) $campaign->subject, 'preheader' => (string) $campaign->preheader, 'body' => (string) $campaign->body, 'id' => (int) $campaign->getKey()]
            : ['subject' => $campaign['subject'], 'preheader' => (string) ($campaign['preheader'] ?? ''), 'body' => $campaign['body'], 'id' => null];

        $vars = $this->variables($subscriber, $draft['id']);
        $body = TemplateRenderer::render($draft['body'], $vars);

        if ($recipient !== null) {
            $body = $this->trackLinks($body, $recipient);
        }

        $layout = EmailLayout::render(
            $vars['site_name'],
            $vars['support_email'],
            TemplateRenderer::render($draft['preheader'], $vars, false),
            $body,
            $vars['unsubscribe_link'],
            $recipient === null ? null : $this->links->openPixelUrl($recipient),
        );

        return [
            'subject' => TemplateRenderer::render($draft['subject'], $vars, false),
            'html' => $layout['html'],
            'text' => $layout['text'],
            'headers' => $this->links->unsubscribeHeaders($subscriber, $draft['id']),
        ];
    }

    /** Réécrit chaque `href` web (hors désinscription) en lien de clic signé ; les autres schémas restent intacts. */
    private function trackLinks(string $html, NewsletterCampaignRecipient $recipient): string
    {
        return (string) preg_replace_callback('/href="(https?:\/\/[^"]+)"/i', function (array $match) use ($recipient): string {
            $url = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (str_contains($url, '/newsletter/desinscription/') || ! NewsletterLinks::isTrackableUrl($url)) {
                return $match[0];
            }

            return 'href="'.htmlspecialchars($this->links->clickUrl($recipient, $url), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }, $html);
    }
}

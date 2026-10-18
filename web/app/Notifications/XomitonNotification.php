<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Application\Shared\NotificationPreferences;
use App\Domain\Notifications\PushMessage;
use App\Models\DatabaseNotification;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Socle des notifications Xomiton (P3-02, NOTIFICATIONS_INSTRUCTION.md §2 et §4). Une sous-classe déclare sa
 * catégorie, son caractère sensible, son contenu (`content()`), et au besoin son template e-mail et son SMS ; le socle
 * calcule les canaux depuis les préférences de la personne (`user_settings.notification_prefs`, sécurité et SOS non
 * désactivables), neutralise le texte quand la notification est sensible et que la personne masque le contenu
 * (`mask_sensitive_notifications`), et produit une charge push sans donnée sensible.
 *
 * Canaux : `database` (cloche, colonnes `category` / `is_sensitive` renseignées par XomitonDatabaseChannel), `mail`
 * (template en base via TemplateMailChannel, jamais de Mailable Markdown), `webpush` et `fcm` (selon les abonnements
 * `push_subscriptions` de la personne), `sms` (SOS uniquement). Aucune notification n'est mise en file (D7 : le SOS ne
 * passe jamais par la file ; l'e-mail retombe sur la boîte d'envoi `mail_outbox` en cas d'échec).
 *
 * @phpstan-type Content array{title: string, body: string, url?: string|null, icon?: string}
 */
abstract class XomitonNotification extends Notification
{
    public const CHANNEL_DATABASE = 'database';

    public const CHANNEL_MAIL = 'mail';

    public const CHANNEL_WEBPUSH = 'webpush';

    public const CHANNEL_FCM = 'fcm';

    public const CHANNEL_SMS = 'sms';

    /** Icône Bootstrap Icons par défaut (sans « bi- »). */
    protected const DEFAULT_ICON = 'bell';

    // --- À déclarer par chaque notification -------------------------------------------------------------------------

    /** Catégorie de préférences (App\Models\DatabaseNotification::CATEGORY_*). */
    abstract public function category(): string;

    /** Vrai si le texte complet peut révéler quelque chose sur écran verrouillé (message, alerte, SOS, modération). */
    abstract public function isSensitive(): bool;

    /**
     * Contenu complet : titre, corps, lien d'ouverture, icône.
     *
     * @return Content
     */
    abstract protected function content(object $notifiable): array;

    // --- Points d'extension facultatifs ------------------------------------------------------------------------------

    /**
     * Texte neutre (NOTIFICATIONS §4.1) affiché à la place du contenu quand la notification est sensible et que la
     * personne masque le contenu sensible. Par défaut la variante générique de lang/fr/notifications.php.
     *
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return [
            'title' => (string) __('notifications.discreet.title'),
            'body' => (string) __('notifications.discreet.body'),
        ];
    }

    /**
     * Données complémentaires stockées avec la notification (identifiants pour l'ouverture profonde). Jamais de
     * donnée sensible : elles restent en clair même quand le texte est neutralisé.
     *
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return [];
    }

    /** Slug du template `email_templates` quand le catalogue en prévoit un ; null = pas de canal e-mail. */
    public function mailTemplate(): ?string
    {
        return null;
    }

    /**
     * Variables du template (en plus de `user_first_name` et `user_pseudo`, ajoutées pour un compte).
     *
     * @return array<string, scalar|\Stringable|null>
     */
    protected function mailVariables(object $notifiable): array
    {
        return [];
    }

    /** Contexte journalisé dans `mail_logs.context` (par défaut la catégorie). */
    protected function mailContext(): ?string
    {
        return null;
    }

    /** Texte du SMS de secours ; null = pas de canal SMS. */
    protected function smsText(object $notifiable): ?string
    {
        return null;
    }

    /** Le SMS est-il voulu pour ce destinataire ? Par défaut jamais (SOS uniquement, NOTIFICATIONS §1). */
    protected function wantsSms(object $notifiable): bool
    {
        return false;
    }

    /** La cloche est-elle prévue par le catalogue ? (faux pour un événement « e-mail seulement », ex. bienvenue). */
    protected function wantsDatabase(): bool
    {
        return true;
    }

    /** Le push est-il prévu par le catalogue ? (faux pour les réactions regroupées, les sondages…). */
    protected function wantsPush(): bool
    {
        return true;
    }

    /** Type court de la charge push (`data.type`, ouverture profonde P15-08). */
    protected function pushType(): string
    {
        return Str::snake(class_basename(static::class));
    }

    /** Identifiant de l'objet visé (`data.id`). */
    protected function subjectId(): int|string|null
    {
        return null;
    }

    // --- Canaux ------------------------------------------------------------------------------------------------------

    /**
     * Canaux calculés depuis les préférences de la personne (catégories verrouillées toujours servies) et ses moyens
     * de réception : cloche si le destinataire a une table de notifications, e-mail si une adresse et un template
     * existent, Web Push / FCM selon ses abonnements, SMS si la notification le prévoit et qu'un numéro est connu.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $user = $notifiable instanceof User ? $notifiable : null;
        $channels = [];

        if ($this->wantsDatabase() && method_exists($notifiable, 'notifications') && $this->allows($user, NotificationPreferences::CHANNEL_IN_APP)) {
            $channels[] = self::CHANNEL_DATABASE;
        }

        if ($this->mailTemplate() !== null && $this->allows($user, NotificationPreferences::CHANNEL_EMAIL) && $this->route($notifiable, 'mail') !== null) {
            $channels[] = self::CHANNEL_MAIL;
        }

        if ($this->wantsPush() && $this->allows($user, NotificationPreferences::CHANNEL_PUSH)) {
            if ($this->hasSubscription($notifiable, PushSubscription::KIND_WEBPUSH)) {
                $channels[] = self::CHANNEL_WEBPUSH;
            }

            if ($this->hasSubscription($notifiable, PushSubscription::KIND_FCM)) {
                $channels[] = self::CHANNEL_FCM;
            }
        }

        if ($this->wantsSms($notifiable) && $this->smsText($notifiable) !== null && $this->route($notifiable, 'sms') !== null) {
            $channels[] = self::CHANNEL_SMS;
        }

        return $channels;
    }

    /**
     * Représentation commune (cloche, API) : titre, corps, lien, icône, catégorie, drapeau sensible, compléments.
     * Texte neutre si la notification est sensible et que la personne masque le contenu sensible.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $content = $this->content($notifiable);

        if ($this->shouldMask($notifiable)) {
            $content = $this->discreetContent($notifiable) + ['url' => $content['url'] ?? null, 'icon' => $content['icon'] ?? self::DEFAULT_ICON];
        }

        return [
            'title' => $content['title'],
            'body' => $content['body'],
            'url' => $content['url'] ?? null,
            'icon' => $content['icon'] ?? self::DEFAULT_ICON,
            'category' => $this->category(),
            'sensitive' => $this->isSensitive(),
        ] + $this->extra($notifiable);
    }

    /**
     * Ligne de la table `notifications` (canal `database`).
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * E-mail par template (canal `mail`) : les variables de la personne sont ajoutées à celles de la notification.
     */
    public function toMail(object $notifiable): TemplateMailData
    {
        $slug = $this->mailTemplate();

        if ($slug === null) {
            throw new \LogicException(sprintf('%s has no mail template.', static::class));
        }

        $variables = $this->mailVariables($notifiable);

        if ($notifiable instanceof User) {
            $variables += [
                'user_first_name' => self::firstName($notifiable),
                'user_pseudo' => (string) $notifiable->pseudo,
            ];
        }

        return new TemplateMailData($slug, $variables, $this->mailContext() ?? $this->category());
    }

    /**
     * Charge push (canaux `webpush` et `fcm`) : même texte que la cloche (donc neutre si masqué), données réduites à
     * `type`, `id`, `url`, `category` ; jamais de récit, de position ni de contact (NOTIFICATIONS §4.1, P15-08).
     */
    public function toPush(object $notifiable): PushMessage
    {
        $data = $this->toArray($notifiable);

        return new PushMessage(
            title: (string) $data['title'],
            body: (string) $data['body'],
            url: is_string($data['url'] ?? null) ? $data['url'] : null,
            data: [
                'type' => $this->pushType(),
                'id' => $this->subjectId(),
                'url' => is_string($data['url'] ?? null) ? $data['url'] : null,
                'category' => $this->category(),
            ],
            icon: is_string($data['icon'] ?? null) ? $data['icon'] : null,
        );
    }

    /** Texte du SMS de secours (canal `sms`), borné à la longueur configurée. */
    public function toSms(object $notifiable): ?string
    {
        $text = $this->smsText($notifiable);

        if ($text === null) {
            return null;
        }

        return Str::limit(trim($text), max(20, (int) config('xomiton.sms.max_length', 160)), '…');
    }

    /** La personne masque le contenu sensible et cette notification l'est : texte neutre partout. */
    public function shouldMask(object $notifiable): bool
    {
        if (! $this->isSensitive()) {
            return false;
        }

        return $notifiable instanceof User && (bool) $notifiable->settings?->mask_sensitive_notifications;
    }

    /** La catégorie est-elle verrouillée (sécurité du compte, SOS) ? */
    public function isMandatory(): bool
    {
        return in_array($this->category(), DatabaseNotification::MANDATORY_CATEGORIES, true);
    }

    // --- Aides pour les sous-classes --------------------------------------------------------------------------------

    /** URL d'une route nommée si elle existe, sinon le chemin de repli (les écrans arrivent phase par phase). */
    protected static function urlTo(string $route, array $parameters = [], ?string $fallback = null): string
    {
        if (Route::has($route)) {
            return route($route, $parameters);
        }

        return url($fallback ?? '/'.str_replace('.', '/', $route));
    }

    /**
     * Texte neutre d'un événement : titre générique « Xomiton » et la variante `discreet` de lang/fr/notifications.php.
     *
     * @return array{title: string, body: string}
     */
    protected static function discreetFrom(string $key): array
    {
        return [
            'title' => (string) __('notifications.discreet.title'),
            'body' => (string) __($key),
        ];
    }

    /** Prénom (avant la première espace du nom), sinon le pseudo : pour les e-mails adressés à la personne elle-même. */
    protected static function firstName(User $user): string
    {
        $firstName = trim(Str::before(trim((string) $user->name), ' '));

        return $firstName !== '' ? $firstName : (string) $user->pseudo;
    }

    /** Préférence de la personne pour un canal de cette catégorie (destinataire sans compte : toujours vrai). */
    protected function allows(?User $user, string $channel): bool
    {
        if ($user === null) {
            return true;
        }

        return NotificationPreferences::allows($user->settings?->notification_prefs, $this->category(), $channel);
    }

    /** Le destinataire possède-t-il au moins un abonnement push de ce type ? */
    protected function hasSubscription(object $notifiable, string $kind): bool
    {
        if (! method_exists($notifiable, 'pushSubscriptions')) {
            return false;
        }

        return $notifiable->pushSubscriptions()->ofKind($kind)->exists();
    }

    /** Vrai si le destinataire peut recevoir un push (sert au repli SMS du SOS). */
    protected function hasAnyPushSubscription(object $notifiable): bool
    {
        if (! method_exists($notifiable, 'pushSubscriptions')) {
            return false;
        }

        return $notifiable->pushSubscriptions()->exists();
    }

    /** Adresse de remise d'un canal (`mail`, `sms`), null si le destinataire n'en a pas. */
    protected function route(object $notifiable, string $channel): ?string
    {
        if (! method_exists($notifiable, 'routeNotificationFor')) {
            return null;
        }

        $route = $notifiable->routeNotificationFor($channel, $this);

        if (is_array($route)) {
            $route = array_key_first($route);
        }

        if ($notifiable instanceof AnonymousNotifiable && $route === null) {
            return null;
        }

        return is_string($route) && trim($route) !== '' ? trim($route) : null;
    }
}

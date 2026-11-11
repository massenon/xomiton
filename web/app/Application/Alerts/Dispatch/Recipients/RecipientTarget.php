<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch\Recipients;

use App\Domain\Alerts\Dispatch\RecipientKind;
use App\Enums\DirectoryRole;
use App\Models\AlertNotification;
use App\Models\DirectoryContact;
use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * Une personne à prévenir (P5-03) et ses moyens de réception, résolus au moment de l'envoi ou du rejeu : compte
 * (cloche, push, SMS de repli, e-mail), fiche d'annuaire sans compte (SMS, e-mail directs) ou contact de confiance
 * (SMS direct, numéro déchiffré en mémoire seulement). `reference` est la valeur d'`alert_notifications.recipient`.
 */
final readonly class RecipientTarget
{
    private function __construct(
        public RecipientKind $kind,
        public string $reference,
        public ?User $user = null,
        public ?DirectoryContact $contact = null,
        public ?string $phone = null,
        public ?string $email = null,
        public bool $primary = false,
    ) {}

    /** Compte (admin de garde, compte lié à une fiche, auteur·e) ; `$contact` : fiche d'annuaire dont il porte le libellé. */
    public static function account(User $user, RecipientKind $kind, ?DirectoryContact $contact = null, bool $primary = false): self
    {
        return new self(
            kind: $kind,
            reference: AlertNotification::USER_RECIPIENT_PREFIX.$user->getKey(),
            user: $user,
            contact: $contact,
            phone: $user->routeNotificationForSms(),
            email: self::clean($user->email),
            primary: $primary,
        );
    }

    /**
     * Fiche d'annuaire : par son compte lié s'il est actif, sinon directement (SMS, e-mail de la fiche).
     */
    public static function forDirectoryContact(DirectoryContact $contact, bool $primary = false): self
    {
        $kind = $contact->role === DirectoryRole::PointFocal ? RecipientKind::Focal : RecipientKind::Contact;
        $user = $contact->user_id === null ? null : $contact->user;

        if ($user instanceof User && $user->isActive()) {
            return self::account($user, $kind, $contact, $primary);
        }

        return new self(
            kind: $kind,
            reference: AlertNotification::CONTACT_RECIPIENT_PREFIX.$contact->getKey(),
            contact: $contact,
            phone: self::clean($contact->phone),
            email: self::clean($contact->email),
            primary: $primary,
        );
    }

    /** Contact de confiance de l'auteur·e d'un SOS (`profiles.trusted_contact_phone`, jamais recopié en base). */
    public static function trusted(User $author, string $phone): self
    {
        return new self(
            kind: RecipientKind::Trusted,
            reference: AlertNotification::TRUSTED_RECIPIENT_PREFIX.$author->getKey(),
            phone: self::clean($phone),
        );
    }

    /** L'auteur·e elle-même (accusé transmis, changement de statut). */
    public static function author(User $author): self
    {
        return self::account($author, RecipientKind::Author);
    }

    public function hasAccount(): bool
    {
        return $this->user !== null;
    }

    public function isTeam(): bool
    {
        return $this->kind === RecipientKind::Team;
    }

    /**
     * Destinataire passé aux méthodes de la notification (`toSms()`, `toMail()`) : le compte, la fiche (texte avec lien
     * d'accusé) ou, pour le contact de confiance, un destinataire à la demande (texte « appelez-la »).
     */
    public function notifiable(): object
    {
        if ($this->user !== null) {
            return $this->user;
        }

        if ($this->contact !== null) {
            return $this->contact;
        }

        return $this->anonymous();
    }

    private function anonymous(): AnonymousNotifiable
    {
        return (new AnonymousNotifiable)->route('sms', (string) $this->phone);
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

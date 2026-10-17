<?php

declare(strict_types=1);

namespace App\Application\Mail;

use App\Support\Settings;

/**
 * Serveur SMTP et identité d'expédition configurés dans Administration › Templates e-mails (table `settings`,
 * groupe `smtp`, une clé par champ). Sans hôte, l'application se replie sur MAIL_MAILER (.env).
 */
final class SmtpSettings
{
    /** Délai de connexion au serveur SMTP (secondes) : compatible max_execution_time 30 s (D7). */
    public const TIMEOUT_SECONDS = 10;

    private function __construct() {}

    public static function host(): string
    {
        return trim(Settings::string('smtp_host'));
    }

    public static function isConfigured(): bool
    {
        return self::host() !== '';
    }

    /**
     * Configuration d'un mailer Laravel à la volée (`Mail::build()`), ou null sans hôte configuré.
     *
     * @return array<string, mixed>|null
     */
    public static function mailerConfig(): ?array
    {
        $host = self::host();

        if ($host === '') {
            return null;
        }

        $encryption = mb_strtolower(Settings::string('smtp_encryption', 'tls'));
        $port = Settings::int('smtp_port', 587);
        $username = Settings::string('smtp_username');
        $password = Settings::string('smtp_password');

        return [
            'transport' => 'smtp',
            'host' => $host,
            'port' => $port,
            // `smtps` = TLS implicite (465) ; sinon STARTTLS négocié automatiquement par Symfony si le serveur le propose.
            'scheme' => $encryption === 'ssl' || $port === 465 ? 'smtps' : 'smtp',
            'username' => $username !== '' ? $username : null,
            'password' => $password !== '' ? $password : null,
            'timeout' => self::TIMEOUT_SECONDS,
            'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: null,
        ];
    }

    public static function fromAddress(): string
    {
        $address = Settings::string('smtp_from_address');

        if ($address === '') {
            // Clé du prototype (admin/emails.php) encore seedée sous ce nom.
            $address = Settings::string('smtp_from_email');
        }

        return $address !== '' ? $address : (string) config('mail.from.address', 'contact@xomiton.bj');
    }

    public static function fromName(): string
    {
        $name = Settings::string('smtp_from_name');

        if ($name === '') {
            $name = Settings::string('site_name');
        }

        return $name !== '' ? $name : (string) config('mail.from.name', 'Xomiton');
    }

    public static function replyTo(): ?string
    {
        $replyTo = trim(Settings::string('smtp_reply_to'));

        return $replyTo !== '' ? $replyTo : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Application\Mail\EmailTemplateService;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Fichiers d'export RGPD (P6-06) sur le disque privé `local` (`storage/app/private/exports/personal/<compte>/`, jamais
 * sous public/), **chiffrés** par la clé de l'application (`Crypt::encryptString`, `.json.enc`) : identité de genre,
 * orientation, contact de confiance et récits, chiffrés en base, ne sont jamais écrits en clair sur le disque ni dans
 * les sauvegardes de fichiers de l'hébergement ; déchiffrés seulement au téléchargement. Un seul fichier par compte (une nouvelle demande remplace l'ancien), lien signé envoyé par e-mail
 * (`export-donnees`) et valable `xomiton.accounts.export_link_hours`, téléchargeable seulement connecté·e au même
 * compte (route web `mes-donnees.export`, `auth` + `signed`) ; fichier effacé à l'expiration (`accounts:purge-exports`).
 */
final class PersonalDataExports
{
    public const TEMPLATE = 'export-donnees';

    private const DIRECTORY = 'exports/personal';

    public function __construct(private readonly PersonalDataExport $export) {}

    public static function linkHours(): int
    {
        return max(1, (int) config('xomiton.accounts.export_link_hours', 48));
    }

    /**
     * Construit le fichier, envoie le lien (boîte d'envoi) et journalise ; renvoie le jeton du fichier.
     */
    public function build(User $user): string
    {
        $this->forget($user);

        $token = Str::lower(Str::random(40));
        $json = json_encode($this->export->collect($user), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->disk()->put($this->path($user, $token), Crypt::encryptString($json));

        $hours = self::linkHours();
        $email = trim((string) $user->email);

        if ($email !== '') {
            EmailTemplateService::enqueue(self::TEMPLATE, $email, [
                'user_first_name' => trim(Str::before(trim((string) $user->name), ' ')) ?: (string) $user->pseudo,
                'export_link' => $this->url($token),
                'export_expiry' => trans_choice('account.export.expiry', $hours, ['hours' => $hours]),
            ], 'compte');
        }

        Audit::log('account.export_ready', $user, sprintf('Lien valable %d h.', $hours), $user);

        return $token;
    }

    public function url(string $token): string
    {
        return URL::temporarySignedRoute('mes-donnees.export', now()->addHours(self::linkHours()), ['token' => $token]);
    }

    /** Chemin du fichier de ce compte pour ce jeton, s'il existe encore. */
    public function find(User $user, string $token): ?string
    {
        if (preg_match('/^[a-z0-9]{40}$/', $token) !== 1) {
            return null;
        }

        $path = $this->path($user, $token);

        return $this->disk()->exists($path) ? $path : null;
    }

    /**
     * Contenu JSON en clair d'un fichier d'export (déchiffré en mémoire, jamais réécrit sur le disque).
     *
     * @throws DecryptException fichier altéré ou clé changée
     */
    public function read(string $path): string
    {
        return Crypt::decryptString((string) $this->disk()->get($path));
    }

    public function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    /** Efface les exports de ce compte (nouvelle demande, suppression du compte). */
    public function forget(User $user): void
    {
        $this->disk()->deleteDirectory(self::DIRECTORY.'/'.$user->getKey());
    }

    /**
     * Efface les fichiers plus vieux que la durée du lien.
     *
     * @return int nombre de fichiers effacés
     */
    public function purgeExpired(): int
    {
        $disk = $this->disk();
        $cutoff = now()->subHours(self::linkHours())->getTimestamp();
        $deleted = 0;

        foreach ($disk->allFiles(self::DIRECTORY) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }

        foreach ($disk->directories(self::DIRECTORY) as $directory) {
            if ($disk->allFiles($directory) === []) {
                $disk->deleteDirectory($directory);
            }
        }

        return $deleted;
    }

    private function path(User $user, string $token): string
    {
        return self::DIRECTORY.'/'.$user->getKey().'/'.$token.'.json.enc';
    }
}

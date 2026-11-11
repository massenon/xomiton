<?php

declare(strict_types=1);

namespace App\Application\Account\Profile;

use App\Application\Auth\CompleteRegistrationProfile;
use App\Application\Shared\MediaUploader;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Avatar et couverture du profil (P8-07, `POST · DELETE me/profile/avatar|cover`) : téléversement par MediaUploader
 * (type réel, poids, ré-encodage GD : orientation appliquée, **EXIF / ICC / XMP retirés**, WebP + repli, variantes)
 * sur le disque **public** (visuels non sensibles, adresses ULID non devinables, P3-04) ; l'ancien fichier et ses
 * variantes sont supprimés après l'enregistrement ; la complétion est recalculée (l'avatar en fait partie). Un visuel
 * importé du prototype (`assets/…`) n'est jamais supprimé.
 */
final class UpdateProfileImage
{
    public const AVATAR = 'avatar';

    public const COVER = 'cover';

    /** @var list<string> */
    public const KINDS = [self::AVATAR, self::COVER];

    public function __construct(private readonly MediaUploader $uploader) {}

    public function store(User $user, string $kind, UploadedFile $file): Profile
    {
        $column = self::column($kind);
        $stored = $this->uploader->upload($file, 'images', public: true);

        try {
            $previous = DB::transaction(fn (): ?string => $this->save($user, $column, $stored->path));
        } catch (\Throwable $exception) {
            $this->uploader->delete($stored);

            throw $exception;
        }

        $this->deletePrevious($previous);

        return $user->profile()->firstOrFail();
    }

    public function remove(User $user, string $kind): Profile
    {
        $previous = DB::transaction(fn (): ?string => $this->save($user, self::column($kind), null));
        $this->deletePrevious($previous);

        return $user->profile()->firstOrFail();
    }

    private function save(User $user, string $column, ?string $path): ?string
    {
        $profile = $user->profile ?? Profile::query()->create(['user_id' => $user->getKey()]);
        $previous = $profile->getAttribute($column);
        $profile->setAttribute($column, $path);
        $profile->completion = CompleteRegistrationProfile::completion($user, $profile);
        $profile->save();
        $user->setRelation('profile', $profile);

        return is_string($previous) && $previous !== '' && $previous !== $path ? $previous : null;
    }

    private function deletePrevious(?string $path): void
    {
        $path = ltrim(trim((string) $path), '/');

        if ($path === '' || str_starts_with($path, 'assets/') || preg_match('#^https?://#', $path) === 1) {
            return;
        }

        $this->uploader->delete($path, MediaUploader::publicDisk());
    }

    private static function column(string $kind): string
    {
        return match ($kind) {
            self::AVATAR => 'avatar_path',
            self::COVER => 'cover_path',
            default => throw new InvalidArgumentException(sprintf('Unknown profile image [%s].', $kind)),
        };
    }
}

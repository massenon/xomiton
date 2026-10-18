<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\BadWordSeverity;
use App\Models\BadWord;
use App\Models\Follow;
use App\Models\User;
use Tests\TestCase;

/**
 * Données de test du fil (P7-01, P7-02, P7-07) : membres avec rôle et jeton, abonnement, mots filtrés, JPEG avec EXIF.
 * Suppose RolesAndPermissionsSeeder exécuté.
 */
final class FeedFixtures
{
    public static function member(string $role = 'membre', bool $verified = true): User
    {
        $factory = User::factory()->withProfile();
        $user = ($verified ? $factory : $factory->unverified())->create();
        $user->assignRole($role);

        return $user;
    }

    public static function token(User $user): string
    {
        return $user->createToken('Pixel 7')->plainTextToken;
    }

    /**
     * Prochaine requête du test au nom de ce compte (jeton Bearer). Les gardes sont oubliées d'abord : sans cela, le
     * garde Sanctum garde en mémoire le compte de la requête précédente du même test.
     */
    public static function as(User $user): TestCase
    {
        app('auth')->forgetGuards();

        /** @var TestCase $test */
        $test = test();

        return $test->withToken(self::token($user));
    }

    public static function follow(User $follower, User $followed): void
    {
        Follow::query()->create(['follower_id' => $follower->getKey(), 'followed_id' => $followed->getKey()]);
    }

    public static function badWord(string $word, BadWordSeverity $severity = BadWordSeverity::Block): BadWord
    {
        return BadWord::query()->create(['word' => $word, 'severity' => $severity, 'is_active' => true]);
    }

    /**
     * JPEG fabriqué avec GD puis segment APP1 Exif (Make « Xomiton Test », Orientation 6) inséré après SOI.
     */
    public static function jpegWithExif(int $width = 800, int $height = 600): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, (int) imagecolorallocate($image, 111, 44, 145));
        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        $make = "Xomiton Test\0";
        $ifd = pack('v', 2)
            .pack('vvVV', 0x010F, 2, strlen($make), 8 + 2 + 2 * 12 + 4)
            .pack('vvVv', 0x0112, 3, 1, 6).pack('v', 0)
            .pack('V', 0);
        $tiff = 'II'.pack('v', 42).pack('V', 8).$ifd.$make;
        $app1 = "Exif\0\0".$tiff;
        $segment = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }
}

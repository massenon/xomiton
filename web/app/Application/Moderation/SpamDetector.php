<?php

declare(strict_types=1);

namespace App\Application\Moderation;

use App\Enums\PostStatus;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Post;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * Détection du spam (P18-07) sur le fil et les forums — **jamais les messages privés** (ni lus ni comptés). Appelée
 * après la publication d'une publication, d'un commentaire, d'un sujet ou d'une réponse (PublishPost, ManageComments,
 * StartTopic, ReplyToTopic). Deux signaux, seuils dans `config('xomiton.moderation.spam')` :
 *  - **fréquence** : au moins `max_per_window` contenus de la même personne (les quatre natures confondues) en
 *    `window_minutes` minutes ;
 *  - **doublons** : le même texte (sans casse, espaces ni ponctuation, au moins `duplicate_min_length` caractères)
 *    publié au moins `duplicate_threshold` fois en `duplicate_hours` heures, ce contenu compris.
 * Au seuil, le contenu qui le franchit est mis en **quarantaine** : masqué (ContentVisibility, comme RG-16) et signalé à
 * la modération avec le motif « Spam » (ReportContent::spam) ; « Approuver » le rend visible. Journal
 * `moderation.spam_quarantined` (signal et nombre, sans texte, acteur : système). L'équipe de modération
 * (`posts.moderate`) n'est jamais concernée ; un contenu déjà masqué ou non publié non plus.
 */
final class SpamDetector
{
    public const SIGNAL_FREQUENCY = 'frequence';

    public const SIGNAL_DUPLICATES = 'doublons';

    /** Contenus récents relus au plus par nature pour les doublons. */
    private const DUPLICATE_SCAN = 50;

    public function __construct(private readonly ReportContent $reports) {}

    /**
     * @return string|null signal déclenché (`frequence`, `doublons`), null si le contenu passe
     */
    public function inspect(Post|Comment|ForumTopic|ForumReply $subject, User $author): ?string
    {
        $config = (array) config('xomiton.moderation.spam', []);

        if (! (bool) ($config['enabled'] ?? true) || $author->can('posts.moderate') || $subject->getRawOriginal('status') !== PostStatus::Published->value) {
            return null;
        }

        [$signal, $count] = $this->signal($subject, $author, $config);

        if ($signal === null) {
            return null;
        }

        $this->reports->spam($subject, $signal, $count);
        Audit::logWithoutRequest('moderation.spam_quarantined', $subject, sprintf('signal=%s ; nombre=%d', $signal, $count), AuditLog::ACTOR_SYSTEM);

        return $signal;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{0: string|null, 1: int}
     */
    private function signal(Model $subject, User $author, array $config): array
    {
        $max = max(2, (int) ($config['max_per_window'] ?? 8));
        $since = now()->subMinutes(max(1, (int) ($config['window_minutes'] ?? 10)));
        $recent = 0;

        foreach ([Post::class, Comment::class, ForumTopic::class, ForumReply::class] as $class) {
            $recent += $class::query()->where('user_id', $author->getKey())->where('created_at', '>=', $since)->count();
        }

        if ($recent >= $max) {
            return [self::SIGNAL_FREQUENCY, $recent];
        }

        $needle = self::normalize($this->text($subject));
        $threshold = max(2, (int) ($config['duplicate_threshold'] ?? 3));

        if (mb_strlen($needle) < max(1, (int) ($config['duplicate_min_length'] ?? 15))) {
            return [null, 0];
        }

        $since = now()->subHours(max(1, (int) ($config['duplicate_hours'] ?? 24)));
        $same = 0;

        foreach ([Post::class, Comment::class, ForumTopic::class, ForumReply::class] as $class) {
            $class::query()->where('user_id', $author->getKey())->where('created_at', '>=', $since)
                ->latest('id')->limit(self::DUPLICATE_SCAN)->get()
                ->each(function (Model $item) use ($needle, &$same): void {
                    if (self::normalize($this->text($item)) === $needle) {
                        $same++;
                    }
                });
        }

        return $same >= $threshold ? [self::SIGNAL_DUPLICATES, $same] : [null, 0];
    }

    private function text(Model $item): string
    {
        $body = (string) $item->getAttribute('body');

        return $item instanceof ForumTopic ? $item->title.' '.$body : $body;
    }

    /** Texte comparable : minuscules, sans accents, ponctuation ni espaces multiples. */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Comment;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Message;
use App\Models\Post;
use App\Models\User;

/**
 * Nature du contenu signalé (DATABASE_DICTIONARY.md §7 `moderation_reports.kind`). Le prototype ne connaissait
 * que `publication`, `commentaire`, `utilisateur` ; `message`, `sujet` et `reponse` couvrent la messagerie et les forums.
 */
enum ModerationKind: string
{
    case Post = 'publication';
    case Comment = 'commentaire';
    case Message = 'message';
    case User = 'utilisateur';
    case Topic = 'sujet';
    case Reply = 'reponse';

    public function label(): string
    {
        return (string) __('enums/steering.moderation_kind.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Post => 'primary',
            self::Comment => 'info',
            self::Message => 'violet',
            self::User => 'danger',
            self::Topic => 'primary',
            self::Reply => 'info',
        };
    }

    /**
     * Classe Eloquent de la cible (`subject_type`), pour construire ou valider le signalement.
     *
     * @return class-string
     */
    public function subjectClass(): string
    {
        return match ($this) {
            self::Post => Post::class,
            self::Comment => Comment::class,
            self::Message => Message::class,
            self::User => User::class,
            self::Topic => ForumTopic::class,
            self::Reply => ForumReply::class,
        };
    }

    /**
     * Nature déduite de la classe de la cible ; null si la classe n'est pas signalable.
     *
     * @param  class-string  $class
     */
    public static function forSubjectClass(string $class): ?self
    {
        foreach (self::cases() as $kind) {
            if ($kind->subjectClass() === $class) {
                return $kind;
            }
        }

        return null;
    }
}

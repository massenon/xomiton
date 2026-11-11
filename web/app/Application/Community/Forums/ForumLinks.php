<?php

declare(strict_types=1);

namespace App\Application\Community\Forums;

use App\Models\Forum;
use App\Models\ForumTopic;
use Illuminate\Support\Facades\Route;

/**
 * Adresses des forums (ADR-27 : un sujet par ULID, jamais par id) : liste `/forums` (filtrée `?forum=f1`, clé courte du
 * prototype) et sujet `/forums/{ulid}` (ancre `#reponses` pour la cloche).
 */
final class ForumLinks
{
    private function __construct() {}

    public static function index(?Forum $forum = null): string
    {
        $parameters = $forum !== null ? ['forum' => $forum->key] : [];

        return Route::has('forums.index') ? route('forums.index', $parameters) : url('/forums'.($forum !== null ? '?forum='.$forum->key : ''));
    }

    public static function show(ForumTopic $topic, ?string $anchor = null): string
    {
        $url = Route::has('forums.show') ? route('forums.show', ['topic' => $topic->ulid]) : url('/forums/'.$topic->ulid);

        return $anchor !== null ? $url.'#'.$anchor : $url;
    }
}

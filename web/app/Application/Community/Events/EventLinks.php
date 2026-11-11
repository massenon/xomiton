<?php

declare(strict_types=1);

namespace App\Application\Community\Events;

use App\Models\Event;
use Illuminate\Support\Facades\Route;

/**
 * Adresse d'un événement (ADR-27 : par ULID, jamais par id) : `/evenements/{ulid}` — page Vue pour un·e membre, page
 * Blade publique pour un·e visiteur·se si l'événement est public (Q35 a), même adresse dans les deux cas.
 */
final class EventLinks
{
    private function __construct() {}

    public static function show(Event $event): string
    {
        return Route::has('evenements.show') ? route('evenements.show', ['event' => $event->ulid]) : url('/evenements/'.$event->ulid);
    }

    public static function index(): string
    {
        return Route::has('evenements.index') ? route('evenements.index') : url('/evenements');
    }
}

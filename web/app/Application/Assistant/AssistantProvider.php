<?php

declare(strict_types=1);

namespace App\Application\Assistant;

use App\Application\Shared\Answer;

/**
 * Fournisseur de réponses de l'assistant membre (P8-11, Q7 non tranchée, Q41 : LLM réservé aux membres).
 *
 * Pilote choisi par `config('xomiton.assistant.provider')` (`ASSISTANT_PROVIDER`), résolu par
 * App\Infrastructure\Assistant\AssistantProviders : `kb` (défaut, seul pilote livré) répond depuis la base de
 * connaissances (KnowledgeBaseAnswerer, P3-09). Point d'extension pour un LLM, une fois le fournisseur choisi par le
 * client : une classe dans App\Infrastructure\Assistant qui implémente cette interface, ajoutée à
 * AssistantProviders::DRIVERS, qui lit sa clé dans `config/xomiton.php` (jamais en dur ni dans le dépôt) et qui :
 *  - n'est **jamais** appelée pour un message de crise ni pour un·e visiteur·se : AskAssistant répond alors par la base
 *    (numéros d'urgence, SOS, escalade) avant toute résolution du pilote ;
 *  - renvoie un Answer de nature `kb` ou `fallback` (jamais `crisis`, réservé aux garde-fous), texte en tu
 *    (VOICE_AND_TONE.md §3), sans HTML ;
 *  - enregistre l'échange par KnowledgeBaseAnswerer::record() quand `$query->persist` est vrai (corps chiffrés),
 *    **en dernier**, une fois la réponse obtenue : rien ne doit pouvoir échouer après (AskAssistant annule de toute façon
 *    un enregistrement suivi d'une exception, la base répondant et enregistrant à sa place) ;
 *  - borne elle-même sa requête (délai court) : une exception est journalisée et la base répond à sa place.
 */
interface AssistantProvider
{
    /** Nom du pilote (journal, écran d'administration P12-07). */
    public function name(): string;

    public function answer(AssistantQuery $query): Answer;
}

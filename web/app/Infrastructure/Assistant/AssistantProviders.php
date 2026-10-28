<?php

declare(strict_types=1);

namespace App\Infrastructure\Assistant;

use App\Application\Assistant\AssistantProvider;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

/**
 * Résout le fournisseur de l'assistant membre (P8-11, Q7) à partir de `config('xomiton.assistant.provider')`
 * (`ASSISTANT_PROVIDER`), comme NotificationDrivers pour les canaux externes (ADR-25).
 *
 * Point d'extension : ajouter la classe du pilote dans DRIVERS (elle implémente AssistantProvider, voir son contrat :
 * jamais appelée en crise ni pour un·e visiteur·se) et choisir son nom dans `.env`. Vide, `null` ou un nom inconnu
 * (ou une classe absente) retombent sur `kb` — un nom inconnu avec un avertissement : l'assistant ne doit jamais
 * tomber en panne sur une erreur de configuration.
 */
final class AssistantProviders
{
    public const DEFAULT = KnowledgeBaseProvider::NAME;

    /** @var array<string, class-string<AssistantProvider>|string> */
    public const DRIVERS = [
        KnowledgeBaseProvider::NAME => KnowledgeBaseProvider::class,
        // Q7 : Gemini, désactivé par défaut (`xomiton.assistant.llm_enabled`) ; ASSISTANT_PROVIDER=gemini ne suffit pas.
        GeminiAssistantProvider::NAME => GeminiAssistantProvider::class,
    ];

    private function __construct() {}

    public static function make(Container $app): AssistantProvider
    {
        /** @var AssistantProvider $provider */
        $provider = $app->make(self::DRIVERS[self::effective(config('xomiton.assistant.provider'))]);

        return $provider;
    }

    /**
     * Nom effectif du pilote après repli (affichage en administration P12-07 et tests).
     */
    public static function effective(mixed $driver): string
    {
        $driver = is_string($driver) ? strtolower(trim($driver)) : '';

        if ($driver === '' || $driver === 'null') {
            return self::DEFAULT;
        }

        $class = self::DRIVERS[$driver] ?? null;

        if ($class === null || ! class_exists($class)) {
            Log::warning(sprintf('Assistant : pilote « %s » inconnu ou absent, repli sur %s.', $driver, self::DEFAULT));

            return self::DEFAULT;
        }

        // Le drapeau llm_enabled coupe tout pilote LLM : sans lui, la base de connaissances répond (et nul appel n'est possible).
        if ($driver !== self::DEFAULT && ! (bool) config('xomiton.assistant.llm_enabled', false)) {
            return self::DEFAULT;
        }

        return $driver;
    }
}

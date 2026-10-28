<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Infrastructure\Assistant\GeminiAssistantProvider;
use Illuminate\Console\Command;

/**
 * Essai de l'appel Gemini (clé, modèle, délai) avec une question fixe sans donnée réelle. Respecte le drapeau
 * `xomiton.assistant.llm_enabled` : désactivé, aucun appel n'est fait. La réponse n'est affichée qu'ici (question de test).
 */
class TestLlmCommand extends Command
{
    protected $signature = 'assistant:test-llm {--yes : Sans confirmation}';

    protected $description = 'Teste l’appel Gemini avec une question fixe (refuse si ASSISTANT_LLM_ENABLED est faux ou si la clé manque)';

    public function handle(GeminiAssistantProvider $gemini): int
    {
        if (! (bool) config('xomiton.assistant.llm_enabled', false)) {
            $this->warn('ASSISTANT_LLM_ENABLED est faux : aucun appel (activation subordonnée à l’avis juridique, DECISIONS_A_VALIDER.md).');

            return self::FAILURE;
        }

        $key = config('xomiton.assistant.gemini.api_key');

        if (! is_string($key) || $key === '') {
            $this->error('GEMINI_API_KEY manquante.');

            return self::FAILURE;
        }

        if (! $this->option('yes') && ! $this->confirm('Appeler Gemini (modèle '.config('xomiton.assistant.gemini.model').') avec une question de test ?', false)) {
            $this->warn('Annulé.');

            return self::FAILURE;
        }

        try {
            $answer = $gemini->generate('Bonjour, peux-tu m’expliquer en une phrase à quoi sert cette application ?');
        } catch (\Throwable $e) {
            $this->error('Échec : '.class_basename($e));

            return self::FAILURE;
        }

        if ($answer === null) {
            $this->error('Aucune réponse exploitable (erreur HTTP, blocage de sécurité ou réponse vide).');

            return self::FAILURE;
        }

        $this->info('Réponse reçue ('.mb_strlen($answer).' caractères) : '.$answer);

        return self::SUCCESS;
    }
}

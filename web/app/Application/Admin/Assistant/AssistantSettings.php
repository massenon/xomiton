<?php

declare(strict_types=1);

namespace App\Application\Admin\Assistant;

use App\Infrastructure\Assistant\AssistantProviders;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;

/**
 * Réglages de l'assistant (P12-07 ; clés `assistant_*` du groupe `assistant`, semées par SettingsSeeder) :
 *  - `assistant_enabled` : fournisseur LLM actif. Désactivé, les membres reçoivent la réponse de la base de connaissances
 *    (comme les visiteur·ses) : le pilote `kb` ne dépend pas de l'interrupteur et les garde-fous de crise s'appliquent
 *    toujours, avant tout fournisseur (AskAssistant) ;
 *  - `assistant_prompt` : consigne système transmise au fournisseur LLM (Q7 non tranchée : le pilote `kb` ne s'en sert pas) ;
 *  - `assistant_guardrails` et `assistant_escalation` : consignes de sécurité et d'escalade transmises au fournisseur.
 * Ni le texte de la consigne ni les mots-clés ne sont écrits dans le journal d'audit.
 */
final class AssistantSettings
{
    public const PROMPT_MIN = 100;

    public const PROMPT_MAX = 6000;

    public const KEYWORDS_MAX = 500;

    /** Garde-fous du prototype (admin/assistant.php), tous actifs par défaut. */
    public const GUARDRAILS = ['no_diagnosis', 'no_pii', 'topic_filter', 'sos_banner', 'log_anon', 'disclaimer'];

    /** Destinataires de l'escalade (prototype). */
    public const TARGETS = ['focal', 'psychologist', 'jurist', 'all_focal'];

    public static function enabled(): bool
    {
        return Settings::bool('assistant_enabled', true);
    }

    /** Nom effectif du pilote après repli (App\Infrastructure\Assistant\AssistantProviders). */
    public static function provider(): string
    {
        return AssistantProviders::effective(config('xomiton.assistant.provider'));
    }

    public static function prompt(): string
    {
        return Settings::string('assistant_prompt', self::defaultPrompt());
    }

    /** Consigne par défaut : celle des paramètres semés. */
    public static function defaultPrompt(): string
    {
        $file = database_path('seeders/data/platform/settings.php');
        $defaults = is_file($file) ? (array) require $file : [];

        return (string) ($defaults['assistant_prompt'][2] ?? '');
    }

    /**
     * @return array<string, bool>
     */
    public static function guardrails(): array
    {
        $stored = Settings::array('assistant_guardrails');

        return array_combine(self::GUARDRAILS, array_map(static fn (string $key): bool => (bool) ($stored[$key] ?? true), self::GUARDRAILS));
    }

    /**
     * @return array{on: bool, keywords: string, target: string}
     */
    public static function escalation(): array
    {
        $stored = Settings::array('assistant_escalation');
        $target = (string) ($stored['target'] ?? 'focal');

        return [
            'on' => (bool) ($stored['on'] ?? true),
            'keywords' => (string) ($stored['keywords'] ?? ''),
            'target' => in_array($target, self::TARGETS, true) ? $target : 'focal',
        ];
    }

    public function setEnabled(bool $enabled, User $actor): void
    {
        Settings::set('assistant_enabled', $enabled, (int) $actor->getKey());
        Audit::log($enabled ? 'assistant.enabled' : 'assistant.disabled', null, null, $actor);
    }

    public function savePrompt(string $prompt, User $actor): void
    {
        Settings::set('assistant_prompt', trim($prompt), (int) $actor->getKey());
        Audit::log('assistant.prompt_saved', null, null, $actor);
    }

    public function resetPrompt(User $actor): string
    {
        $this->savePrompt(self::defaultPrompt(), $actor);

        return self::defaultPrompt();
    }

    /**
     * @param  array<string, bool>  $guardrails
     * @param  array{on: bool, keywords: string, target: string}  $escalation
     */
    public function saveGuardrails(array $guardrails, array $escalation, User $actor): void
    {
        Settings::set('assistant_guardrails', array_combine(self::GUARDRAILS, array_map(static fn (string $key): bool => (bool) ($guardrails[$key] ?? false), self::GUARDRAILS)), (int) $actor->getKey());
        Settings::set('assistant_escalation', [
            'on' => $escalation['on'],
            'keywords' => trim($escalation['keywords']),
            'target' => in_array($escalation['target'], self::TARGETS, true) ? $escalation['target'] : 'focal',
        ], (int) $actor->getKey());
        Audit::log('assistant.guardrails_saved', null, null, $actor);
    }
}

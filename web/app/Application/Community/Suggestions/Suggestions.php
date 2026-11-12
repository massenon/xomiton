<?php

declare(strict_types=1);

namespace App\Application\Community\Suggestions;

use App\Enums\SuggestionCategory;
use App\Enums\SuggestionStatus;
use App\Models\Suggestion;
use App\Models\User;
use App\Notifications\TeamNotice;
use App\Support\Audit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Boîte à suggestions (P18-14) : un·e membre écrit une suggestion (catégorie, message ≤ 1000 caractères, anonyme
 * possible) depuis l'espace membre web, l'application ou l'API ; l'équipe (`suggestions.manage`) la lit dans
 * `/admin/suggestions`, change son statut (nouvelle, en cours, retenue, écartée) et peut répondre : la réponse est envoyée
 * en notification à son auteur·rice (même anonyme : le compte reste en base, jamais affiché). Journal `suggestions.*`
 * sans texte.
 */
final class Suggestions
{
    public const MESSAGE_MIN = 10;

    public const MESSAGE_MAX = 1000;

    public const REPLY_MAX = 1000;

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(SuggestionCategory::class)],
            'message' => ['required', 'string', 'min:'.self::MESSAGE_MIN, 'max:'.self::MESSAGE_MAX],
            'is_anonymous' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{categories: list<array{value: string, label: string}>, limits: array{message_min: int, message_max: int}}
     */
    public static function options(): array
    {
        return [
            'categories' => SuggestionCategory::options(),
            'limits' => ['message_min' => self::MESSAGE_MIN, 'message_max' => self::MESSAGE_MAX],
        ];
    }

    /**
     * @param  array{category: string, message: string, is_anonymous?: bool}  $data
     */
    public function submit(User $author, array $data): Suggestion
    {
        $suggestion = Suggestion::query()->create([
            'user_id' => $author->getKey(),
            'category' => SuggestionCategory::from($data['category'])->value,
            'message' => trim($data['message']),
            'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
            'status' => SuggestionStatus::New->value,
        ]);

        Audit::log('suggestions.submitted', $suggestion, 'category='.$suggestion->suggestionCategory()->value, $author);

        return $suggestion;
    }

    public function setStatus(Suggestion $suggestion, SuggestionStatus $status, User $actor): bool
    {
        if ($suggestion->suggestionStatus() === $status) {
            return false;
        }

        $suggestion->forceFill(['status' => $status->value])->save();
        Audit::log('suggestions.status_changed', $suggestion, 'status='.$status->value, $actor);

        return true;
    }

    /** Réponse de l'équipe, envoyée en notification à l'auteur·rice (si le compte existe et est actif). */
    public function reply(Suggestion $suggestion, string $reply, User $actor): void
    {
        $reply = trim($reply);
        $suggestion->forceFill([
            'reply' => $reply,
            'replied_by' => $actor->getKey(),
            'replied_at' => now(),
            'status' => $suggestion->suggestionStatus() === SuggestionStatus::New ? SuggestionStatus::InProgress->value : $suggestion->suggestionStatus()->value,
        ])->save();

        Audit::log('suggestions.replied', $suggestion, null, $actor);

        // Compte relu en entier : l'écran charge la relation réduite (id, pseudo), sans le statut.
        $author = $suggestion->user_id !== null ? User::query()->find($suggestion->user_id) : null;

        if ($author === null || ! $author->isActive()) {
            return;
        }

        try {
            $author->notify(new TeamNotice(TeamNotice::SUGGESTION_REPLIED, Str::limit((string) $suggestion->message, 60), $reply));
        } catch (Throwable $exception) {
            Log::warning('Boîte à suggestions : réponse non notifiée.', ['suggestion_id' => $suggestion->getKey(), 'error' => $exception->getMessage()]);
        }
    }
}

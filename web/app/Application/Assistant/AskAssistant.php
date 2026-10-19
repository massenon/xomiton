<?php

declare(strict_types=1);

namespace App\Application\Assistant;

use App\Application\Shared\Answer;
use App\Application\Shared\KnowledgeBaseAnswerer;
use App\Models\AssistantConversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Question posée à l'assistant (P8-11, `POST /api/v1/assistant/question`, page /assistant), dans cet ordre :
 *  1. garde-fous de crise (KnowledgeBaseAnswerer::detectCrisis) : réponse de la base avec numéros d'urgence, SOS et
 *     escalade, **sans appel au fournisseur** (ni réseau ni latence, rien ne part vers un tiers) ;
 *  2. visiteur·se (offcanvas public, Q41 a) : la base seule, jamais de fournisseur ni d'enregistrement ;
 *  3. membre : le fournisseur configuré (AssistantProvider, pilote `kb` par défaut = la base) ; s'il échoue, la base.
 *     L'appel au fournisseur passe dans une transaction : un échange qu'il aurait déjà enregistré (record()) avant
 *     d'échouer est annulé, la base l'enregistre une seule fois (pas de messages en double).
 * Enregistrement seulement sur demande d'un compte connecté (`persist`, historique non persistant par défaut, Q55),
 * dans une conversation existante de la personne (`conversation_id`, 404 sinon) ou une nouvelle.
 */
final class AskAssistant
{
    public function __construct(
        private readonly KnowledgeBaseAnswerer $answerer,
        private readonly AssistantProvider $provider,
    ) {}

    public function __invoke(string $question, ?User $user = null, bool $persist = false, ?int $conversationId = null): Answer
    {
        $persist = $persist && $user !== null;
        $conversation = $persist && $conversationId !== null ? self::conversationOf($user, $conversationId) : null;

        if ($user === null || KnowledgeBaseAnswerer::detectCrisis($question) !== null) {
            return $this->answerer->answer($question, $user, $persist, $conversation);
        }

        try {
            $query = new AssistantQuery($question, $user, $persist, $conversation);

            return DB::transaction(fn (): Answer => $this->provider->answer($query));
        } catch (Throwable $e) {
            Log::warning(sprintf('Assistant : le fournisseur « %s » a échoué (%s), réponse de la base.', $this->provider->name(), $e::class));

            return $this->answerer->answer($question, $user, $persist, $conversation);
        }
    }

    /**
     * Conversation enregistrée de la personne ; celle d'un autre compte n'existe pas pour elle (404, sans oracle).
     */
    public static function conversationOf(User $user, int $conversationId): AssistantConversation
    {
        return AssistantConversation::query()->ownedBy($user)->whereKey($conversationId)->firstOrFail();
    }
}

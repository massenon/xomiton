<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member;

use App\Application\Shared\KnowledgeBaseAnswerer;
use App\Enums\ContactChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AssistantQuestionRequest;
use App\Http\Requests\Assistant\EscalateToHumanRequest;
use App\Http\Resources\AssistantAnswerResource;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écran Vue « Assistant » (P8-11, prototype assistant.php ; Q7, Q41, Q55) : page Inertia `Assistant/Index` dans le
 * gabarit membre. La conversation passe par `POST /api/v1/assistant/question` (AskAssistant : garde-fous, fournisseur,
 * `persist` facultatif) ; l'historique reste dans l'onglet (sessionStorage, rien en mode discret). Les props portent de
 * quoi répondre à un message de crise **sans appel réseau** : mots-clés normalisés comme KnowledgeBaseAnswerer::normalize,
 * texte de crise (numéros de `settings.emergency_numbers`, même source que /urgences) et liens de l'API (SOS, `tel:`,
 * besoin, signalement, urgences), plus « Parler à un humain » (`POST /api/v1/assistant/escalate`).
 */
final class AssistantPageController extends Controller
{
    public function __invoke(): Response
    {
        $title = (string) __('menus.member_pages.assistant');
        $numbers = KnowledgeBaseAnswerer::emergencyNumbers();
        $first = $numbers[0] ?? null;

        return Inertia::render('Assistant/Index', [
            'title' => $title,
            'chips' => array_values(array_map('strval', (array) __('assistant.page.chips'))),
            'max_length' => AssistantQuestionRequest::MAX_LENGTH,
            'crisis' => [
                'keywords' => self::crisisKeywords(),
                'text' => KnowledgeBaseAnswerer::crisisText(false, true),
                'links' => AssistantAnswerResource::crisisLinks(),
            ],
            'offline' => [
                'text' => $first === null
                    ? (string) __('components.assistant.offline_no_number')
                    : (string) __('components.assistant.offline', ['number' => $first['number']]),
                'escalation' => $first === null
                    ? (string) __('assistant.escalation.offline_no_number')
                    : (string) __('assistant.escalation.offline', ['number' => $first['number']]),
                'call' => $first === null ? null : [
                    'label' => (string) __('assistant.links.call', ['number' => $first['number'], 'label' => $first['label']]),
                    'url' => KnowledgeBaseAnswerer::telHref($first['number']),
                ],
            ],
            'escalation' => [
                'channels' => collect(ContactChannel::cases())
                    ->mapWithKeys(static fn (ContactChannel $channel): array => [$channel->value => $channel->label()])
                    ->all(),
                'message_max' => EscalateToHumanRequest::messageMax(),
            ],
            'links' => [
                'sos' => route('sos'),
                'urgences' => route('urgences'),
                'annuaire' => Route::has('annuaire') ? route('annuaire') : url('/annuaire'),
            ],
        ])->withViewData(['title' => $title]);
    }

    /**
     * Mots-clés de crise sous leur forme comparable (minuscules, sans accent ni ponctuation) : la page n'a plus qu'à
     * normaliser le message de la même façon et chercher le mot entier (pluriel en -s / -x toléré).
     *
     * @return list<string>
     */
    private static function crisisKeywords(): array
    {
        $keywords = [];

        foreach ((array) config('xomiton.assistant.crisis_keywords', []) as $keyword) {
            $normalized = is_string($keyword) ? KnowledgeBaseAnswerer::normalize($keyword) : '';

            if ($normalized !== '' && ! in_array($normalized, $keywords, true)) {
                $keywords[] = $normalized;
            }
        }

        return $keywords;
    }
}

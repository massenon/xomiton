<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Content\HelpCenter;
use App\Application\Content\RecordFaqFeedback;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\FaqFeedbackRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Vote « Cet article vous a-t-il aidé·e ? » (`POST /aide/{slug}/avis`, P4-05, limiteur `faq_feedback`) : enregistré par
 * RecordFaqFeedback. Avec JavaScript (resources/js/public/aide.js, `Accept: application/json`) : 200 + remerciement ;
 * sans : retour sur l'article (ancre #avis) avec le remerciement en session flash. Question hors du centre d'aide → 404.
 */
final class AideFeedbackController extends Controller
{
    public function __invoke(FaqFeedbackRequest $request, string $slug, HelpCenter $helpCenter, RecordFaqFeedback $record): JsonResponse|RedirectResponse
    {
        $faq = $helpCenter->faqs()->where('slug', $slug)->firstOrFail();
        $helpful = $request->helpful();

        $record($faq, $helpful, $request);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __($helpful ? 'pages/aide.feedback.thanks_yes' : 'pages/aide.feedback.thanks_no'),
                'helpful' => $helpful,
            ]);
        }

        return redirect()->to(route('aide.show', $faq->slug).'#avis')
            ->with('aide_feedback', ['slug' => $faq->slug, 'helpful' => $helpful]);
    }
}

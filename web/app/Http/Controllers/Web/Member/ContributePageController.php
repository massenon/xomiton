<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member;

use App\Application\Community\Suggestions\Suggestions;
use App\Application\Content\Proposals\ResourceProposalRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contributions des membres (P18-12, P18-14), pages Inertia du gabarit membre chargées à la demande :
 *  - `/proposer-une-ressource` → `Member/ResourceProposal` (formulaire : titre, type, résumé, catégorie, lien ou PDF,
 *    crédit ; envoi `POST /api/v1/resource-proposals`) ;
 *  - `/boite-a-idees` → `Member/Suggestion` (catégorie, message ≤ 1000, anonyme ; envoi `POST /api/v1/suggestions`).
 * Sans `social.access` : 403, comme l'API.
 */
final class ContributePageController extends Controller
{
    public function proposal(Request $request): Response
    {
        $this->authorizeSocial($request);
        $title = (string) __('community.proposals.title');

        return Inertia::render('Member/ResourceProposal', [
            'title' => $title,
            'options' => ResourceProposalRules::options(),
        ])->withViewData(['title' => $title]);
    }

    public function suggestion(Request $request): Response
    {
        $this->authorizeSocial($request);
        $title = (string) __('community.suggestions.title');

        return Inertia::render('Member/Suggestion', [
            'title' => $title,
            'options' => Suggestions::options(),
        ])->withViewData(['title' => $title]);
    }

    private function authorizeSocial(Request $request): void
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('social.access'), 403);
    }
}

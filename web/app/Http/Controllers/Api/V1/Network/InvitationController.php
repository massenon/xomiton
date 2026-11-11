<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Network;

use App\Application\Community\Network\InvitationDirectory;
use App\Application\Community\Network\ManageInvitations;
use App\Http\Controllers\Api\V1\Community\CommunityApiController;
use App\Http\Requests\Api\Network\ListInvitationsRequest;
use App\Http\Requests\Api\Network\StoreInvitationRequest;
use App\Http\Resources\Network\InvitationResource;
use App\Models\Invitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * /api/v1/invitations (P8-06, docs/API.md §17) : invitations reçues ou envoyées en attente (compteurs des deux onglets
 * dans `meta.counts`), inviter (201, ou 200 avec l'invitation déjà en attente : jamais de doublon), accepter, refuser,
 * annuler. Pagination par curseur (`meta.next_cursor`). Invitation d'une autre personne ou entre comptes qui se
 * bloquent → 404.
 */
final class InvitationController extends CommunityApiController
{
    public function __construct(
        private readonly InvitationDirectory $invitations,
        private readonly ManageInvitations $manage,
    ) {}

    public function index(ListInvitationsRequest $request): JsonResponse
    {
        $viewer = $request->viewer();
        $box = $request->box();
        $page = $this->invitations->page($viewer, $box, $this->perPage($request, (int) config('xomiton.network.invitations_per_page', 20)), $request->cursor());

        return $this->data(
            InvitationResource::collection($page->items)->resolve($request),
            200,
            $page->meta() + ['box' => $box, 'counts' => $this->invitations->counts($viewer)],
        );
    }

    public function store(StoreInvitationRequest $request): JsonResponse
    {
        $viewer = $request->viewer();
        ['invitation' => $invitation, 'created' => $created] = $this->manage->send($viewer, $request->targetId());
        $invitation->loadMissing(['toUser.profile', 'toUser.roles', 'toUser.zone']);

        return $this->respond(InvitationResource::make($invitation), $created ? 201 : 200, ['created' => $created, 'counts' => $this->invitations->counts($viewer)]);
    }

    public function accept(Request $request, Invitation $invitation): JsonResponse
    {
        $viewer = $this->viewer($request);
        abort_unless($viewer->can('respond', $this->visible($request, $invitation)), 403);
        $invitation = $this->manage->accept($viewer, $invitation)->loadMissing(['fromUser.profile', 'fromUser.roles', 'fromUser.zone']);

        return $this->respond(InvitationResource::make($invitation), meta: ['counts' => $this->invitations->counts($viewer)]);
    }

    public function refuse(Request $request, Invitation $invitation): JsonResponse
    {
        $viewer = $this->viewer($request);
        abort_unless($viewer->can('respond', $this->visible($request, $invitation)), 403);
        $invitation = $this->manage->refuse($viewer, $invitation)->loadMissing(['fromUser.profile', 'fromUser.roles', 'fromUser.zone']);

        return $this->respond(InvitationResource::make($invitation), meta: ['counts' => $this->invitations->counts($viewer)]);
    }

    /**
     * Une invitation qui n'est plus en attente (acceptée, refusée) répond 404 à la personne qui a invité, comme un
     * blocage ou une suspension : elle ne distingue jamais un refus (Q79).
     */
    public function destroy(Request $request, Invitation $invitation): Response
    {
        $viewer = $this->viewer($request);
        abort_unless($viewer->can('cancel', $this->visible($request, $invitation)), 403);

        if (! $this->manage->cancel($viewer, $invitation)) {
            throw new NotFoundHttpException;
        }

        return $this->noContent();
    }

    /**
     * @throws NotFoundHttpException
     */
    private function visible(Request $request, Invitation $invitation): Invitation
    {
        if (! $this->viewer($request)->can('view', $invitation)) {
            throw new NotFoundHttpException;
        }

        return $invitation;
    }
}

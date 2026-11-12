<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Content;

use App\Application\Content\Proposals\ProposeResource;
use App\Application\Content\Proposals\ResourceProposalRules;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\User;
use App\Support\NamedRateLimit;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Ressources proposées par les membres (P18-12, docs/API.md §11 bis) : `GET resource-proposals/options` (types,
 * catégories, limites du formulaire) et `POST resource-proposals` (multipart : `title`, `kind`, `summary`, `category_id`,
 * `external_url`, `file` PDF, `credit_author`) → 201 `{id, status: "proposee"}`. La proposition n'est jamais publique.
 * Exige `social.access` et un e-mail confirmé ; limiteur `resource_proposal` par compte et par jour, compté après
 * validation (un formulaire refusé ne brûle pas la tentative).
 */
final class ResourceProposalController extends ApiController
{
    public function options(Request $request): JsonResponse
    {
        abort_unless($this->user($request)->can('social.access'), 403);

        return $this->data(ResourceProposalRules::options());
    }

    public function store(Request $request, ProposeResource $propose): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($user->can('social.access'), 403);

        /** @var array{title: string, kind: string, summary: string, category_id?: int|null, external_url?: string|null, credit_author?: bool} $data */
        $data = $request->validate(ResourceProposalRules::rules(), [], self::attributes());

        if (($retryAfter = NamedRateLimit::attempt('resource_proposal', $request)) !== null) {
            throw new ThrottleRequestsException((string) __('auth.throttle', ['seconds' => $retryAfter]), null, ['Retry-After' => (string) $retryAfter]);
        }

        $file = $request->file('file');
        $proposal = $propose($user, $data, $file instanceof UploadedFile ? $file : null);

        return $this->created(['id' => (int) $proposal->getKey(), 'status' => $proposal->proposalStatus()->value]);
    }

    /** @return array<string, string> */
    private static function attributes(): array
    {
        return [
            'title' => (string) __('community.proposals.fields.title'),
            'kind' => (string) __('community.proposals.fields.kind'),
            'summary' => (string) __('community.proposals.fields.summary'),
            'category_id' => (string) __('community.proposals.fields.category'),
            'external_url' => (string) __('community.proposals.fields.link'),
            'file' => (string) __('community.proposals.fields.file'),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}

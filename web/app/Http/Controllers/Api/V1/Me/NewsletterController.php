<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Me;

use App\Application\Account\MemberNewsletter;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Me\UpdateNewsletterRequest;
use App\Http\Resources\Me\NewsletterResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET / PATCH /api/v1/me/newsletter (P6-06) : abonnement et centres d'intérêt du compte (App\Application\Account\MemberNewsletter).
 */
final class NewsletterController extends ApiController
{
    public function __construct(private readonly MemberNewsletter $newsletter) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->respond(new NewsletterResource($this->newsletter->find($user)));
    }

    public function update(UpdateNewsletterRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->respond(new NewsletterResource($this->newsletter->update($user, $request->subscribed(), $request->interests())));
    }
}

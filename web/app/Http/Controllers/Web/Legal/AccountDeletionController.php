<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Legal;

use App\Application\Account\PublicAccountDeletion;
use App\Application\Legal\LegalTexts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Legal\RequestAccountDeletionRequest;
use App\Support\NamedRateLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Page publique « Supprimer mon compte et mes données » (`/supprimer-mon-compte`, exigence Google Play : accessible sans
 * connexion). GET : procédure dans l'application et sur le web, délais, données effacées et conservées, formulaire pour
 * les personnes qui n'ont plus accès à leur compte. POST : demande par e-mail (réponse identique que le compte existe ou
 * non, limiteur `account_deletion`, pot de miel) → `/supprimer-mon-compte/demande-envoyee` ; lien reçu par e-mail →
 * `/supprimer-mon-compte/confirmer/{jeton}` (bouton POST : un anti-virus qui ouvre le lien ne supprime rien).
 * Pages de jeton et de remerciement en noindex, exclues de robots.txt et du plan du site.
 */
final class AccountDeletionController extends Controller
{
    public const FLASH = 'account_deletion_flash';

    public function show(): Response
    {
        $content = LegalTexts::resolve((array) __('pages/deletion.page'));

        return response()->view('public.suppression.show', [
            'content' => $content,
            'updatedAt' => (string) config('xomiton.content.legal_updated_at', '2026-10-29'),
            'seo' => [
                'title' => __('seo.pages.account_deletion.title'),
                'description' => __('seo.pages.account_deletion.description'),
                'breadcrumb' => [[(string) __('pages/deletion.title'), route('account-deletion.show')]],
            ],
        ]);
    }

    public function store(RequestAccountDeletionRequest $request, PublicAccountDeletion $deletion): RedirectResponse
    {
        if (($retryAfter = NamedRateLimit::attempt('account_deletion', $request)) !== null) {
            return redirect()->route('account-deletion.show', status: 303)
                ->withInput($request->only('email'))
                ->with(self::FLASH, (string) __('pages/deletion.form.too_many', ['minutes' => max(1, (int) ceil($retryAfter / 60))]))
                ->withHeaders(['Retry-After' => (string) $retryAfter]);
        }

        if (! $request->trapped()) {
            $deletion->request((string) $request->validated('email'));
        }

        return redirect()->route('account-deletion.sent', status: 303);
    }

    public function sent(): Response
    {
        return $this->noindex('sent', ['title' => (string) __('pages/deletion.sent.title')]);
    }

    public function confirmShow(string $token, PublicAccountDeletion $deletion): Response
    {
        return $deletion->pending($token) === null
            ? $this->noindex('invalid', ['title' => (string) __('pages/deletion.invalid.title')], 410)
            : $this->noindex('confirm', ['title' => (string) __('pages/deletion.confirm.title'), 'token' => $token]);
    }

    public function confirmStore(string $token, PublicAccountDeletion $deletion): Response
    {
        return $deletion->confirm($token)
            ? $this->noindex('done', ['title' => (string) __('pages/deletion.done.title')])
            : $this->noindex('invalid', ['title' => (string) __('pages/deletion.invalid.title')], 410);
    }

    /** @param array<string, mixed> $data */
    private function noindex(string $state, array $data, int $status = 200): Response
    {
        return response()->view('public.suppression.state', [
            'state' => $state,
            'texts' => LegalTexts::resolve((array) __('pages/deletion.'.$state)),
            'token' => $data['token'] ?? null,
            'seo' => ['title' => $data['title'], 'index' => false],
        ], $status)->withHeaders(['X-Robots-Tag' => 'noindex, nofollow']);
    }
}

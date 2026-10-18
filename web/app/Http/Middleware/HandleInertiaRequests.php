<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Member\MemberShell;
use App\Application\Offline\OutboxAccount;
use App\Models\User;
use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inertia de l'espace membre et pair (P6-02, D9, ADR-30 : sans SSR). Posé sur le groupe de routes membre (routes/web.php),
 * jamais sur les pages publiques ni l'administration. Props partagées (resources/js/app/types.ts `SharedProps`) :
 *  - auth.user : compte réduit (MemberShell::user), null sans session ;
 *  - can : permissions lues par l'interface (MemberShell::ABILITIES), la décision reste aux policies ;
 *  - counters : notifications et conversations non lues (en-tête, P6-03) ;
 *  - flash : toast / statut flashés en session (`toast`, `toast_type`, `status`, `status_type`) : `{id, message, type}`,
 *    type ramené à un ToastType (TOAST_TYPES, sinon primary), id propre à la réponse (app/flash.ts : jamais réaffiché
 *    au retour dans l'historique) ; le statut reste aussi à l'écran (XmFlashStatus) ;
 *  - lang : locale (messages vue-i18n chargés depuis resources/js/lang/<locale>.json, jamais envoyés à chaque visite) ;
 *  - ui : titre d'onglet neutre (user_settings.neutral_tab_title → « Notes »), cadence de la cloche, compte et durée de
 *    vie de la boîte d'envoi (même contrat que partials/offline.blade.php), maintien et décompte du bouton SOS.
 * Toute réponse porte `X-Robots-Tag: noindex, nofollow` (ADR-30 : aucun écran connecté n'est indexé) et
 * `Cache-Control: no-store, private` (appareil partagé : ni cache HTTP ni retour arrière restaurant une page membre
 * après la déconnexion) ; l'historique Inertia est chiffré (Inertia::encryptHistory(), clé effacée à la déconnexion par
 * Inertia::clearHistory() dans Auth\LogoutController).
 */
class HandleInertiaRequests extends Middleware
{
    /** Types de toast connus de XmToast (resources/js/app/types.ts `ToastType`) : classe text-bg-{type}. */
    public const TOAST_TYPES = ['primary', 'secondary', 'success', 'danger', 'warning', 'info'];

    /**
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(private readonly MemberShell $shell) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function handle(Request $request, Closure $next): Response
    {
        Inertia::encryptHistory();

        $response = parent::handle($request, $next);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $user = $user instanceof User ? $user : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn (): ?array => $user !== null ? $this->shell->user($user) : null,
            ],
            'can' => fn (): array => $this->shell->abilities($user),
            'counters' => fn (): ?array => $user !== null ? $this->shell->counters($user) : null,
            'flash' => fn (): array => [
                'toast' => $this->flashed($request, 'toast', 'toast_type'),
                'status' => $this->flashed($request, 'status', 'status_type'),
            ],
            'lang' => [
                'locale' => Locales::current(),
                'fallback' => Locales::default(),
            ],
            'ui' => [
                'neutral_title' => $this->shell->neutralTitle($user),
                'neutral_label' => __('seo.noindex.neutral'),
                'bell_poll_seconds' => (int) config('xomiton.feed.bell_poll_seconds', 30),
                'outbox' => [
                    'account' => OutboxAccount::fingerprint($user),
                    'ttl_days' => (int) config('xomiton.alerts.draft_days', 7),
                ],
                'sos' => [
                    'hold_ms' => (int) config('xomiton.alerts.sos_hold_ms', 2000),
                    'countdown_seconds' => (int) config('xomiton.alerts.sos_countdown_seconds', 5),
                ],
            ],
        ];
    }

    /**
     * @return array{id: string, message: string, type: string}|null
     */
    private function flashed(Request $request, string $key, string $typeKey): ?array
    {
        if (! $request->hasSession()) {
            return null;
        }

        $message = $request->session()->get($key);

        if (! is_string($message) || $message === '') {
            return null;
        }

        $type = $request->session()->get($typeKey);

        return [
            'id' => Str::random(16),
            'message' => $message,
            'type' => in_array($type, self::TOAST_TYPES, true) ? $type : 'primary',
        ];
    }
}

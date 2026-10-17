<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerAuthorization();
        $this->registerRateLimiters();
    }

    /**
     * Lot « securite-http » (P2-08) : limiteurs nommés, utilisés avec le middleware throttle:<nom>
     * (DEVELOPMENT_STANDARDS.md §7 « Force brute », config/xomiton.php « rate_limits »). Les seuils ne sont jamais codés ici
     * et sont lus à chaque requête (un test ou un réglage peut les changer sans redémarrer).
     * Réponse 429 en français (lang/fr/auth.php), identique pour tout le monde : aucune énumération de compte.
     */
    private function registerRateLimiters(): void
    {
        $tooMany = static function (Request $request, array $headers): never {
            $seconds = (int) ($headers['Retry-After'] ?? 60);

            throw new ThrottleRequestsException(__('auth.throttle', ['seconds' => $seconds]), null, $headers);
        };

        // Connexion : par identifiant (e-mail ou pseudo, insensible à la casse) et IP, pour ne pas bloquer tout un réseau partagé
        // ni permettre à un tiers de verrouiller le compte d'une autre personne depuis sa propre adresse.
        RateLimiter::for('login', static fn (Request $request): Limit => Limit::perMinute(self::limit('login_per_minute'))
            ->by(self::loginIdentifier($request).'|'.$request->ip())
            ->response($tooMany));

        // Codes OTP : par adresse e-mail (OtpService applique en plus ses propres règles d'envoi).
        RateLimiter::for('otp', static fn (Request $request): Limit => Limit::perHour(self::limit('otp_per_hour'))
            ->by('otp|'.(self::stringInput($request, 'email') ?? $request->ip()))
            ->response(static function (Request $request, array $headers): never {
                $minutes = max(1, (int) ceil(((int) ($headers['Retry-After'] ?? 3600)) / 60));

                throw new ThrottleRequestsException(__('auth.otp.rate_limited', ['minutes' => $minutes]), null, $headers);
            }));

        // Signalement anonyme : par IP.
        RateLimiter::for('report', static fn (Request $request): Limit => Limit::perHour(self::limit('report_per_hour_per_ip'))
            ->by('report|'.$request->ip())
            ->response($tooMany));

        // Suivi anonyme d'un signalement : par IP, la réponse « code inconnu ou expiré » reste neutre.
        RateLimiter::for('tracking', static fn (Request $request): Limit => Limit::perHour(self::limit('tracking_per_hour_per_ip'))
            ->by('tracking|'.$request->ip())
            ->response($tooMany));

        // Newsletter : une inscription par fenêtre glissante, par session ; un client qui ne renvoie pas le cookie de session
        // (script, curl) est compté par IP, sinon chaque requête ouvrirait une session neuve et contournerait la limite.
        RateLimiter::for('newsletter', static fn (Request $request): Limit => Limit::perSecond(1, self::limit('newsletter_seconds_between'))
            ->by('newsletter|'.self::sessionOrIp($request))
            ->response($tooMany));

        // API : par utilisateur·rice authentifié·e (session Sanctum ou jeton), sinon par IP.
        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(self::limit('api_per_minute'))
            ->by('api|'.($request->user('sanctum')?->getAuthIdentifier() ?? $request->ip()))
            ->response($tooMany));
    }

    /**
     * Seuil lu dans config/xomiton.php « rate_limits » au moment de la requête.
     */
    private static function limit(string $key): int
    {
        return max(1, (int) config('xomiton.rate_limits.'.$key));
    }

    /**
     * Valeur texte d'un champ du formulaire, normalisée (minuscules, sans espaces autour) ; null si absente ou non textuelle.
     */
    private static function stringInput(Request $request, string $field): ?string
    {
        $value = $request->input($field);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::lower(trim($value));
    }

    /**
     * Identifiant saisi sur le formulaire de connexion (e-mail ou pseudo), normalisé ; sinon l'IP seule.
     */
    private static function loginIdentifier(Request $request): string
    {
        foreach (['identifier', 'email', 'login', 'pseudo'] as $field) {
            if (($value = self::stringInput($request, $field)) !== null) {
                return $value;
            }
        }

        return 'anonymous';
    }

    /**
     * Identifiant de session quand le navigateur a renvoyé le cookie de session, sinon l'adresse IP.
     */
    private static function sessionOrIp(Request $request): string
    {
        $cookiePresented = $request->cookies->get((string) config('session.cookie')) !== null;

        if ($request->hasSession() && $cookiePresented) {
            return 'session:'.$request->session()->getId();
        }

        return 'ip:'.$request->ip();
    }

    /**
     * Lot « policies-audit » (P2-07, P2-09). Les policies App\Policies\<Modèle>Policy sont découvertes
     * automatiquement pour App\Models\<Modèle> ; les écouteurs d'audit App\Listeners\Auth\* sont découverts
     * dans app/Listeners (Login, Logout, Failed, PasswordReset, Lockout) : ne pas les enregistrer une seconde fois ici.
     */
    private function registerAuthorization(): void
    {
        // Q12 c : capacité « e-mail confirmé » (publier, commenter, envoyer un message, rejoindre un groupe) ;
        // miroir du middleware App\Http\Middleware\EnsureEmailIsVerified (alias `verified`).
        Gate::define('verified', static fn (User $user): bool => $user->hasVerifiedEmail());

        // Seul endroit où un nom de rôle est codé en dur (ROLE_ET_PERMISSIONS.md §3) : le superadmin détient tout,
        // sauf la modification du journal d'audit, immuable pour tout le monde (P2-09), et les actions sur son
        // propre compte, laissées aux garde-fous de UserPolicy (P12-01 : on ne se suspend ni ne se supprime soi-même).
        Gate::before(function (User $user, string $ability, array $arguments = []): ?bool {
            if (! $user->hasRole('superadmin')) {
                return null;
            }

            $subject = $arguments[0] ?? null;

            if ($subject instanceof User && $subject->is($user)) {
                return null;
            }

            $targetsAuditLog = $subject === AuditLog::class || $subject instanceof AuditLog;

            if ($targetsAuditLog && in_array($ability, ['create', 'update', 'delete', 'restore', 'forceDelete'], true)) {
                return false;
            }

            return true;
        });
    }
}

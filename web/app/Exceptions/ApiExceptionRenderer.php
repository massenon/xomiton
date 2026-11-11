<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Domain\Auth\Exceptions\AccountSuspended;
use App\Domain\Auth\Exceptions\AgeConfirmationRequired;
use App\Domain\Auth\Exceptions\AuthException;
use App\Domain\Auth\Exceptions\AuthFailed;
use App\Domain\Auth\Exceptions\ConsentRequired;
use App\Domain\Auth\Exceptions\EmailNotVerified;
use App\Domain\Auth\Exceptions\InvitationInvalid;
use App\Domain\Auth\Exceptions\MaintenanceActive;
use App\Domain\Auth\Exceptions\OtpInvalid;
use App\Domain\Auth\Exceptions\OtpRateLimited;
use App\Domain\Auth\Exceptions\OtpTooSoon;
use App\Domain\Auth\Exceptions\RegistrationsClosed;
use App\Domain\Shared\Exceptions\DomainException;
use App\Support\RouteAudience;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Réponses d'erreur JSON de /api/v1 (SOFTWARE_ARCHITECTURE.md §4, P5-09) : `{ message, code, errors?, retry_after? }`
 * pour tous les statuts (400, 401, 403, 404, 405, 409, 410, 413, 415, 419, 422, 429, 500, 503). Les exceptions métier
 * (App\Domain\Shared\Exceptions\DomainException) portent leur statut, leur `code` stable et leurs `errors` ; celles du
 * tunnel d'authentification (App\Domain\Auth\Exceptions) aussi : `invalid_credentials`, `account_suspended`, `maintenance`,
 * `email_unverified`, `otp_invalid`, `otp_too_soon`, `otp_rate_limited`, `registrations_closed`, `consent_required`, `age_confirmation_required`, `invitation_invalid`.
 * Le 422 de validation garde `{ message, errors }` de Laravel et y ajoute `code: validation_failed`. Hors débogage, toute
 * autre exception répond 500 `server_error` sans détail. Textes : lang/fr (auth.api tutoie, errors.api sans registre).
 */
final class ApiExceptionRenderer
{
    private function __construct() {}

    /** Requête de l'API ou client qui attend du JSON. */
    public static function wants(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    /**
     * Réponse JSON pour l'exception, ou null pour laisser Laravel faire (422 de validation, 500…).
     */
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! self::wants($request)) {
            return null;
        }

        return match (true) {
            $e instanceof HttpResponseException => null,
            $e instanceof DomainException => self::domain($e),
            $e instanceof AuthException => self::auth($e, $request),
            $e instanceof AuthenticationException => self::error(__('auth.api.unauthenticated'), 401, 'unauthenticated'),
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => self::error(__('auth.api.forbidden'), 403, 'forbidden'),
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => self::error(__('auth.api.not_found'), 404, 'not_found'),
            $e instanceof MethodNotAllowedHttpException => self::error(__('auth.api.method_not_allowed'), 405, 'method_not_allowed', headers: $e->getHeaders()),
            $e instanceof ThrottleRequestsException => self::throttled($e),
            $e instanceof ValidationException => self::validation($e),
            $e instanceof HttpExceptionInterface => self::http($e, $request),
            default => config('app.debug') ? null : self::error((string) __('errors.api.server'), 500, 'server_error'),
        };
    }

    /**
     * Exception métier : statut, message, code, erreurs par champ et en-têtes portés par l'exception.
     */
    public static function domain(DomainException $e): JsonResponse
    {
        return self::error($e->userMessage(), $e->status(), $e->errorCode(), $e->errors(), $e->extra(), $e->headers());
    }

    /**
     * 422 : forme Laravel `{ message, errors }` conservée (premier message + « (et :count autres erreurs) », clés de
     * ValidationException::summarize() traduites dans lang/fr.json), `code` ajouté. Une exception portant
     * déjà sa réponse (ValidationException::withMessages()->response) est laissée à Laravel.
     */
    private static function validation(ValidationException $e): ?JsonResponse
    {
        if ($e->response !== null) {
            return null;
        }

        return self::error($e->getMessage(), $e->status, 'validation_failed', $e->errors());
    }

    /**
     * Autres exceptions HTTP (abort(), 419 CSRF, 413 corps trop gros, 503 artisan down…) : message et code par statut,
     * en-têtes conservés (Retry-After). Le message brut de l'exception n'est jamais renvoyé (il peut être en anglais).
     */
    private static function http(HttpExceptionInterface $e, Request $request): JsonResponse
    {
        $status = $e->getStatusCode();
        $headers = $e->getHeaders();

        if ($status === 429) {
            $retryAfter = max(1, (int) ($headers['Retry-After'] ?? 60));

            return self::error((string) __('errors.api.throttled', ['seconds' => $retryAfter]), 429, 'too_many_requests', [], ['retry_after' => $retryAfter], $headers);
        }

        [$key, $code] = match ($status) {
            400 => ['errors.api.bad_request', 'bad_request'],
            401 => ['auth.api.unauthenticated', 'unauthenticated'],
            403 => ['auth.api.forbidden', 'forbidden'],
            404 => ['auth.api.not_found', 'not_found'],
            405 => ['auth.api.method_not_allowed', 'method_not_allowed'],
            409 => ['errors.api.conflict', 'conflict'],
            410 => ['errors.api.gone', 'gone'],
            413 => ['errors.api.payload_too_large', 'payload_too_large'],
            415 => ['errors.api.unsupported_media_type', 'unsupported_media_type'],
            // Deux clés seulement (VOICE_AND_TONE.md §3 règle 2) : groupe public (assistant de l'accueil, sans compte) au
            // vous, espace connecté (Vue en mode SPA, routes auth:sanctum) au tu.
            419 => [RouteAudience::requiresAuthentication($request) ? 'errors.419.member.text' : 'errors.419.public.text', 'session_expired'],
            422 => ['errors.api.validation', 'validation_failed'],
            503 => ['errors.api.maintenance', 'maintenance'],
            default => $status >= 500 ? ['errors.api.server', 'server_error'] : ['errors.api.http', 'http_error'],
        };

        return self::error((string) __($key), $status, $code, headers: $headers);
    }

    /**
     * Exception métier du tunnel d'authentification : statut et message de l'exception, code stable pour le client.
     */
    public static function auth(AuthException $e, Request $request): JsonResponse
    {
        $extra = [];
        $errors = [];

        if ($e instanceof AuthFailed) {
            $errors = [self::loginField($request) => [$e->userMessage()]];
        }

        if ($e instanceof AgeConfirmationRequired) {
            $errors = ['age_confirmed' => [$e->userMessage()]];
        }

        if ($e instanceof InvitationInvalid) {
            $errors = ['invitation' => [$e->userMessage()]];
        }

        if ($e instanceof OtpInvalid) {
            $errors = [self::otpField($request) => [$e->userMessage()]];
        }

        if ($e instanceof OtpTooSoon || $e instanceof OtpRateLimited) {
            $extra['retry_after'] = max(1, (int) ceil(now()->diffInSeconds($e->retryAt, false)));
        }

        return self::error($e->userMessage(), $e->status(), self::code($e), $errors, $extra);
    }

    public static function code(AuthException $e): string
    {
        return match (true) {
            $e instanceof AuthFailed => 'invalid_credentials',
            $e instanceof AccountSuspended => 'account_suspended',
            $e instanceof MaintenanceActive => 'maintenance',
            $e instanceof EmailNotVerified => 'email_unverified',
            $e instanceof OtpTooSoon => 'otp_too_soon',
            $e instanceof OtpRateLimited => 'otp_rate_limited',
            $e instanceof OtpInvalid => 'otp_invalid',
            $e instanceof RegistrationsClosed => 'registrations_closed',
            $e instanceof ConsentRequired => 'consent_required',
            $e instanceof AgeConfirmationRequired => 'age_confirmation_required',
            $e instanceof InvitationInvalid => 'invitation_invalid',
            default => 'auth_error',
        };
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, mixed>  $extra
     * @param  array<string, string>  $headers
     */
    public static function error(string $message, int $status, string $code, array $errors = [], array $extra = [], array $headers = []): JsonResponse
    {
        $payload = ['message' => $message, 'code' => $code];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload + $extra, $status, $headers);
    }

    private static function throttled(ThrottleRequestsException $e): JsonResponse
    {
        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];
        $retryAfter = (int) ($headers['Retry-After'] ?? 60);
        // Les limiteurs nommés (AppServiceProvider) portent déjà un message français ; le middleware throttle de Laravel
        // sans réponse personnalisée envoie « Too Many Attempts. » : on le traduit.
        $message = trim($e->getMessage());

        if ($message === '' || $message === 'Too Many Attempts.') {
            $message = (string) __('auth.throttle', ['seconds' => $retryAfter]);
        }

        return self::error($message, 429, 'too_many_requests', [], ['retry_after' => $retryAfter], $headers);
    }

    /** Champ qui porte l'identifiant sur la requête (`login` pour l'API, `identifier` pour le web). */
    private static function loginField(Request $request): string
    {
        foreach (['login', 'identifier', 'email', 'pseudo'] as $field) {
            if ($request->has($field)) {
                return $field;
            }
        }

        return 'login';
    }

    /** Champ qui porte la preuve OTP : `reset_token` sur la réinitialisation, `code` ailleurs. */
    private static function otpField(Request $request): string
    {
        return $request->has('reset_token') && ! $request->has('code') ? 'reset_token' : 'code';
    }
}

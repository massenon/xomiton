<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Domain\Auth\Exceptions\AccountSuspended;
use App\Domain\Auth\Exceptions\AuthException;
use App\Domain\Auth\Exceptions\AuthFailed;
use App\Domain\Auth\Exceptions\ConsentRequired;
use App\Domain\Auth\Exceptions\EmailNotVerified;
use App\Domain\Auth\Exceptions\MaintenanceActive;
use App\Domain\Auth\Exceptions\OtpInvalid;
use App\Domain\Auth\Exceptions\OtpRateLimited;
use App\Domain\Auth\Exceptions\OtpTooSoon;
use App\Domain\Auth\Exceptions\RegistrationsClosed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
 * Réponses d'erreur JSON de /api/v1 (SOFTWARE_ARCHITECTURE.md §4) : `{ message, code, errors?, retry_after? }`
 * avec les codes HTTP standard (401, 403, 404, 405, 422, 429, 503). Les exceptions métier du tunnel
 * d'authentification (App\Domain\Auth\Exceptions) portent leur propre statut et un `code` stable lu par Flutter :
 * `invalid_credentials`, `account_suspended`, `maintenance`, `email_unverified`, `otp_invalid`, `otp_too_soon`,
 * `otp_rate_limited`, `registrations_closed`, `consent_required`. Les textes viennent de lang/fr (tutoiement, Q36).
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
            $e instanceof AuthException => self::auth($e, $request),
            $e instanceof AuthenticationException => self::error(__('auth.api.unauthenticated'), 401, 'unauthenticated'),
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => self::error(__('auth.api.forbidden'), 403, 'forbidden'),
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => self::error(__('auth.api.not_found'), 404, 'not_found'),
            $e instanceof MethodNotAllowedHttpException => self::error(__('auth.api.method_not_allowed'), 405, 'method_not_allowed', headers: $e->getHeaders()),
            $e instanceof ThrottleRequestsException => self::throttled($e),
            $e instanceof ValidationException => null,
            default => null,
        };
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

<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Centralised API exception renderer.
 *
 * Every exception is normalised into a machine-readable JSON envelope:
 *
 *   { "success": false,
 *     "error":   { "code": "…", "message": "…", "errors": {} },
 *     "meta":    { "request_id": "…", "locale": "…", "direction": "…" } }
 *
 * Mapping new exception types is a single method + one line in register().
 */
final class ApiExceptionRenderer
{
    /**
     * Register all exception renderers on the given Exceptions configurator.
     */
    public function register(Exceptions $exceptions): void
    {
        $exceptions->render(fn (ApiException $e, Request $r) => $this->renderApiException($e, $r));
        $exceptions->render(fn (ValidationException $e, Request $r) => $this->renderValidation($e, $r));
        $exceptions->render(fn (AuthenticationException $e, Request $r) => $this->renderUnauthenticated($e, $r));
        $exceptions->render(fn (AuthorizationException $e, Request $r) => $this->renderForbidden($e, $r));
        $exceptions->render(fn (ModelNotFoundException|NotFoundHttpException $e, Request $r) => $this->renderNotFound($e, $r));
        $exceptions->render(fn (ThrottleRequestsException $e, Request $r) => $this->renderThrottled($e, $r));
        $exceptions->render(fn (HttpException $e, Request $r) => $this->renderHttpException($e, $r));
        $exceptions->render(fn (Throwable $e, Request $r) => $this->renderServerError($e, $r));
    }

    // ------------------------------------------------------------------
    //  Typed handlers — one per exception category
    // ------------------------------------------------------------------

    private function renderApiException(ApiException $e, Request $request): JsonResponse
    {
        return $this->problem($request, $e->status, $e->errorCode, $e->getMessage(), $e->context);
    }

    private function renderValidation(ValidationException $e, Request $request): JsonResponse
    {
        return $this->problem($request, 422, 'validation_failed', __('api.validation_failed'), $e->errors());
    }

    private function renderUnauthenticated(AuthenticationException $e, Request $request): JsonResponse
    {
        return $this->problem($request, 401, 'unauthenticated', __('api.unauthenticated'));
    }

    private function renderForbidden(AuthorizationException $e, Request $request): JsonResponse
    {
        return $this->problem($request, 403, 'forbidden', __('api.forbidden'));
    }

    private function renderNotFound(ModelNotFoundException|NotFoundHttpException $e, Request $request): JsonResponse
    {
        return $this->problem($request, 404, 'not_found', __('api.not_found'));
    }

    private function renderThrottled(ThrottleRequestsException $e, Request $request): JsonResponse
    {
        return $this->problem(
            $request,
            429,
            'too_many_requests',
            __('api.too_many_requests'),
            headers: ['Retry-After' => $e->getHeaders()['Retry-After'] ?? null],
        );
    }

    private function renderHttpException(HttpException $e, Request $request): JsonResponse
    {
        return $this->problem(
            $request,
            $e->getStatusCode(),
            'http_error',
            $e->getMessage() ?: __('api.http_error', ['status' => $e->getStatusCode()]),
        );
    }

    private function renderServerError(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->expectsJson() && ! $request->is('api/*')) {
            return null; // fall through to Laravel's default handler
        }

        report($e);

        return $this->problem(
            $request,
            500,
            'server_error',
            config('app.debug') ? $e->getMessage() : __('api.server_error'),
        );
    }

    // ------------------------------------------------------------------
    //  Envelope builder
    // ------------------------------------------------------------------

    /**
     * Build the standard error envelope. Every error response passes through
     * this single method, guaranteeing a consistent shape across the API.
     */
    private function problem(
        Request $request,
        int $status,
        string $code,
        string $message,
        array $errors = [],
        array $headers = [],
    ): JsonResponse {
        $payload = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'errors' => (object) $errors,
            ],
            'meta' => $this->meta($request),
        ];

        return response()->json($payload, $status, array_filter($headers));
    }

    /**
     * Request metadata attached to every error response for traceability
     * and client-side locale/direction handling.
     */
    private function meta(Request $request): array
    {
        return [
            'request_id' => $request->headers->get('X-Request-Id', (string) Str::uuid()),
            'locale' => app()->getLocale(),
            'direction' => in_array(app()->getLocale(), is_array($rtl = config('localization.rtl')) ? $rtl : [], true) ? 'rtl' : 'ltr',
        ];
    }
}

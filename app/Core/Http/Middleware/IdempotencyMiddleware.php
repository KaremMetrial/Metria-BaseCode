<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Safe retries for mutating endpoints (payments, transfers, orders).
 *
 * Client sends `Idempotency-Key: <uuid>`; if the same key + endpoint + user
 * has already completed, the stored response is replayed. If it is still
 * in flight, 409 is returned. Keys expire after governance.idempotency.ttl_hours.
 */
class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = config('governance.idempotency.header', 'Idempotency-Key');
        $key = $request->header($header);

        if (! $key || ! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $scope = hash('sha256', implode('|', [
            $key,
            $request->method(),
            $request->path(),
            (string) $request->user()?->getAuthIdentifier(),
        ]));

        $existing = IdempotencyKey::query()
            ->where('scope_hash', $scope)
            ->where('created_at', '>=', now()->subHours((int) config('governance.idempotency.ttl_hours', 24)))
            ->first();

        if ($existing) {
            if ($existing->response_body === null) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'idempotency_conflict',
                        'message' => __('api.idempotency_in_flight'),
                        'errors' => (object) [],
                    ],
                ], 409);
            }

            return response()->json(
                json_decode($existing->response_body, true),
                $existing->response_status,
            )->header('Idempotency-Replayed', 'true');
        }

        try {
            $record = IdempotencyKey::query()->create([
                'key' => $key,
                'scope_hash' => $scope,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'idempotency_conflict',
                    'message' => __('api.idempotency_in_flight'),
                    'errors' => (object) [],
                ],
            ], 409);
        }

        /** @var Response $response */
        $response = $next($request);

        // Only cache deterministic outcomes.
        if ($response->getStatusCode() < 500) {
            $record->update([
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
            ]);
        } else {
            $record->delete(); // allow retry after server errors
        }

        return $response;
    }
}

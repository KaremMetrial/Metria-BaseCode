<?php

declare(strict_types=1);

namespace App\Core\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Standard response envelope for every API endpoint:
 *
 *  { "success": true, "message": "...", "data": ..., "meta": {...} }
 */
trait ApiResponses
{
    protected function respond(mixed $data = null, ?string $message = null, int $status = 200, array $meta = []): JsonResponse
    {
        $payload = [
            'success' => $status >= 200 && $status < 300,
            'message' => $message,
            'data' => $data,
            'meta' => array_merge($this->baseMeta(), $meta),
        ];

        if ($data instanceof LengthAwarePaginator) {
            $payload['data'] = $data->items();
            $payload['meta']['pagination'] = $this->paginationMeta($data);
        }

        if ($data instanceof ResourceCollection && $data->resource instanceof LengthAwarePaginator) {
            $payload['data'] = $data->collection;
            $payload['meta']['pagination'] = $this->paginationMeta($data->resource);
        }

        if ($data instanceof JsonResource && ! $data instanceof ResourceCollection) {
            $payload['data'] = $data->resolve(request());
            if (! empty($data->additional)) {
                $payload = array_merge($payload, $data->additional);
            }
        }

        return response()->json($payload, $status);
    }

    protected function respondCreated(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->respond($data, $message ?? __('api.created'), 201);
    }

    protected function respondNoContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function respondError(string $message, int $status = 400, string $code = 'error', array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'errors' => (object) $errors,
            ],
            'meta' => $this->baseMeta(),
        ], $status);
    }

    private function baseMeta(): array
    {
        return [
            'request_id' => request()->headers->get('X-Request-Id', (string) Str::uuid()),
            'locale' => app()->getLocale(),
            'direction' => in_array(app()->getLocale(), config('localization.rtl', []), true) ? 'rtl' : 'ltr',
        ];
    }

    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}

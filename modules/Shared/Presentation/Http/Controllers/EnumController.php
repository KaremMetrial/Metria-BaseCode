<?php

declare(strict_types=1);

namespace Modules\Shared\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Shared\Application\Support\EnumRegistry;

class EnumController extends ApiController
{
    /** Retrieve all registered system enums and their formatted cases for frontend dropdowns/filters. */
    public function index(): JsonResponse
    {
        return $this->respond(EnumRegistry::all());
    }

    /** Retrieve a specific enum's formatted cases by name/key (e.g., payment_status). */
    public function show(string $key): JsonResponse
    {
        $enum = EnumRegistry::get($key);

        if ($enum === null) {
            return $this->respondError(__('api.enum_not_found', ['key' => $key]), 404, 'enum_not_found');
        }

        return $this->respond(['key' => $key, 'cases' => $enum]);
    }
}

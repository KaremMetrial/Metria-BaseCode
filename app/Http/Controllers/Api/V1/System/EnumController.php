<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\System;

use App\Core\Http\Controllers\ApiController;
use App\Core\Support\EnumRegistry;
use Illuminate\Http\JsonResponse;

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
            return $this->respondError("Enum not found for key: [{$key}].", 404, 'enum_not_found');
        }

        return $this->respond(['key' => $key, 'cases' => $enum]);
    }
}

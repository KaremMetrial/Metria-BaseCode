<?php

declare(strict_types=1);

namespace App\Domain\Governance\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Governance\Models\FeatureFlag;
use App\Domain\Governance\Services\FeatureFlagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FeatureFlagController extends ApiController
{
    public function __construct(private readonly FeatureFlagService $flags) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', FeatureFlag::class);

        return $this->respond(FeatureFlag::query()->orderBy('name')->get());
    }

    /** Evaluate a flag for the calling user (rollout %, allowlist, global). */
    public function show(Request $request, string $name): JsonResponse
    {
        return $this->respond([
            'name' => $name,
            'enabled' => $this->flags->enabled($name, $request->user()),
        ]);
    }

    public function toggle(Request $request, string $name): JsonResponse
    {
        Gate::authorize('toggle', FeatureFlag::class);
        $request->validate(['enabled' => ['required', 'boolean']]);

        $flag = $this->flags->toggle($name, $request->boolean('enabled'));

        return $this->respond($flag, __('api.updated'));
    }
}

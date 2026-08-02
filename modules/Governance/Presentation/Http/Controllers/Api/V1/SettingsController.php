<?php

declare(strict_types=1);

namespace Modules\Governance\Presentation\Http\Controllers\Api\V1;

use Modules\Shared\Presentation\Http\Controllers\ApiController;
use Modules\Governance\Presentation\Http\Requests\UpdateSettingRequest;
use Modules\Governance\Domain\Models\Setting;
use Modules\Governance\Infrastructure\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SettingsController extends ApiController
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Setting::class);

        return $this->respond($this->settings->all());
    }

    public function show(string $key): JsonResponse
    {
        Gate::authorize('viewAny', Setting::class);

        return $this->respond(['key' => $key, 'value' => $this->settings->get($key)]);
    }

    public function update(UpdateSettingRequest $request, string $key): JsonResponse
    {
        Gate::authorize('update', Setting::class);
        $descVal = $request->validated('description');
        $description = is_string($descVal) ? $descVal : null;
        $this->settings->set($key, $request->validated('value'), $description);

        return $this->respond(['key' => $key, 'value' => $this->settings->get($key)], __('api.updated'));
    }

    public function destroy(string $key): JsonResponse
    {
        Gate::authorize('delete', Setting::class);
        $this->settings->forget($key);

        return $this->respondNoContent();
    }
}

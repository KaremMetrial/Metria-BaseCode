<?php

declare(strict_types=1);

namespace App\Domain\Governance\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Governance\Http\Requests\UpdateSettingRequest;
use App\Domain\Governance\Models\Setting;
use App\Domain\Governance\Services\SettingsService;
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
        $this->settings->set($key, $request->validated('value'), $request->validated('description'));

        return $this->respond(['key' => $key, 'value' => $this->settings->get($key)], __('api.updated'));
    }

    public function destroy(string $key): JsonResponse
    {
        Gate::authorize('delete', Setting::class);
        $this->settings->forget($key);

        return $this->respondNoContent();
    }
}

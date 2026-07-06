<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Governance;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Governance\Services\SettingsService;
use App\Http\Requests\UpdateSettingRequest;
use Illuminate\Http\JsonResponse;

class SettingsController extends ApiController
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(): JsonResponse
    {
        return $this->respond($this->settings->all());
    }

    public function show(string $key): JsonResponse
    {
        return $this->respond(['key' => $key, 'value' => $this->settings->get($key)]);
    }

    public function update(UpdateSettingRequest $request, string $key): JsonResponse
    {
        $this->settings->set($key, $request->validated('value'), $request->validated('description'));

        return $this->respond(['key' => $key, 'value' => $this->settings->get($key)], __('api.updated'));
    }

    public function destroy(string $key): JsonResponse
    {
        $this->settings->forget($key);

        return $this->respondNoContent();
    }
}

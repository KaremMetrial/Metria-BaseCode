<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Webhook\Models\WebhookEndpoint;
use App\Http\Requests\Webhook\StoreWebhookEndpointRequest;
use App\Http\Resources\Webhook\WebhookEndpointResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookEndpointController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->respond(WebhookEndpointResource::collection(WebhookEndpoint::query()->latest()->get()));
    }

    public function store(StoreWebhookEndpointRequest $request): JsonResponse
    {
        $endpoint = WebhookEndpoint::create([
            ...$request->validated(),
            'secret' => WebhookEndpoint::generateSecret(),
        ]);

        // The signing secret is revealed exactly once, at creation time.
        $resource = (new WebhookEndpointResource($endpoint))->additional(['reveal_secret' => true]);

        return $this->respondCreated($resource, __('webhooks.secret_shown_once'));
    }

    public function update(StoreWebhookEndpointRequest $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        $webhookEndpoint->update($request->validated());

        return $this->respond(new WebhookEndpointResource($webhookEndpoint));
    }

    public function destroy(WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        $webhookEndpoint->delete();

        return $this->respondNoContent();
    }

    /** Rotate the signing secret (old signatures stop validating immediately). */
    public function rotateSecret(Request $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        $webhookEndpoint->update(['secret' => WebhookEndpoint::generateSecret()]);

        $resource = (new WebhookEndpointResource($webhookEndpoint->refresh()))->additional(['reveal_secret' => true]);

        return $this->respond($resource, __('webhooks.secret_shown_once'));
    }
}

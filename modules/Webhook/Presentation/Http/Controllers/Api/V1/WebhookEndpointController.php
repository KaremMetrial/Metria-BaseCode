<?php

declare(strict_types=1);

namespace Modules\Webhook\Presentation\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Shared\Presentation\Http\Controllers\BaseCrudController;
use Modules\Webhook\Domain\Models\WebhookEndpoint;
use Modules\Webhook\Presentation\Http\Requests\StoreWebhookEndpointRequest;
use Modules\Webhook\Presentation\Http\Resources\WebhookEndpointResource;

/**
 * index/update/destroy come from BaseCrudController — store and
 * rotateSecret stay custom here because they generate/reveal the signing
 * secret, which is business logic BaseCrudController deliberately doesn't
 * know about.
 */
class WebhookEndpointController extends BaseCrudController
{
    protected string $modelClass = WebhookEndpoint::class;

    protected string $resourceClass = WebhookEndpointResource::class;

    protected string $storeRequestClass = StoreWebhookEndpointRequest::class;

    public function store(): JsonResponse
    {
        Gate::authorize('create', WebhookEndpoint::class);

        $request = app($this->storeRequestClass);

        $endpoint = WebhookEndpoint::create([
            ...$request->validated(),
            'secret' => WebhookEndpoint::generateSecret(),
        ]);

        // The signing secret is revealed exactly once, at creation time.
        $resource = (new WebhookEndpointResource($endpoint))->additional(['reveal_secret' => true]);

        return $this->respondCreated($resource, __('webhooks.secret_shown_once'));
    }

    /** Rotate the signing secret (old signatures stop validating immediately). */
    public function rotateSecret(Request $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        Gate::authorize('update', $webhookEndpoint);

        $webhookEndpoint->update(['secret' => WebhookEndpoint::generateSecret()]);

        $resource = (new WebhookEndpointResource($webhookEndpoint->refresh()))->additional(['reveal_secret' => true]);

        return $this->respond($resource, __('webhooks.secret_shown_once'));
    }
}

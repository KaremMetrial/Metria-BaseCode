<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    public function test_docs_are_hidden_when_disabled_and_protected_when_enabled(): void
    {
        config(['api_docs.enabled' => false]);
        $this->getJson('/docs/api.json')->assertNotFound();

        config(['api_docs.enabled' => true, 'api_docs.allowed_environments' => ['testing'], 'api_docs.public_access' => false]);
        $this->getJson('/docs/api.json')->assertForbidden();
    }

    public function test_public_documents_cover_every_versioned_client_route_without_internal_leaks(): void
    {
        config(['api_docs.enabled' => true, 'api_docs.allowed_environments' => ['testing'], 'api_docs.public_access' => true]);
        $documents = collect(['/docs/api.json', '/docs/admin.json', '/docs/webhooks.json'])
            ->map(fn (string $path) => $this->getJson($path)->assertOk()->json())
            ->all();

        $operations = collect($documents)->flatMap(function (array $document) {
            return collect($document['paths'] ?? [])->flatMap(fn (array $path) => collect($path)
                ->reject(fn (mixed $operation, string $method) => $method === 'parameters')
                ->mapWithKeys(fn (array $operation, string $method) => [strtolower($method).' '.$operation['operationId'] => $operation]));
        });

        $expected = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/'));

        $this->assertCount($expected->count(), $operations);
        $this->assertNotEmpty($operations);
        $this->assertTrue($operations->every(fn (array $operation) => ! empty($operation['operationId']) && ! empty($operation['tags'])));
        $this->assertSame([], collect($documents)->flatMap(fn (array $document) => array_keys($document['paths'] ?? []))
            ->filter(fn (string $path) => str_contains($path, 'internal/realtime'))->values()->all());

        // Scramble's configured API server already includes the /api/v1 prefix,
        // so published path keys are relative to that server URL.
        $communicationMessage = $documents[0]['paths']['/communication/conversations/{conversation}/messages']['post'] ?? null;
        $communicationSync = $documents[0]['paths']['/communication/conversations/{conversation}/sync']['get'] ?? null;
        $this->assertIsArray($communicationMessage);
        $this->assertIsArray($communicationSync);
        $this->assertSame(['Communication'], $communicationMessage['tags']);
        $this->assertStringContainsString('Socket.IO', (string) ($communicationMessage['description'] ?? ''));
        $this->assertStringContainsString('authoritative cursor synchronization', (string) ($communicationSync['description'] ?? ''));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Core\Exceptions\IntegrationException;
use App\Domain\Integration\Push\FcmPushProvider;
use Illuminate\Support\Facades\Cache;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;
use Mockery\MockInterface;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_it_sends_push_notification_via_firebase_admin_sdk(): void
    {
        $this->mock(Messaging::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')
                ->once()
                ->withArgs(function (CloudMessage $message) {
                    return true;
                })
                ->andReturn(['name' => 'projects/test-project/messages/msg-123']);
        });

        $provider = new FcmPushProvider;
        $result = $provider->send('device-token-123', 'Test Title', 'Test Body', ['key' => 'val']);

        $this->assertSame('projects/test-project/messages/msg-123', $result);
    }

    public function test_it_throws_integration_exception_on_firebase_error(): void
    {
        $this->mock(Messaging::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')
                ->once()
                ->andThrow(new NotFound('Token not registered.'));
        });

        $this->expectException(IntegrationException::class);
        $this->expectExceptionMessage(__('integrations.push_send_failed', ['provider' => 'fcm']));

        $provider = new FcmPushProvider;
        $provider->send('invalid-token', 'Title', 'Body');
    }
}

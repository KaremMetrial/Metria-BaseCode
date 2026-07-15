<?php

declare(strict_types=1);

namespace App\Core\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Broadcaster as BroadcasterContract;
use Illuminate\Http\Request;

/**
 * Enterprise Dual/Hybrid Broadcaster.
 * Iterates through multiple configured broadcast connections (e.g., 'reverb' and 'redis')
 * to stream real-time events to both Pusher/Reverb WebSockets and Socket.IO servers simultaneously.
 */
class DualBroadcaster extends Broadcaster
{
    /**
     * @param  array<int, string>  $drivers
     */
    public function __construct(
        protected array $drivers = ['reverb', 'redis']
    ) {}

    protected function resolvePrimaryDriver(): BroadcasterContract
    {
        $primary = $this->drivers[0] ?? 'log';
        $connection = app(BroadcastManager::class)->connection($primary);

        if (! $connection instanceof BroadcasterContract) {
            throw new \RuntimeException(__('core.broadcaster_contract_required'));
        }


        return $connection;
    }

    /**
     * Authenticate the incoming request for a given channel by delegating to the primary driver.
     *
     * @param  Request  $request
     * @return mixed
     */
    public function auth($request)
    {
        return $this->resolvePrimaryDriver()->auth($request);
    }

    /**
     * Return the valid authentication response from the primary driver.
     *
     * @param  Request  $request
     * @param  mixed  $result
     * @return mixed
     */
    public function validAuthenticationResponse($request, $result)
    {
        return $this->resolvePrimaryDriver()->validAuthenticationResponse($request, $result);
    }

    /**
     * Broadcast the given event across all configured secondary connections.
     *
     * @param  array<int, mixed>  $channels
     * @param  string  $event
     * @param  array<string, mixed>  $payload
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        $manager = app(BroadcastManager::class);

        foreach ($this->drivers as $driverName) {
            $connection = $manager->connection($driverName);
            if ($connection instanceof BroadcasterContract) {
                $connection->broadcast($channels, $event, $payload);
            }
        }
    }
}

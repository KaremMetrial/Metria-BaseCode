<?php

declare(strict_types=1);

namespace App\Domain\System\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends ApiController
{
    /** Deep health check: verifies DB + cache round-trips (for LB probes use /up). */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'cache' => $this->check(function () {
                Cache::put('health:ping', 'pong', 5);

                return Cache::get('health:ping') === 'pong';
            }),
        ];

        $healthy = ! in_array(false, $checks, true);

        return $this->respond(
            ['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks, 'version' => config('core.api.version')],
            status: $healthy ? 200 : 503,
        );
    }

    private function check(callable $probe): bool
    {
        try {
            return (bool) $probe();
        } catch (Throwable) {
            return false;
        }
    }
}

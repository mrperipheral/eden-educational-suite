<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Lightweight health / readiness endpoint for load balancers and uptime
 * monitors. Intentionally unauthenticated and side-effect free.
 *
 * It deliberately does NOT expose environment names, versions, secrets or
 * configuration. It only reports whether the app can serve requests and
 * reach its primary datastore.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = $this->check(fn () => DB::connection()->getPdo() !== null);

        $healthy = $database;

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'time' => now()->toIso8601String(),
            'checks' => [
                'database' => $database ? 'ok' : 'fail',
            ],
        ], $healthy ? 200 : 503);
    }

    private function check(callable $probe): bool
    {
        try {
            return (bool) $probe();
        } catch (\Throwable) {
            return false;
        }
    }
}

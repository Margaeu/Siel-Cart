<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Readiness: can this instance serve database-backed pages right now?
 *
 * `/up` (Laravel's built-in health route) is liveness -- it proves PHP boots and
 * touches nothing else, so it stays green through a database outage. This
 * adds the one dependency every storefront page needs, with a single
 * `select 1`. It deliberately does not call the chatbot: that is a separate
 * service with its own /health routes, and an assistant outage must not make
 * the shop itself look down.
 *
 * Registered in bootstrap/app.php outside the `web` middleware group, so a
 * monitor polling it every minute creates no session row (SESSION_DRIVER is
 * `database`) and needs no CSRF token.
 */
class HealthReadyController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            Log::error('Readiness check: database unreachable.', ['exception' => $e]);

            // No exception text in the body: it can carry the database host.
            return response()->json(['status' => 'unavailable', 'database' => 'unreachable'], 503);
        }

        return response()->json(['status' => 'ok', 'database' => 'ok']);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyNetboxWebhook
{
    /**
     * Handle an incoming request.
     *
     * Validates the X-Netbox-Signature header sent by Netbox webhooks.
     * Netbox signs the raw request body with HMAC-SHA512 using the shared
     * secret configured in Netbox and stored here as NETBOX_WEBHOOK_SECRET.
     *
     * Header format from Netbox:  X-Netbox-Signature: sha512=<hex digest>
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $secret = env('NETBOX_WEBHOOK_SECRET');

        if (!$secret) {
            return response()->json(['error' => 'Webhook secret not configured.'], 500);
        }

        $signature = $request->header('X-Netbox-Signature');

        if (!$signature) {
            return response()->json(['error' => 'Missing X-Netbox-Signature header.'], 403);
        }

        // Netbox sends:  sha512=<hex digest>
        if (!str_starts_with($signature, 'sha512=')) {
            return response()->json(['error' => 'Invalid signature format.'], 403);
        }

        $receivedDigest = substr($signature, strlen('sha512='));
        $expectedDigest = hash_hmac('sha512', $request->getContent(), $secret);

        if (!hash_equals($expectedDigest, $receivedDigest)) {
            return response()->json(['error' => 'Invalid webhook signature.'], 403);
        }

        return $next($request);
    }
}

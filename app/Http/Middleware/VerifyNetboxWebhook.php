<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyNetboxWebhook
{
    /**
     * Handle an incoming request.
     *
     * Validates the X-Hook-Signature header sent by Netbox webhooks.
     * Netbox signs the raw request body with HMAC-SHA512 using the shared
     * secret configured in Netbox and stored here as NETBOX_WEBHOOK_SECRET.
     *
     * Header format from Netbox:  X-Hook-Signature: sha512=<hex digest>
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $secret = env('NETBOX_WEBHOOK_SECRET');

        if (!$secret) {
            return response()->json(['error' => 'Webhook secret not configured.'], 500);
        }

        $signature = $request->header('X-Hook-Signature');

        if (!$signature) {
            return response()->json(['error' => 'Missing X-Hook-Signature header.'], 403);
        }

        // Netbox may send either "sha512=<hex>" or a raw hex digest.
        if (str_starts_with($signature, 'sha512=')) {
            $receivedDigest = substr($signature, strlen('sha512='));
        } else {
            $receivedDigest = $signature;
        }

        $expectedDigest = hash_hmac('sha512', $request->getContent(), $secret);

        // DEBUG: temporarily log signature details to diagnose mismatch
        \Illuminate\Support\Facades\Log::debug('VerifyNetboxWebhook', [
            'received'  => $receivedDigest,
            'expected'  => $expectedDigest,
            'match'     => hash_equals($expectedDigest, $receivedDigest),
            'body_len'  => strlen($request->getContent()),
        ]);

        if (!hash_equals($expectedDigest, $receivedDigest)) {
            return response()->json(['error' => 'Invalid webhook signature.'], 403);
        }

        return $next($request);
    }
}

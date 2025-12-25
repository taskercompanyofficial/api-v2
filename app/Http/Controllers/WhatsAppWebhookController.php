<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    protected WhatsAppWebhookService $webhookService;

    public function __construct(WhatsAppWebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Verify webhook (GET request from WhatsApp).
     * This is called by WhatsApp to verify your webhook URL.
     */
    public function verify(Request $request): JsonResponse|string
    {
        Log::info('WhatsApp webhook verification request', [
            'payload' => $request->all(),
        ]);
        $challenge = $this->webhookService->verifyWebhook($request->all());

        if ($challenge) {
            // Return the challenge as plain text (required by WhatsApp)
            return response($challenge, 200)
                ->header('Content-Type', 'text/plain');
        }

        return response()->json([
            'success' => false,
            'message' => 'Webhook verification failed',
        ], 403);
    }

    /**
     * Handle incoming webhooks (POST request from WhatsApp).
     * This receives messages, status updates, and other events.
     */
    public function handle(Request $request): JsonResponse
    {
        // Validate signature if enabled
        if (config('whatsapp.webhook.verify_signature')) {
            $signature = $request->header('X-Hub-Signature-256');
            $payload = $request->getContent();

            if (!$this->webhookService->validateSignature($payload, $signature)) {
                Log::warning('WhatsApp webhook signature validation failed');
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid signature',
                ], 403);
            }
        }

        // Log the incoming webhook for debugging
        Log::info('WhatsApp webhook received', [
            'payload' => $request->all(),
        ]);

        // Process the webhook
        $success = $this->webhookService->processWebhook($request->all());

        // WhatsApp expects a 200 OK response quickly
        // Processing should be done asynchronously if it takes time
        return response()->json([
            'success' => $success,
        ], 200);
    }
}

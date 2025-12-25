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
     * MUST return plain text challenge for WhatsApp to accept.
     */
    public function verify(Request $request)
    {
        $requestId = 'req_' . uniqid() . '_' . time();
        
        Log::info('WhatsApp webhook verification request received', [
            'request_id' => $requestId,
            'params' => $request->all(),
        ]);

        $challenge = $this->webhookService->verifyWebhook($request->all());

        if ($challenge) {
            Log::info('WhatsApp webhook verified successfully', [
                'request_id' => $requestId,
                'challenge' => $challenge,
            ]);
            
            // Return JSON response with success status
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Webhook verification successful. Challenge: ' . $challenge,
                'request_id' => $requestId,
                'challenge' => $challenge,
            ], 200);
        }

        // If verification fails
        Log::error('WhatsApp webhook verification failed', [
            'request_id' => $requestId,
            'params' => $request->all(),
        ]);

        return response()->json([
            'status' => 'ERROR',
            'message' => 'Webhook verification failed. Invalid verify token.',
            'request_id' => $requestId,
        ], 403);
    }

    /**
     * Handle incoming webhooks (POST request from WhatsApp).
     * This receives messages, status updates, and other events.
     * Returns JSON response with request tracking.
     */
    public function handle(Request $request): JsonResponse
    {
        $requestId = 'req_' . uniqid() . '_' . time();
        
        // Log the incoming webhook for debugging
        Log::info('WhatsApp webhook received', [
            'request_id' => $requestId,
            'payload' => $request->all(),
        ]);

        // Validate signature if enabled
        if (config('whatsapp.webhook.verify_signature')) {
            $signature = $request->header('X-Hub-Signature-256');
            $payload = $request->getContent();

            if (!$this->webhookService->validateSignature($payload, $signature)) {
                Log::warning('WhatsApp webhook signature validation failed', [
                    'request_id' => $requestId,
                ]);
                
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid signature. Webhook request rejected.',
                    'request_id' => $requestId,
                ], 403);
            }
        }

        // Process the webhook
        try {
            $success = $this->webhookService->processWebhook($request->all());

            if ($success) {
                Log::info('WhatsApp webhook processed successfully', [
                    'request_id' => $requestId,
                ]);

                // WhatsApp expects a 200 OK response quickly
                return response()->json([
                    'status' => 'SUCCESS',
                    'message' => 'Webhook received and processed successfully. Check logs for details.',
                    'request_id' => $requestId,
                ], 200);
            } else {
                Log::error('WhatsApp webhook processing failed', [
                    'request_id' => $requestId,
                ]);

                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Webhook received but processing failed. Check logs for details.',
                    'request_id' => $requestId,
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp webhook exception', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'ERROR',
                'message' => 'Webhook processing failed: ' . $e->getMessage(),
                'request_id' => $requestId,
            ], 500);
        }
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class WhatsAppWebhookService
{
    protected WhatsAppMessageService $messageService;

    public function __construct(WhatsAppMessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    /**
     * Verify webhook request from WhatsApp.
     *
     * @param array $params Query parameters
     * @return string|null Challenge token if valid, null otherwise
     */
    public function verifyWebhook(array $params): ?string
    {
        $mode = $params['hub_mode'] ?? null;
        $token = $params['hub_verify_token'] ?? null;
        $challenge = $params['hub_challenge'] ?? null;

        $verifyToken = config('whatsapp.webhook_verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('WhatsApp webhook verified successfully');
            return $challenge;
        }

        Log::warning('WhatsApp webhook verification failed', [
            'mode' => $mode,
            'token_match' => $token === $verifyToken,
        ]);

        return null;
    }

    /**
     * Process webhook payload from WhatsApp.
     *
     * @param array $payload
     * @return bool
     */
    public function processWebhook(array $payload): bool
    {
        try {
            $entries = $payload['entry'] ?? [];

            foreach ($entries as $entry) {
                $changes = $entry['changes'] ?? [];

                foreach ($changes as $change) {
                    $value = $change['value'] ?? [];
                    
                    // Process messages
                    if (isset($value['messages'])) {
                        $this->processMessages($value['messages'], $value);
                    }

                    // Process status updates
                    if (isset($value['statuses'])) {
                        $this->processStatuses($value['statuses']);
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to process WhatsApp webhook', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            return false;
        }
    }

    /**
     * Process incoming messages from webhook.
     *
     * @param array $messages
     * @param array $metadata
     * @return void
     */
    protected function processMessages(array $messages, array $metadata): void
    {
        foreach ($messages as $messageData) {
            // Add metadata to message data
            $messageData['profile'] = $metadata['contacts'][0] ?? [];
            
            $this->messageService->processIncomingMessage($messageData);
        }
    }

    /**
     * Process status updates from webhook.
     *
     * @param array $statuses
     * @return void
     */
    protected function processStatuses(array $statuses): void
    {
        foreach ($statuses as $status) {
            $messageId = $status['id'] ?? null;
            $statusValue = $status['status'] ?? null;

            if ($messageId && $statusValue) {
                $this->messageService->updateMessageStatus($messageId, $statusValue);
            }

            // Log errors if present
            if (isset($status['errors'])) {
                Log::error('WhatsApp message error', [
                    'message_id' => $messageId,
                    'errors' => $status['errors'],
                ]);
            }
        }
    }

    /**
     * Validate webhook signature (optional but recommended).
     *
     * @param string $payload
     * @param string $signature
     * @return bool
     */
    public function validateSignature(string $payload, string $signature): bool
    {
        if (!config('whatsapp.webhook.verify_signature')) {
            return true;
        }

        $appSecret = config('whatsapp.app_secret');
        
        if (!$appSecret) {
            Log::warning('WhatsApp app secret not configured');
            return true;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

        $isValid = hash_equals($expectedSignature, $signature);

        if (!$isValid) {
            Log::warning('WhatsApp webhook signature validation failed', [
                'expected' => $expectedSignature,
                'received' => $signature,
            ]);
        }

        return $isValid;
    }
}

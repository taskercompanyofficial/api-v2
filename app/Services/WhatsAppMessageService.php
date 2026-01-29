<?php

namespace App\Services;

use App\Events\WhatsAppMessageReceived;
use App\Events\WhatsAppMessageSent;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\AI\GeminiAgentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppMessageService
{
    protected WhatsAppService $whatsappService;
    protected GeminiAgentService $aiAgent;

    public function __construct(WhatsAppService $whatsappService, GeminiAgentService $aiAgent)
    {
        $this->whatsappService = $whatsappService;
        $this->aiAgent = $aiAgent;
    }

    /**
     * Get staff user IDs for broadcasting.
     * Prioritizes conversation-specific staff, falls back to all CRM staff.
     * 
     * @param WhatsAppConversation|null $conversation
     * @return array
     */
    protected function getStaffIdsForBroadcast(?WhatsAppConversation $conversation = null): array
    {
        // If conversation has assigned staff, use them
        if ($conversation) {
            $staffIds = $conversation->getNotifiableStaffIds();

            // Also include the primary assigned_to user if set
            if ($conversation->assigned_to && !in_array($conversation->assigned_to, $staffIds)) {
                $staffIds[] = $conversation->assigned_to;
            }

            // If conversation has assigned staff, use only them
            if (!empty($staffIds)) {
                return $staffIds;
            }
        }

        // Fallback: broadcast to all active CRM staff
        return User::where('is_active', true)
            ->whereIn('role', ['admin', 'manager', 'staff'])
            ->pluck('id')
            ->toArray();
    }

    /**
     * Send a text message and store it in the database.
     *
     * @param int $conversationId
     * @param string $message
     * @param int|null $sentBy User ID
     * @return WhatsAppMessage|null
     */
    public function sendTextMessage(int $conversationId, string $message, ?int $sentBy = null): ?WhatsAppMessage
    {
        $conversation = WhatsAppConversation::with('contact')->find($conversationId);

        if (!$conversation) {
            Log::error('Conversation not found', ['conversation_id' => $conversationId]);
            return null;
        }

        // Create message record
        $whatsappMessage = WhatsAppMessage::create([
            'whatsapp_conversation_id' => $conversationId,
            'direction' => 'outbound',
            'type' => 'text',
            'content' => $message,
            'status' => 'pending',
            'sent_by' => $sentBy,
        ]);

        // Send via WhatsApp API
        $response = $this->whatsappService->sendTextMessage(
            $conversation->contact->phone_number,
            $message
        );

        if ($response && isset($response['messages'][0]['id'])) {
            $whatsappMessage->update([
                'whatsapp_message_id' => $response['messages'][0]['id'],
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Update conversation
            $conversation->updateLastMessageTime();
            $conversation->contact->updateLastInteraction();

            // Broadcast event to conversation staff (or all CRM staff if none assigned)
            $staffIds = $this->getStaffIdsForBroadcast($conversation);
            broadcast(new WhatsAppMessageSent($whatsappMessage->fresh(), $staffIds));
        } else {
            $whatsappMessage->markAsFailed('Failed to send message via WhatsApp API');
        }

        return $whatsappMessage->fresh();
    }

    /**
     * Send an image message and store it in the database.
     *
     * @param int $conversationId
     * @param string $imageUrl
     * @param string|null $caption
     * @param int|null $sentBy User ID
     * @return WhatsAppMessage|null
     */
    public function sendImageMessage(int $conversationId, string $imageUrl, ?string $caption = null, ?int $sentBy = null): ?WhatsAppMessage
    {
        $conversation = WhatsAppConversation::with('contact')->find($conversationId);

        if (!$conversation) {
            Log::error('Conversation not found', ['conversation_id' => $conversationId]);
            return null;
        }

        // Create message record
        $whatsappMessage = WhatsAppMessage::create([
            'whatsapp_conversation_id' => $conversationId,
            'direction' => 'outbound',
            'type' => 'image',
            'content' => $caption,
            'media' => ['url' => $imageUrl, 'type' => 'image'],
            'status' => 'pending',
            'sent_by' => $sentBy,
        ]);

        // Send via WhatsApp API
        $response = $this->whatsappService->sendImageMessage(
            $conversation->contact->phone_number,
            $imageUrl,
            $caption
        );

        if ($response && isset($response['messages'][0]['id'])) {
            $whatsappMessage->update([
                'whatsapp_message_id' => $response['messages'][0]['id'],
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $conversation->updateLastMessageTime();
            $conversation->contact->updateLastInteraction();
        } else {
            $whatsappMessage->markAsFailed('Failed to send image via WhatsApp API');
        }

        return $whatsappMessage->fresh();
    }

    /**
     * Send a document message and store it in the database.
     *
     * @param int $conversationId
     * @param string $documentUrl
     * @param string|null $filename
     * @param string|null $caption
     * @param int|null $sentBy User ID
     * @return WhatsAppMessage|null
     */
    public function sendDocumentMessage(int $conversationId, string $documentUrl, ?string $filename = null, ?string $caption = null, ?int $sentBy = null): ?WhatsAppMessage
    {
        $conversation = WhatsAppConversation::with('contact')->find($conversationId);

        if (!$conversation) {
            Log::error('Conversation not found', ['conversation_id' => $conversationId]);
            return null;
        }

        // Create message record
        $whatsappMessage = WhatsAppMessage::create([
            'whatsapp_conversation_id' => $conversationId,
            'direction' => 'outbound',
            'type' => 'document',
            'content' => $caption,
            'media' => ['url' => $documentUrl, 'type' => 'document', 'filename' => $filename],
            'status' => 'pending',
            'sent_by' => $sentBy,
        ]);

        // Send via WhatsApp API
        $response = $this->whatsappService->sendDocumentMessage(
            $conversation->contact->phone_number,
            $documentUrl,
            $filename,
            $caption
        );

        if ($response && isset($response['messages'][0]['id'])) {
            $whatsappMessage->update([
                'whatsapp_message_id' => $response['messages'][0]['id'],
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $conversation->updateLastMessageTime();
            $conversation->contact->updateLastInteraction();
        } else {
            $whatsappMessage->markAsFailed('Failed to send document via WhatsApp API');
        }

        return $whatsappMessage->fresh();
    }

    /**
     * Send a template message and store it in the database.
     *
     * @param int $conversationId
     * @param string $templateName
     * @param string $languageCode
     * @param array $parameters
     * @param int|null $sentBy User ID
     * @return WhatsAppMessage|null
     */
    public function sendTemplateMessage(int $conversationId, string $templateName, string $languageCode, array $parameters = [], ?int $sentBy = null): ?WhatsAppMessage
    {
        $conversation = WhatsAppConversation::with('contact')->find($conversationId);

        if (!$conversation) {
            Log::error('Conversation not found', ['conversation_id' => $conversationId]);
            return null;
        }

        // Create message record
        $whatsappMessage = WhatsAppMessage::create([
            'whatsapp_conversation_id' => $conversationId,
            'direction' => 'outbound',
            'type' => 'template',
            'template_data' => [
                'name' => $templateName,
                'language' => $languageCode,
                'parameters' => $parameters,
            ],
            'status' => 'pending',
            'sent_by' => $sentBy,
        ]);

        // Send via WhatsApp API
        $response = $this->whatsappService->sendTemplateMessage(
            $conversation->contact->phone_number,
            $templateName,
            $languageCode,
            $parameters
        );

        if ($response && isset($response['messages'][0]['id'])) {
            $whatsappMessage->update([
                'whatsapp_message_id' => $response['messages'][0]['id'],
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $conversation->updateLastMessageTime();
            $conversation->contact->updateLastInteraction();
        } else {
            $whatsappMessage->markAsFailed('Failed to send template via WhatsApp API');
        }

        return $whatsappMessage->fresh();
    }

    /**
     * Process an incoming message from webhook.
     *
     * @param array $messageData
     * @return WhatsAppMessage|null
     */
    public function processIncomingMessage(array $messageData): ?WhatsAppMessage
    {
        try {
            DB::beginTransaction();

            $from = $messageData['from'] ?? null;
            $messageId = $messageData['id'] ?? null;
            $timestamp = $messageData['timestamp'] ?? null;
            $contactProfile = $messageData['contact_profile'] ?? null;
            $metadata = $messageData['metadata'] ?? null;

            if (!$from || !$messageId) {
                Log::error('Invalid incoming message data', ['data' => $messageData]);
                return null;
            }

            // Extract contact name from profile
            $contactName = $contactProfile['profile']['name'] ?? null;
            $waId = $contactProfile['wa_id'] ?? $from;

            // Get or create contact
            $contact = WhatsAppContact::firstOrCreate(
                ['phone_number' => $from],
                ['whatsapp_name' => $contactName]
            );

            // Update contact name if it changed
            if ($contactName && $contact->whatsapp_name !== $contactName) {
                $contact->update(['whatsapp_name' => $contactName]);
            }

            // Get or create active conversation
            $conversation = $contact->activeConversation();
            if (!$conversation) {
                $conversation = WhatsAppConversation::create([
                    'whatsapp_contact_id' => $contact->id,
                    'customer_id' => $contact->customer_id,
                    'status' => 'open',
                    'last_message_at' => now(),
                ]);
            }

            // Determine message type and content
            $type = $messageData['type'] ?? 'text';
            $content = null;
            $media = null;

            switch ($type) {
                case 'text':
                    $content = $messageData['text']['body'] ?? null;
                    break;
                case 'image':
                    $content = $messageData['image']['caption'] ?? null;
                    $mediaId = $messageData['image']['id'] ?? null;
                    $localPath = $mediaId ? $this->whatsappService->downloadMedia($mediaId) : null;
                    $media = [
                        'id' => $mediaId,
                        'mime_type' => $messageData['image']['mime_type'] ?? null,
                        'sha256' => $messageData['image']['sha256'] ?? null,
                        'type' => 'image',
                        'url' => $localPath ? asset('storage/' . $localPath) : null,
                        'path' => $localPath,
                    ];
                    break;
                case 'document':
                    $content = $messageData['document']['caption'] ?? null;
                    $mediaId = $messageData['document']['id'] ?? null;
                    $localPath = $mediaId ? $this->whatsappService->downloadMedia($mediaId) : null;
                    $media = [
                        'id' => $mediaId,
                        'filename' => $messageData['document']['filename'] ?? null,
                        'mime_type' => $messageData['document']['mime_type'] ?? null,
                        'sha256' => $messageData['document']['sha256'] ?? null,
                        'type' => 'document',
                        'url' => $localPath ? asset('storage/' . $localPath) : null,
                        'path' => $localPath,
                    ];
                    break;
                case 'video':
                    $content = $messageData['video']['caption'] ?? null;
                    $mediaId = $messageData['video']['id'] ?? null;
                    $localPath = $mediaId ? $this->whatsappService->downloadMedia($mediaId) : null;
                    $media = [
                        'id' => $mediaId,
                        'mime_type' => $messageData['video']['mime_type'] ?? null,
                        'sha256' => $messageData['video']['sha256'] ?? null,
                        'type' => 'video',
                        'url' => $localPath ? asset('storage/' . $localPath) : null,
                        'path' => $localPath,
                    ];
                    break;
                case 'audio':
                    $mediaId = $messageData['audio']['id'] ?? null;
                    $localPath = $mediaId ? $this->whatsappService->downloadMedia($mediaId) : null;
                    $media = [
                        'id' => $mediaId,
                        'mime_type' => $messageData['audio']['mime_type'] ?? null,
                        'sha256' => $messageData['audio']['sha256'] ?? null,
                        'voice' => $messageData['audio']['voice'] ?? false,
                        'type' => 'audio',
                        'url' => $localPath ? asset('storage/' . $localPath) : null,
                        'path' => $localPath,
                    ];
                    break;
                case 'location':
                    $media = [
                        'latitude' => $messageData['location']['latitude'] ?? null,
                        'longitude' => $messageData['location']['longitude'] ?? null,
                        'name' => $messageData['location']['name'] ?? null,
                        'address' => $messageData['location']['address'] ?? null,
                        'type' => 'location',
                    ];
                    break;
            }

            // Create message record
            $message = WhatsAppMessage::create([
                'whatsapp_conversation_id' => $conversation->id,
                'whatsapp_message_id' => $messageId,
                'direction' => 'inbound',
                'type' => $type,
                'content' => $content,
                'media' => $media,
                'status' => 'delivered',
                'delivered_at' => $timestamp ? now()->setTimestamp($timestamp) : now(),
            ]);

            // Update conversation and contact
            $conversation->updateLastMessageTime();
            $contact->updateLastInteraction();

            DB::commit();

            // Broadcast event to conversation staff (or all CRM staff if none assigned)
            $staffIds = $this->getStaffIdsForBroadcast($conversation);
            broadcast(new WhatsAppMessageReceived($message, $staffIds));

            Log::info('Incoming WhatsApp message processed', [
                'message_id' => $messageId,
                'from' => $from,
                'type' => $type,
                'contact_name' => $contactName,
            ]);

            // Process AI response if enabled and message is text
            if ($type === 'text' && $content) {
                $this->processAIResponse($conversation, $content);
            }

            return $message;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process incoming message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $messageData,
            ]);
            return null;
        }
    }

    /**
     * Update message status from webhook.
     *
     * @param string $messageId
     * @param string $status
     * @return bool
     */
    public function updateMessageStatus(string $messageId, string $status): bool
    {
        $message = WhatsAppMessage::where('whatsapp_message_id', $messageId)->first();

        if (!$message) {
            Log::warning('Message not found for status update', [
                'message_id' => $messageId,
                'status' => $status,
            ]);
            return false;
        }

        switch ($status) {
            case 'sent':
                $message->markAsSent();
                break;
            case 'delivered':
                $message->markAsDelivered();
                break;
            case 'read':
                $message->markAsRead();
                break;
            case 'failed':
                $message->markAsFailed('Message delivery failed');
                break;
        }

        Log::info('Message status updated', [
            'message_id' => $messageId,
            'status' => $status,
        ]);

        return true;
    }

    /**
     * Process AI response for incoming text message.
     *
     * @param WhatsAppConversation $conversation
     * @param string $userMessage
     * @return void
     */
    protected function processAIResponse(WhatsAppConversation $conversation, string $userMessage): void
    {
        // Check if AI agent is enabled
        if (!config('ai.whatsapp_agent.enabled', false)) {
            return;
        }

        // Check if Gemini API key is configured
        if (!config('ai.gemini.api_key')) {
            Log::debug('AI agent skipped: GEMINI_API_KEY not configured');
            return;
        }

        try {
            Log::info('Processing AI response', [
                'conversation_id' => $conversation->id,
                'message' => substr($userMessage, 0, 100),
            ]);

            // Get AI response
            $aiResponse = $this->aiAgent->processMessage($userMessage, [
                'phone' => $conversation->contact->phone_number ?? null,
            ]);

            if (!$aiResponse) {
                return;
            }

            // Send AI response back to user
            $this->sendTextMessage($conversation->id, $aiResponse);

            Log::info('AI response sent', [
                'conversation_id' => $conversation->id,
                'response_length' => strlen($aiResponse),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process AI response', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

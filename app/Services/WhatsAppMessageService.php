<?php

namespace App\Services;

use App\Events\WhatsAppMessageReceived;
use App\Events\WhatsAppMessageSent;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppMessageService
{
    protected WhatsAppService $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
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

            // Broadcast event
            $userId = $conversation->assigned_to;
            broadcast(new WhatsAppMessageSent($whatsappMessage->fresh(), $userId));
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
                    $media = [
                        'id' => $messageData['image']['id'] ?? null,
                        'mime_type' => $messageData['image']['mime_type'] ?? null,
                        'sha256' => $messageData['image']['sha256'] ?? null,
                        'type' => 'image',
                    ];
                    break;
                case 'document':
                    $content = $messageData['document']['caption'] ?? null;
                    $media = [
                        'id' => $messageData['document']['id'] ?? null,
                        'filename' => $messageData['document']['filename'] ?? null,
                        'mime_type' => $messageData['document']['mime_type'] ?? null,
                        'sha256' => $messageData['document']['sha256'] ?? null,
                        'type' => 'document',
                    ];
                    break;
                case 'video':
                    $content = $messageData['video']['caption'] ?? null;
                    $media = [
                        'id' => $messageData['video']['id'] ?? null,
                        'mime_type' => $messageData['video']['mime_type'] ?? null,
                        'sha256' => $messageData['video']['sha256'] ?? null,
                        'type' => 'video',
                    ];
                    break;
                case 'audio':
                    $media = [
                        'id' => $messageData['audio']['id'] ?? null,
                        'mime_type' => $messageData['audio']['mime_type'] ?? null,
                        'sha256' => $messageData['audio']['sha256'] ?? null,
                        'voice' => $messageData['audio']['voice'] ?? false,
                        'type' => 'audio',
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

            // Broadcast event
            $userId = $conversation->assigned_to;
            broadcast(new WhatsAppMessageReceived($message, $userId));

            Log::info('Incoming WhatsApp message processed', [
                'message_id' => $messageId,
                'from' => $from,
                'type' => $type,
                'contact_name' => $contactName,
            ]);

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
}

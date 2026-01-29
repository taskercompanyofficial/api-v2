<?php

namespace App\Http\Controllers\Authenticated\CRM;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppMessageService;
use App\Services\WhatsAppTemplateService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WhatsAppController extends Controller
{
    protected WhatsAppMessageService $messageService;
    protected WhatsAppTemplateService $templateService;
    protected WhatsAppService $whatsappService;

    public function __construct(
        WhatsAppMessageService $messageService,
        WhatsAppTemplateService $templateService,
        WhatsAppService $whatsappService
    ) {
        $this->messageService = $messageService;
        $this->templateService = $templateService;
        $this->whatsappService = $whatsappService;
    }

    /**
     * Get all conversations with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WhatsAppConversation::with(['contact', 'customer', 'assignedStaff', 'latestMessage'])
            ->orderBy('last_message_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by assigned staff
        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        // Search by phone number or customer name
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('contact', function ($q) use ($search) {
                $q->where('phone_number', 'like', "%{$search}%")
                    ->orWhere('whatsapp_name', 'like', "%{$search}%");
            })->orWhereHas('customer', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $conversations = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ]);
    }

    /**
     * Get a specific conversation with messages.
     */
    public function show(int $id): JsonResponse
    {
        $conversation = WhatsAppConversation::with([
            'contact',
            'customer',
            'assignedStaff',
            'messages' => function ($query) {
                $query->orderBy('created_at', 'asc');
            },
            'messages.sender',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $conversation,
        ]);
    }

    /**
     * Send a text message.
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|exists:whatsapp_conversations,id',
            'message' => 'required|string|max:4096',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $message = $this->messageService->sendTextMessage(
            $request->conversation_id,
            $request->message,
            $request->user()->id
        );

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => $message->load('conversation.contact'),
        ]);
    }

    /**
     * Send a media message (image, document, video).
     */
    public function sendMediaMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|exists:whatsapp_conversations,id',
            'media_type' => 'required|in:image,document,video,audio',
            'media_url' => 'required_without:file|url',
            'file' => 'required_without:media_url|file',
            'caption' => 'nullable|string|max:1024',
            'filename' => 'nullable|string', // For documents
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $mediaUrl = $request->media_url;
        $filename = $request->filename;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('whatsapp/media', 'public');
            $mediaUrl = asset('storage/' . $path);
            if (!$filename) {
                $filename = $file->getClientOriginalName();
            }
        }

        $message = null;

        switch ($request->media_type) {
            case 'image':
                $message = $this->messageService->sendImageMessage(
                    $request->conversation_id,
                    $mediaUrl,
                    $request->caption,
                    $request->user()->id
                );
                break;
            case 'document':
                $message = $this->messageService->sendDocumentMessage(
                    $request->conversation_id,
                    $mediaUrl,
                    $filename,
                    $request->caption,
                    $request->user()->id
                );
                break;
            case 'video':
                $message = $this->messageService->sendVideoMessage(
                    $request->conversation_id,
                    $mediaUrl,
                    $request->caption,
                    $request->user()->id
                );
                break;
            case 'audio':
                $message = $this->messageService->sendAudioMessage(
                    $request->conversation_id,
                    $mediaUrl,
                    $request->user()->id
                );
                break;
        }

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send media message',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Media message sent successfully',
            'data' => $message->load('conversation.contact'),
        ]);
    }

    /**
     * Send a template message.
     */
    public function sendTemplate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|exists:whatsapp_conversations,id',
            'template_name' => 'required|string|exists:whatsapp_templates,name',
            'language_code' => 'nullable|string',
            'parameters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $template = WhatsAppTemplate::where('name', $request->template_name)->first();

        if (!$template->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Template is not approved',
            ], 400);
        }

        $message = $this->messageService->sendTemplateMessage(
            $request->conversation_id,
            $request->template_name,
            $request->get('language_code', $template->language),
            $request->get('parameters', []),
            $request->user()->id
        );

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send template message',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Template message sent successfully',
            'data' => $message->load('conversation.contact'),
        ]);
    }

    /**
     * Mark messages as read.
     */
    public function markAsRead(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $success = $this->whatsappService->markMessageAsRead($request->message_id);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Message marked as read' : 'Failed to mark message as read',
        ]);
    }

    /**
     * Get all approved templates.
     */
    public function getTemplates(): JsonResponse
    {
        $templates = $this->templateService->getApprovedTemplates();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Sync templates from WhatsApp API.
     */
    public function syncTemplates(): JsonResponse
    {
        $count = $this->templateService->syncTemplates();

        return response()->json([
            'success' => true,
            'message' => "Synced {$count} templates",
            'count' => $count,
        ]);
    }

    /**
     * Get all WhatsApp contacts.
     */
    public function getContacts(Request $request): JsonResponse
    {
        $query = WhatsAppContact::with('customer')
            ->orderBy('last_interaction_at', 'desc');

        // Filter by opt-in status
        if ($request->has('opted_in')) {
            $query->where('is_opted_in', $request->boolean('opted_in'));
        }

        // Search by phone number or name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('phone_number', 'like', "%{$search}%")
                    ->orWhere('whatsapp_name', 'like', "%{$search}%");
            });
        }

        $contacts = $query->paginate($request->get('per_page', 15));

        return response()->json($contacts);
    }

    /**
     * Update contact opt-in status.
     */
    public function updateContactOptIn(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_opted_in' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $contact = WhatsAppContact::findOrFail($id);

        if ($request->boolean('is_opted_in')) {
            $contact->optIn();
        } else {
            $contact->optOut();
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact opt-in status updated',
            'data' => $contact->fresh(),
        ]);
    }

    /**
     * Update conversation status.
     */
    public function updateConversationStatus(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:open,closed,archived',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $conversation = WhatsAppConversation::findOrFail($id);

        switch ($request->status) {
            case 'open':
                $conversation->open();
                break;
            case 'closed':
                $conversation->close();
                break;
            case 'archived':
                $conversation->archive();
                break;
        }

        return response()->json([
            'success' => true,
            'message' => 'Conversation status updated',
            'data' => $conversation->fresh(),
        ]);
    }

    /**
     * Assign conversation to staff member.
     */
    public function assignConversation(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $conversation = WhatsAppConversation::findOrFail($id);
        $conversation->assignTo($request->user_id);

        return response()->json([
            'success' => true,
            'message' => 'Conversation assigned successfully',
            'data' => $conversation->fresh()->load('assignedStaff'),
        ]);
    }

    /**
     * Get media file URL for WhatsApp media.
     * This acts as a proxy to retrieve media from WhatsApp CDN.
     */
    public function getMedia(string $mediaId): JsonResponse
    {
        try {
            // Download/cache the media file
            $localPath = $this->whatsappService->downloadMedia($mediaId);

            // ss
            if (!$localPath) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve media',
                ], 404);
            }

            // Return the local URL
            $url = asset('storage/' . $localPath);

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $url,
                    'path' => $localPath,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving media: ' . $e->getMessage(),
            ], 500);
        }
    }
}

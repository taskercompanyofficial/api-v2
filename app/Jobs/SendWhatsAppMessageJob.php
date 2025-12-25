<?php

namespace App\Jobs;

use App\Models\WhatsAppConversation;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 5;

    /**
     * The message data.
     *
     * @var array
     */
    protected array $messageData;

    /**
     * Create a new job instance.
     */
    public function __construct(array $messageData)
    {
        $this->messageData = $messageData;
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsappService): void
    {
        $type = $this->messageData['type'];
        $to = $this->messageData['to'];

        Log::info('Sending WhatsApp message via job', [
            'type' => $type,
            'to' => $to,
        ]);

        $response = null;

        switch ($type) {
            case 'text':
                $response = $whatsappService->sendTextMessage(
                    $to,
                    $this->messageData['message']
                );
                break;

            case 'image':
                $response = $whatsappService->sendImageMessage(
                    $to,
                    $this->messageData['image_url'],
                    $this->messageData['caption'] ?? null
                );
                break;

            case 'document':
                $response = $whatsappService->sendDocumentMessage(
                    $to,
                    $this->messageData['document_url'],
                    $this->messageData['filename'] ?? null,
                    $this->messageData['caption'] ?? null
                );
                break;

            case 'video':
                $response = $whatsappService->sendVideoMessage(
                    $to,
                    $this->messageData['video_url'],
                    $this->messageData['caption'] ?? null
                );
                break;

            case 'template':
                $response = $whatsappService->sendTemplateMessage(
                    $to,
                    $this->messageData['template_name'],
                    $this->messageData['language_code'],
                    $this->messageData['parameters'] ?? []
                );
                break;
        }

        if (!$response) {
            throw new \Exception('Failed to send WhatsApp message');
        }

        Log::info('WhatsApp message sent successfully via job', [
            'message_id' => $response['messages'][0]['id'] ?? null,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('WhatsApp message job failed', [
            'error' => $exception->getMessage(),
            'message_data' => $this->messageData,
        ]);
    }
}

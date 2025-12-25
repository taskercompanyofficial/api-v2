<?php

namespace App\Jobs;

use App\Services\WhatsAppWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppWebhookJob implements ShouldQueue
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
    public $backoff = 10;

    /**
     * The webhook payload.
     *
     * @var array
     */
    protected array $payload;

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
        $this->onQueue(config('whatsapp.webhook.queue'));
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppWebhookService $webhookService): void
    {
        Log::info('Processing WhatsApp webhook job', [
            'payload_id' => $this->payload['entry'][0]['id'] ?? 'unknown',
        ]);

        $webhookService->processWebhook($this->payload);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('WhatsApp webhook job failed', [
            'error' => $exception->getMessage(),
            'payload' => $this->payload,
        ]);
    }
}

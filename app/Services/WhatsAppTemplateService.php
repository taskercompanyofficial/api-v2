<?php

namespace App\Services;

use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppTemplateService
{
    protected WhatsAppService $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Sync templates from WhatsApp Business API to database.
     *
     * @return int Number of templates synced
     */
    public function syncTemplates(): int
    {
        $templates = $this->whatsappService->getMessageTemplates();

        if (!$templates) {
            Log::error('Failed to fetch templates from WhatsApp API');
            return 0;
        }

        $syncedCount = 0;

        foreach ($templates as $templateData) {
            try {
                $template = WhatsAppTemplate::updateOrCreate(
                    ['name' => $templateData['name']],
                    [
                        'language' => $templateData['language'] ?? 'en',
                        'category' => $templateData['category'] ?? 'UTILITY',
                        'status' => $templateData['status'] ?? 'PENDING',
                        'components' => $templateData['components'] ?? [],
                        'whatsapp_template_id' => $templateData['id'] ?? null,
                        'parameter_count' => $this->countParameters($templateData['components'] ?? []),
                        'preview_text' => $this->generatePreviewText($templateData['components'] ?? []),
                    ]
                );

                if ($template->status === 'APPROVED' && !$template->approved_at) {
                    $template->update(['approved_at' => now()]);
                }

                $syncedCount++;
            } catch (\Exception $e) {
                Log::error('Failed to sync template', [
                    'template_name' => $templateData['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Clear template cache
        $this->clearTemplateCache();

        Log::info('WhatsApp templates synced', ['count' => $syncedCount]);

        return $syncedCount;
    }

    /**
     * Get approved templates (with caching).
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getApprovedTemplates()
    {
        if (!config('whatsapp.template_cache.enabled')) {
            return WhatsAppTemplate::approved()->get();
        }

        return Cache::remember(
            'whatsapp_approved_templates',
            config('whatsapp.template_cache.ttl'),
            function () {
                return WhatsAppTemplate::approved()->get();
            }
        );
    }

    /**
     * Get a template by name.
     *
     * @param string $name
     * @return WhatsAppTemplate|null
     */
    public function getTemplateByName(string $name): ?WhatsAppTemplate
    {
        return WhatsAppTemplate::where('name', $name)->first();
    }

    /**
     * Render template with parameters.
     *
     * @param string $templateName
     * @param array $parameters
     * @return array|null
     */
    public function renderTemplate(string $templateName, array $parameters): ?array
    {
        $template = $this->getTemplateByName($templateName);

        if (!$template) {
            Log::error('Template not found', ['name' => $templateName]);
            return null;
        }

        if (!$template->isApproved()) {
            Log::error('Template not approved', ['name' => $templateName]);
            return null;
        }

        if (!$template->validateParameters($parameters)) {
            Log::error('Invalid parameter count for template', [
                'name' => $templateName,
                'expected' => $template->parameter_count,
                'provided' => count($parameters),
            ]);
            return null;
        }

        return $template->render($parameters);
    }

    /**
     * Count parameters in template components.
     *
     * @param array $components
     * @return int
     */
    protected function countParameters(array $components): int
    {
        $count = 0;

        foreach ($components as $component) {
            if (isset($component['parameters'])) {
                $count += count($component['parameters']);
            }
            
            // Check for parameters in text using {{1}}, {{2}}, etc.
            if (isset($component['text'])) {
                preg_match_all('/\{\{(\d+)\}\}/', $component['text'], $matches);
                $count = max($count, count($matches[0]));
            }
        }

        return $count;
    }

    /**
     * Generate preview text from template components.
     *
     * @param array $components
     * @return string|null
     */
    protected function generatePreviewText(array $components): ?string
    {
        $bodyComponent = collect($components)->firstWhere('type', 'BODY');
        
        if ($bodyComponent && isset($bodyComponent['text'])) {
            return $bodyComponent['text'];
        }

        return null;
    }

    /**
     * Clear template cache.
     *
     * @return void
     */
    public function clearTemplateCache(): void
    {
        Cache::forget('whatsapp_approved_templates');
    }
}

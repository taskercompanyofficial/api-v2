<?php

namespace App\Services;

use App\Models\BusinessPhoneNumber;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsAppService
{
    protected Client $client;
    protected string $accessToken;
    protected string $phoneNumberId;
    protected string $apiVersion;
    protected string $baseUrl;
    protected ?BusinessPhoneNumber $businessPhone;

    public function __construct(?int $businessPhoneNumberId = null)
    {
        // Get business phone number from database or use default
        if ($businessPhoneNumberId) {
            $this->businessPhone = BusinessPhoneNumber::with('apiToken')
                ->active()
                ->verified()
                ->findOrFail($businessPhoneNumberId);
        } else {
            $this->businessPhone = BusinessPhoneNumber::getDefaultWhatsApp();
        }

        // If no database credentials, fall back to config (for backward compatibility)
        if ($this->businessPhone && $this->businessPhone->apiToken) {
            $this->accessToken = $this->businessPhone->getAccessToken();
            $this->phoneNumberId = $this->businessPhone->phone_number_id;
        } else {
            // Fallback to environment variables
            $this->accessToken = config('whatsapp.access_token');
            $this->phoneNumberId = config('whatsapp.phone_number_id');
        }

        $this->apiVersion = config('whatsapp.api_version');
        $this->baseUrl = config('whatsapp.graph_api_url');

        if (!$this->accessToken || !$this->phoneNumberId) {
            throw new \Exception('WhatsApp credentials not configured. Please add a business phone number or set environment variables.');
        }

        $this->client = new Client([
            'base_uri' => "{$this->baseUrl}/{$this->apiVersion}/",
            'timeout' => config('whatsapp.rate_limit.timeout'),
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
                'Content-Type' => 'application/json',
            ],
        ]);

        // Mark token as used
        if ($this->businessPhone && $this->businessPhone->apiToken) {
            $this->businessPhone->apiToken->markAsUsed();
        }
    }

    /**
     * Send a text message.
     *
     * @param string $to Phone number in E.164 format
     * @param string $message Message text
     * @return array|null
     */
    public function sendTextMessage(string $to, string $message): ?array
    {
        try {
            $response = $this->client->post("{$this->phoneNumberId}/messages", [
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => true,
                        'body' => $message,
                    ],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            Log::info('WhatsApp text message sent', [
                'to' => $to,
                'message_id' => $data['messages'][0]['id'] ?? null,
            ]);

            return $data;
        } catch (GuzzleException $e) {
            Log::error('Failed to send WhatsApp text message', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send an image message.
     *
     * @param string $to Phone number in E.164 format
     * @param string $imageUrl URL or media ID
     * @param string|null $caption Optional caption
     * @return array|null
     */
    public function sendImageMessage(string $to, string $imageUrl, ?string $caption = null): ?array
    {
        try {
            $imageData = ['link' => $imageUrl];

            if ($caption) {
                $imageData['caption'] = $caption;
            }

            $response = $this->client->post("{$this->phoneNumberId}/messages", [
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'image',
                    'image' => $imageData,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            Log::info('WhatsApp image message sent', [
                'to' => $to,
                'message_id' => $data['messages'][0]['id'] ?? null,
            ]);

            return $data;
        } catch (GuzzleException $e) {
            Log::error('Failed to send WhatsApp image message', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send a document message.
     *
     * @param string $to Phone number in E.164 format
     * @param string $documentUrl URL or media ID
     * @param string|null $filename Optional filename
     * @param string|null $caption Optional caption
     * @return array|null
     */
    public function sendDocumentMessage(string $to, string $documentUrl, ?string $filename = null, ?string $caption = null): ?array
    {
        try {
            $documentData = ['link' => $documentUrl];

            if ($filename) {
                $documentData['filename'] = $filename;
            }

            if ($caption) {
                $documentData['caption'] = $caption;
            }

            $response = $this->client->post("{$this->phoneNumberId}/messages", [
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'document',
                    'document' => $documentData,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            Log::info('WhatsApp document message sent', [
                'to' => $to,
                'message_id' => $data['messages'][0]['id'] ?? null,
            ]);

            return $data;
        } catch (GuzzleException $e) {
            Log::error('Failed to send WhatsApp document message', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send a video message.
     *
     * @param string $to Phone number in E.164 format
     * @param string $videoUrl URL or media ID
     * @param string|null $caption Optional caption
     * @return array|null
     */
    public function sendVideoMessage(string $to, string $videoUrl, ?string $caption = null): ?array
    {
        try {
            $videoData = ['link' => $videoUrl];

            if ($caption) {
                $videoData['caption'] = $caption;
            }

            $response = $this->client->post("{$this->phoneNumberId}/messages", [
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'video',
                    'video' => $videoData,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            Log::info('WhatsApp video message sent', [
                'to' => $to,
                'message_id' => $data['messages'][0]['id'] ?? null,
            ]);

            return $data;
        } catch (GuzzleException $e) {
            Log::error('Failed to send WhatsApp video message', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send a template message.
     *
     * @param string $to Phone number in E.164 format
     * @param string $templateName Template name
     * @param string $languageCode Language code (e.g., 'en', 'es')
     * @param array $parameters Template parameters
     * @return array|null
     */
    public function sendTemplateMessage(string $to, string $templateName, string $languageCode, array $parameters = []): ?array
    {
        try {
            $components = [];

            if (!empty($parameters)) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => array_map(function ($param) {
                        return [
                            'type' => 'text',
                            'text' => $param,
                        ];
                    }, $parameters),
                ];
            }

            $response = $this->client->post("{$this->phoneNumberId}/messages", [
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => [
                            'code' => $languageCode,
                        ],
                        'components' => $components,
                    ],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            Log::info('WhatsApp template message sent', [
                'to' => $to,
                'template' => $templateName,
                'message_id' => $data['messages'][0]['id'] ?? null,
            ]);

            return $data;
        } catch (GuzzleException $e) {
            Log::error('Failed to send WhatsApp template message', [
                'to' => $to,
                'template' => $templateName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Mark a message as read.
     *
     * @param string $messageId WhatsApp message ID
     * @return bool
     */
    public function markMessageAsRead(string $messageId): bool
    {
        try {
            $this->client->post("{$this->phoneNumberId}/messages", [
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'status' => 'read',
                    'message_id' => $messageId,
                ],
            ]);

            Log::info('WhatsApp message marked as read', ['message_id' => $messageId]);

            return true;
        } catch (GuzzleException $e) {
            Log::error('Failed to mark WhatsApp message as read', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Download media from WhatsApp.
     *
     * @param string $mediaId WhatsApp media ID
     * @return string|null Local file path
     */
    public function downloadMedia(string $mediaId): ?string
    {
        try {
            // First, get the media URL
            $response = $this->client->get($mediaId);
            $mediaData = json_decode($response->getBody()->getContents(), true);
            $mediaUrl = $mediaData['url'] ?? null;

            if (!$mediaUrl) {
                return null;
            }

            // Download the media file
            $mediaResponse = $this->client->get($mediaUrl);
            $mediaContent = $mediaResponse->getBody()->getContents();

            // Generate a unique filename
            $filename = 'whatsapp/media/' . uniqid() . '_' . basename($mediaUrl);

            // Store the file
            Storage::disk(config('whatsapp.media.disk'))->put($filename, $mediaContent);

            Log::info('WhatsApp media downloaded', [
                'media_id' => $mediaId,
                'filename' => $filename,
            ]);

            return $filename;
        } catch (GuzzleException $e) {
            Log::error('Failed to download WhatsApp media', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get message templates from WhatsApp Business API.
     *
     * @return array|null
     */
    public function getMessageTemplates(): ?array
    {
        try {
            $response = $this->client->get(config('whatsapp.business_account_id') . '/message_templates');
            $data = json_decode($response->getBody()->getContents(), true);

            Log::info('WhatsApp templates fetched', [
                'count' => count($data['data'] ?? []),
            ]);

            return $data['data'] ?? [];
        } catch (GuzzleException $e) {
            Log::error('Failed to fetch WhatsApp templates', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send an interactive message with reply buttons.
     * Maximum 3 buttons allowed by WhatsApp.
     *
     * @param string $to Phone number in E.164 format
     * @param string $bodyText Message body text
     * @param array $buttons Array of buttons [['id' => 'btn_1', 'title' => 'Button 1'], ...]
     * @param string|null $headerText Optional header text
     * @param string|null $footerText Optional footer text
     * @return array|null
     */
    public function sendInteractiveButtons(
        string $to,
        string $bodyText,
        array $buttons,
        ?string $headerText = null,
        ?string $footerText = null
    ): ?array {
        try {
            // WhatsApp allows max 3 buttons
            $buttons = array_slice($buttons, 0, 3);

            $interactive = [
                'type' => 'button',
                'body' => ['text' => $bodyText],
                'action' => [
                    'buttons' => array_map(fn($btn) => [
                        'type' => 'reply',
                        'reply' => [
                            'id' => $btn['id'],
                            'title' => substr($btn['title'], 0, 20), // Max 20 chars
                        ],
                    ], $buttons),
                ],
            ];

            if ($headerText) {
                $interactive['header'] = ['type' => 'text', 'text' => $headerText];
            }
            if ($footerText) {
                $interactive['footer'] = ['text' => $footerText];
            }

            $response = $this->client->post("{$this->phoneNumberId}/messages", [
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'interactive',
                    'interactive' => $interactive,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            Log::info('WhatsApp interactive buttons sent', [
                'to' => $to,
                'message_id' => $data['messages'][0]['id'] ?? null,
            ]);

            return $data;
        } catch (GuzzleException $e) {
            Log::error('Failed to send WhatsApp interactive buttons', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send an interactive list message.
     * Great for menus with more than 3 options.
     *
     * @param string $to Phone number in E.164 format
     * @param string $bodyText Message body text
     * @param string $buttonText Text for the list button (max 20 chars)
     * @param array $sections Array of sections with rows
     * @param string|null $headerText Optional header text
     * @param string|null $footerText Optional footer text
     * @return array|null
     */
    public function sendInteractiveList(
        string $to,
        string $bodyText,
        string $buttonText,
        array $sections,
        ?string $headerText = null,
        ?string $footerText = null
    ): ?array {
        try {
            $interactive = [
                'type' => 'list',
                'body' => ['text' => $bodyText],
                'action' => [
                    'button' => substr($buttonText, 0, 20),
                    'sections' => $sections,
                ],
            ];

            if ($headerText) {
                $interactive['header'] = ['type' => 'text', 'text' => $headerText];
            }
            if ($footerText) {
                $interactive['footer'] = ['text' => $footerText];
            }

            $response = $this->client->post("{$this->phoneNumberId}/messages", [
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'interactive',
                    'interactive' => $interactive,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            Log::info('WhatsApp interactive list sent', [
                'to' => $to,
                'message_id' => $data['messages'][0]['id'] ?? null,
            ]);

            return $data;
        } catch (GuzzleException $e) {
            Log::error('Failed to send WhatsApp interactive list', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

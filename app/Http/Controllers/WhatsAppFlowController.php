<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppFlowEncryptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppFlowController extends Controller
{
    protected WhatsAppFlowEncryptionService $encryptionService;

    public function __construct(WhatsAppFlowEncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    /**
     * Handle WhatsApp Flow data exchange requests.
     *
     * This endpoint receives encrypted requests from WhatsApp Flows,
     * decrypts them, processes the action, and returns encrypted responses.
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        $requestId = 'flow_' . uniqid() . '_' . time();

        Log::info('WhatsApp Flow request received', [
            'request_id' => $requestId,
        ]);

        // Validate signature if app secret is configured
        $signature = $request->header('X-Hub-Signature-256');
        if ($signature && !$this->encryptionService->validateSignature($request->getContent(), $signature)) {
            Log::warning('WhatsApp Flow signature validation failed', [
                'request_id' => $requestId,
            ]);
            return response('Invalid signature', 432);
        }

        try {
            // Get the encrypted request body
            $body = $request->all();

            // Validate required fields
            if (!isset($body['encrypted_flow_data']) || !isset($body['encrypted_aes_key']) || !isset($body['initial_vector'])) {
                Log::error('WhatsApp Flow request missing required fields', [
                    'request_id' => $requestId,
                    'fields' => array_keys($body),
                ]);
                return response('Missing required fields', 400);
            }

            // Decrypt the request
            $decrypted = $this->encryptionService->decryptRequest($body);
            $payload = $decrypted['decryptedBody'];
            $aesKey = $decrypted['aesKey'];
            $iv = $decrypted['iv'];

            Log::info('WhatsApp Flow request decrypted', [
                'request_id' => $requestId,
                'action' => $payload['action'] ?? 'unknown',
                'screen' => $payload['screen'] ?? null,
                'version' => $payload['version'] ?? null,
            ]);

            // Process the action
            $response = $this->processAction($payload, $requestId);

            // Encrypt the response
            $encryptedResponse = $this->encryptionService->encryptResponse($response, $aesKey, $iv);

            Log::info('WhatsApp Flow response sent', [
                'request_id' => $requestId,
                'response_screen' => $response['screen'] ?? null,
            ]);

            // Return encrypted response as plain text
            return response($encryptedResponse, 200)
                ->header('Content-Type', 'text/plain');
        } catch (\Exception $e) {
            Log::error('WhatsApp Flow request processing failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 421 for decryption errors as per WhatsApp docs
            return response('Decryption failed', 421);
        }
    }

    /**
     * Process the Flow action and return appropriate response.
     *
     * @param array $payload Decrypted request payload
     * @param string $requestId Request ID for logging
     * @return array Response payload to encrypt
     */
    protected function processAction(array $payload, string $requestId): array
    {
        $action = $payload['action'] ?? '';
        $version = $payload['version'] ?? '3.0';
        $screen = $payload['screen'] ?? '';
        $data = $payload['data'] ?? [];
        $flowToken = $payload['flow_token'] ?? '';

        switch ($action) {
            case 'ping':
                // Health check request
                return $this->handlePing();

            case 'INIT':
                // Flow initialization - return first screen
                return $this->handleInit($flowToken);

            case 'BACK':
                // User navigated back
                return $this->handleBack($screen, $flowToken);

            case 'data_exchange':
                // Form submission / screen interaction
                return $this->handleDataExchange($screen, $data, $flowToken, $requestId);

            default:
                // Check if it's an error notification
                if (isset($data['error'])) {
                    return $this->handleErrorNotification($data);
                }

                Log::warning('Unknown WhatsApp Flow action', [
                    'request_id' => $requestId,
                    'action' => $action,
                ]);

                return [
                    'screen' => $screen ?: 'ERROR',
                    'data' => [
                        'error_message' => 'Unknown action: ' . $action,
                    ],
                ];
        }
    }

    /**
     * Handle ping/health check request.
     *
     * @return array
     */
    protected function handlePing(): array
    {
        return [
            'data' => [
                'status' => 'active',
            ],
        ];
    }

    /**
     * Handle INIT action - Flow initialization.
     *
     * Override this method in a child controller to customize the initial screen.
     *
     * @param string $flowToken
     * @return array
     */
    protected function handleInit(string $flowToken): array
    {
        // Default implementation - should be overridden for specific flows
        return [
            'screen' => 'WELCOME',
            'data' => [
                'flow_token' => $flowToken,
                'welcome_message' => 'Welcome to Tasker Company!',
            ],
        ];
    }

    /**
     * Handle BACK action - User navigated back.
     *
     * Override this method to customize back navigation behavior.
     *
     * @param string $currentScreen
     * @param string $flowToken
     * @return array
     */
    protected function handleBack(string $currentScreen, string $flowToken): array
    {
        // Default implementation - return to previous screen logic
        // This should be customized based on your flow structure
        return [
            'screen' => 'WELCOME',
            'data' => [
                'flow_token' => $flowToken,
            ],
        ];
    }

    /**
     * Handle data_exchange action - Form submissions and screen interactions.
     *
     * This is the main method to override for custom flow logic.
     *
     * @param string $screen Current screen name
     * @param array $data Form data from the screen
     * @param string $flowToken Flow token
     * @param string $requestId Request ID for logging
     * @return array
     */
    protected function handleDataExchange(string $screen, array $data, string $flowToken, string $requestId): array
    {
        Log::info('WhatsApp Flow data exchange', [
            'request_id' => $requestId,
            'screen' => $screen,
            'data' => $data,
        ]);

        // Default implementation - should be overridden for specific flows
        // This is where you'd process form submissions and return the next screen

        // Example: Simple form completion
        return [
            'screen' => 'SUCCESS',
            'data' => [
                'extension_message_response' => [
                    'params' => [
                        'flow_token' => $flowToken,
                        'submitted_screen' => $screen,
                    ],
                ],
            ],
        ];
    }

    /**
     * Handle error notification from WhatsApp.
     *
     * @param array $data Error data
     * @return array
     */
    protected function handleErrorNotification(array $data): array
    {
        Log::error('WhatsApp Flow error notification received', [
            'error' => $data['error'] ?? 'unknown',
            'error_message' => $data['error_message'] ?? '',
        ]);

        // Acknowledge the error
        return [
            'data' => [
                'acknowledged' => true,
            ],
        ];
    }
}

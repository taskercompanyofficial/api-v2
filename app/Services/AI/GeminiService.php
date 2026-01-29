<?php

namespace App\Services\AI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected Client $httpClient;
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('ai.gemini.api_key');
        $this->model = config('ai.gemini.model', 'gemini-2.0-flash');
        $this->baseUrl = config('ai.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');

        $this->httpClient = new Client([
            'timeout' => config('ai.gemini.timeout', 30),
        ]);
    }

    /**
     * Generate content with optional function calling.
     *
     * @param array $contents Conversation contents
     * @param array $tools Optional tools/functions for the model
     * @param string|null $systemInstruction Optional system prompt
     * @return array Response from Gemini
     * @throws \Exception
     */
    public function generateContent(array $contents, array $tools = [], ?string $systemInstruction = null): array
    {
        Log::info('=== GEMINI API CALL START ===', [
            'model' => $this->model,
            'has_tools' => !empty($tools),
            'has_system_instruction' => !empty($systemInstruction),
            'contents_count' => count($contents),
        ]);

        if (!$this->apiKey) {
            Log::error('Gemini API key is empty');
            throw new \Exception('Gemini API key not configured. Set GEMINI_API_KEY in .env');
        }

        $url = "{$this->baseUrl}/models/{$this->model}:generateContent";

        $payload = [
            'contents' => $contents,
        ];

        // Add system instruction if provided
        if ($systemInstruction) {
            $payload['system_instruction'] = [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ];
        }

        // Add tools if provided
        if (!empty($tools)) {
            $payload['tools'] = $tools;
        }

        Log::debug('Gemini API request URL', ['url' => $url]);

        try {
            $response = $this->httpClient->post($url, [
                'headers' => [
                    'x-goog-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            Log::info('=== GEMINI API CALL SUCCESS ===', [
                'model' => $this->model,
                'usage' => $result['usageMetadata'] ?? null,
                'has_candidates' => !empty($result['candidates']),
                'finish_reason' => $result['candidates'][0]['finishReason'] ?? 'unknown',
            ]);

            return $result;
        } catch (GuzzleException $e) {
            Log::error('=== GEMINI API CALL FAILED ===', [
                'error' => $e->getMessage(),
                'model' => $this->model,
                'url' => $url,
            ]);
            throw new \Exception('Failed to communicate with Gemini API: ' . $e->getMessage());
        }
    }

    /**
     * Extract text response from Gemini result.
     *
     * @param array $result Gemini API response
     * @return string|null
     */
    public function extractTextResponse(array $result): ?string
    {
        $candidates = $result['candidates'] ?? [];

        if (empty($candidates)) {
            return null;
        }

        $parts = $candidates[0]['content']['parts'] ?? [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                return $part['text'];
            }
        }

        return null;
    }

    /**
     * Extract function calls from Gemini result.
     *
     * @param array $result Gemini API response
     * @return array Array of function calls
     */
    public function extractFunctionCalls(array $result): array
    {
        $candidates = $result['candidates'] ?? [];

        if (empty($candidates)) {
            return [];
        }

        $parts = $candidates[0]['content']['parts'] ?? [];
        $functionCalls = [];

        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                $functionCalls[] = $part['functionCall'];
            }
        }

        return $functionCalls;
    }

    /**
     * Check if response contains function calls.
     *
     * @param array $result Gemini API response
     * @return bool
     */
    public function hasFunctionCalls(array $result): bool
    {
        return !empty($this->extractFunctionCalls($result));
    }

    /**
     * Build function response content for multi-turn conversation.
     *
     * @param string $functionName
     * @param array $response
     * @return array
     */
    public function buildFunctionResponse(string $functionName, array $response): array
    {
        return [
            'role' => 'function',
            'parts' => [
                [
                    'functionResponse' => [
                        'name' => $functionName,
                        'response' => $response,
                    ],
                ],
            ],
        ];
    }

    /**
     * Build user message content.
     *
     * @param string $message
     * @return array
     */
    public function buildUserMessage(string $message): array
    {
        return [
            'role' => 'user',
            'parts' => [
                ['text' => $message],
            ],
        ];
    }

    /**
     * Build model message content.
     *
     * @param string $message
     * @return array
     */
    public function buildModelMessage(string $message): array
    {
        return [
            'role' => 'model',
            'parts' => [
                ['text' => $message],
            ],
        ];
    }
}

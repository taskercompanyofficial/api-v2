<?php

namespace App\Services;

use App\Models\BusinessPhoneNumber;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\AES;

class WhatsAppFlowEncryptionService
{
    protected ?string $privateKeyPath;
    protected ?string $privateKeyPassphrase;
    protected string $accessToken;
    protected string $phoneNumberId;
    protected string $apiVersion;
    protected string $graphApiUrl;
    protected Client $httpClient;

    public function __construct(?int $businessPhoneNumberId = null)
    {
        // Try to get credentials from BusinessPhoneNumber model first
        $businessPhone = null;
        if ($businessPhoneNumberId) {
            $businessPhone = BusinessPhoneNumber::find($businessPhoneNumberId);
        }

        if (!$businessPhone) {
            $businessPhone = BusinessPhoneNumber::getDefaultWhatsApp();
        }

        // Use BusinessPhoneNumber credentials if available, fallback to config
        if ($businessPhone) {
            $this->accessToken = $businessPhone->api_token;
            $this->phoneNumberId = $businessPhone->phone_number_id;
        } else {
            $this->accessToken = config('whatsapp.access_token');
            $this->phoneNumberId = config('whatsapp.phone_number_id');
        }

        $this->apiVersion = config('whatsapp.api_version', 'v18.0');
        $this->graphApiUrl = config('whatsapp.graph_api_url', 'https://graph.facebook.com');
        $this->privateKeyPath = config('whatsapp.flows.private_key_path');
        $this->privateKeyPassphrase = config('whatsapp.flows.private_key_passphrase');

        $this->httpClient = new Client([
            'timeout' => config('whatsapp.rate_limit.timeout', 30),
        ]);
    }

    /**
     * Decrypt incoming WhatsApp Flow request.
     *
     * The request contains:
     * - encrypted_aes_key: Base64 encoded, RSA encrypted AES key
     * - encrypted_flow_data: Base64 encoded, AES-GCM encrypted payload
     * - initial_vector: Base64 encoded IV for AES-GCM
     *
     * @param array $body Request body with encrypted_flow_data, encrypted_aes_key, initial_vector
     * @return array {decryptedBody: array, aesKey: string, iv: string}
     * @throws \Exception
     */
    public function decryptRequest(array $body): array
    {
        $encryptedAesKey = base64_decode($body['encrypted_aes_key']);
        $encryptedFlowData = base64_decode($body['encrypted_flow_data']);
        $initialVector = base64_decode($body['initial_vector']);

        // Load and configure RSA private key
        $privateKeyPem = $this->loadPrivateKey();

        /** @var \phpseclib3\Crypt\RSA\PrivateKey $rsa */
        $rsa = RSA::load($privateKeyPem, $this->privateKeyPassphrase ?: false)
            ->withPadding(RSA::ENCRYPTION_OAEP)
            ->withHash('sha256')
            ->withMGFHash('sha256');

        // Decrypt the AES key using RSA private key
        $decryptedAesKey = $rsa->decrypt($encryptedAesKey);

        if (!$decryptedAesKey) {
            throw new \Exception('Failed to decrypt AES key with RSA private key.');
        }

        // The authentication tag is the last 16 bytes of the encrypted data
        $tagLength = 16;
        $encryptedFlowDataBody = substr($encryptedFlowData, 0, -$tagLength);
        $encryptedFlowDataTag = substr($encryptedFlowData, -$tagLength);

        // Configure AES-GCM for decryption
        $aes = new AES('gcm');
        $aes->setKey($decryptedAesKey);
        $aes->setNonce($initialVector);
        $aes->setTag($encryptedFlowDataTag);

        // Decrypt the flow data
        $decrypted = $aes->decrypt($encryptedFlowDataBody);

        if ($decrypted === false) {
            throw new \Exception('Failed to decrypt flow data with AES-GCM.');
        }

        $decryptedBody = json_decode($decrypted, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to parse decrypted flow data as JSON.');
        }

        return [
            'decryptedBody' => $decryptedBody,
            'aesKey' => $decryptedAesKey,
            'iv' => $initialVector,
        ];
    }

    /**
     * Encrypt response for WhatsApp Flow.
     *
     * Uses AES-GCM with the same key but inverted IV bits.
     *
     * @param array $response Response data to encrypt
     * @param string $aesKey The AES key from decryption
     * @param string $iv The original IV from decryption
     * @return string Base64 encoded encrypted response
     */
    public function encryptResponse(array $response, string $aesKey, string $iv): string
    {
        // Invert all bits of the IV for response encryption
        $flippedIv = ~$iv;

        // Encrypt using OpenSSL for AES-GCM (phpseclib doesn't expose tag concatenation easily)
        $tag = '';
        $encrypted = openssl_encrypt(
            json_encode($response),
            'aes-128-gcm',
            $aesKey,
            OPENSSL_RAW_DATA,
            $flippedIv,
            $tag
        );

        // Concatenate encrypted data with tag and base64 encode
        return base64_encode($encrypted . $tag);
    }

    /**
     * Upload business public key to WhatsApp Cloud API.
     *
     * POST /{phone_number_id}/whatsapp_business_encryption
     *
     * @param string $publicKeyPem The public key in PEM format
     * @param int|null $phoneNumberId Optional specific phone number ID
     * @return bool
     */
    public function uploadPublicKey(string $publicKeyPem, ?int $phoneNumberId = null): bool
    {
        $phoneId = $phoneNumberId ?? $this->phoneNumberId;

        try {
            $response = $this->httpClient->post(
                "{$this->graphApiUrl}/{$this->apiVersion}/{$phoneId}/whatsapp_business_encryption",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$this->accessToken}",
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'form_params' => [
                        'business_public_key' => $publicKeyPem,
                    ],
                ]
            );

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['success']) && $result['success'] === true) {
                Log::info('WhatsApp Flow public key uploaded successfully', [
                    'phone_number_id' => $phoneId,
                ]);
                return true;
            }

            Log::warning('WhatsApp Flow public key upload returned unexpected response', [
                'phone_number_id' => $phoneId,
                'response' => $result,
            ]);
            return false;
        } catch (GuzzleException $e) {
            Log::error('Failed to upload WhatsApp Flow public key', [
                'phone_number_id' => $phoneId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get signed business public key from WhatsApp Cloud API.
     *
     * GET /{phone_number_id}/whatsapp_business_encryption
     *
     * @param int|null $phoneNumberId Optional specific phone number ID
     * @return array|null
     */
    public function getPublicKey(?int $phoneNumberId = null): ?array
    {
        $phoneId = $phoneNumberId ?? $this->phoneNumberId;

        try {
            $response = $this->httpClient->get(
                "{$this->graphApiUrl}/{$this->apiVersion}/{$phoneId}/whatsapp_business_encryption",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$this->accessToken}",
                    ],
                ]
            );

            $result = json_decode($response->getBody()->getContents(), true);

            Log::info('WhatsApp Flow public key retrieved', [
                'phone_number_id' => $phoneId,
            ]);

            return $result;
        } catch (GuzzleException $e) {
            Log::error('Failed to get WhatsApp Flow public key', [
                'phone_number_id' => $phoneId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generate a new 2048-bit RSA key pair.
     *
     * @param string $outputDir Directory to save keys
     * @param string|null $passphrase Optional passphrase for private key
     * @return array{private: string, public: string} Paths to generated keys
     * @throws \Exception
     */
    public function generateKeyPair(string $outputDir, ?string $passphrase = null): array
    {
        // Ensure output directory exists
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $privateKeyPath = $outputDir . DIRECTORY_SEPARATOR . 'whatsapp_flows_private.pem';
        $publicKeyPath = $outputDir . DIRECTORY_SEPARATOR . 'whatsapp_flows_public.pem';

        // Generate 2048-bit RSA key pair
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $keyResource = openssl_pkey_new($config);

        if ($keyResource === false) {
            throw new \Exception('Failed to generate RSA key pair: ' . openssl_error_string());
        }

        // Export private key
        if ($passphrase) {
            openssl_pkey_export($keyResource, $privateKeyPem, $passphrase);
        } else {
            openssl_pkey_export($keyResource, $privateKeyPem);
        }

        // Get public key
        $keyDetails = openssl_pkey_get_details($keyResource);
        $publicKeyPem = $keyDetails['key'];

        // Save keys to files
        file_put_contents($privateKeyPath, $privateKeyPem);
        file_put_contents($publicKeyPath, $publicKeyPem);

        // Set restrictive permissions on private key
        chmod($privateKeyPath, 0600);
        chmod($publicKeyPath, 0644);

        Log::info('WhatsApp Flow RSA key pair generated', [
            'private_key_path' => $privateKeyPath,
            'public_key_path' => $publicKeyPath,
        ]);

        return [
            'private' => $privateKeyPath,
            'public' => $publicKeyPath,
        ];
    }

    /**
     * Validate request signature from WhatsApp.
     *
     * @param string $payload Raw request body
     * @param string $signature Value from X-Hub-Signature-256 header
     * @return bool
     */
    public function validateSignature(string $payload, string $signature): bool
    {
        $appSecret = config('whatsapp.app_secret');

        if (!$appSecret) {
            Log::warning('WhatsApp app secret not configured for Flow signature validation');
            return true; // Skip validation if not configured
        }

        // Remove 'sha256=' prefix
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

        $isValid = hash_equals($expectedSignature, $signature);

        if (!$isValid) {
            Log::warning('WhatsApp Flow signature validation failed', [
                'expected' => $expectedSignature,
                'received' => $signature,
            ]);
        }

        return $isValid;
    }

    /**
     * Load the private key from file.
     *
     * @return string
     * @throws \Exception
     */
    protected function loadPrivateKey(): string
    {
        if (!$this->privateKeyPath || !file_exists($this->privateKeyPath)) {
            throw new \Exception(
                "WhatsApp Flow private key not found. " .
                    "Please generate keys using: php artisan whatsapp:generate-flow-keys"
            );
        }

        return file_get_contents($this->privateKeyPath);
    }

    /**
     * Load the public key from file.
     *
     * @param string|null $path Optional custom path
     * @return string
     * @throws \Exception
     */
    public function loadPublicKey(?string $path = null): string
    {
        $publicKeyPath = $path ?? str_replace('private.pem', 'public.pem', $this->privateKeyPath);

        if (!file_exists($publicKeyPath)) {
            throw new \Exception(
                "WhatsApp Flow public key not found at: {$publicKeyPath}"
            );
        }

        return file_get_contents($publicKeyPath);
    }
}

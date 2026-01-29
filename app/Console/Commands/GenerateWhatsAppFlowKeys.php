<?php

namespace App\Console\Commands;

use App\Services\WhatsAppFlowEncryptionService;
use Illuminate\Console\Command;

class GenerateWhatsAppFlowKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:generate-flow-keys 
                            {--output= : Directory to save keys (default: storage/keys)}
                            {--passphrase= : Optional passphrase to encrypt private key}
                            {--upload : Upload public key to WhatsApp after generation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new 2048-bit RSA key pair for WhatsApp Flows encryption';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $outputDir = $this->option('output') ?: storage_path('keys');
        $passphrase = $this->option('passphrase');
        $shouldUpload = $this->option('upload');

        $this->info('Generating 2048-bit RSA key pair for WhatsApp Flows...');
        $this->newLine();

        try {
            // Generate key pair directly (no database needed)
            $paths = $this->generateKeyPair($outputDir, $passphrase);

            $this->info('✓ Keys generated successfully!');
            $this->newLine();
            $this->table(
                ['Key Type', 'Path'],
                [
                    ['Private Key', $paths['private']],
                    ['Public Key', $paths['public']],
                ]
            );
            $this->newLine();

            // Show the public key content for reference
            $publicKey = file_get_contents($paths['public']);
            $this->info('Public Key (copy this to WhatsApp Business Manager if needed):');
            $this->line($publicKey);
            $this->newLine();

            // Show configuration instructions
            $this->info('Add these to your .env file:');
            $this->line("WHATSAPP_FLOWS_ENABLED=true");
            $this->line("WHATSAPP_FLOWS_PRIVATE_KEY_PATH={$paths['private']}");
            if ($passphrase) {
                $this->line("WHATSAPP_FLOWS_PRIVATE_KEY_PASSPHRASE={$passphrase}");
            }
            $this->newLine();

            // Upload if requested (only now we need database/API access)
            if ($shouldUpload) {
                $this->info('Uploading public key to WhatsApp...');

                $encryptionService = app(WhatsAppFlowEncryptionService::class);
                $success = $encryptionService->uploadPublicKey($publicKey);

                if ($success) {
                    $this->info('✓ Public key uploaded successfully!');
                } else {
                    $this->error('✗ Failed to upload public key. Check logs for details.');
                    $this->line('You can manually upload the public key via the WhatsApp Business Manager');
                    $this->line('or run: php artisan whatsapp:upload-flow-key');
                    return Command::FAILURE;
                }
            } else {
                $this->warn('Remember to upload the public key to WhatsApp!');
                $this->line('Run with --upload flag to upload automatically:');
                $this->line('php artisan whatsapp:generate-flow-keys --upload');
                $this->newLine();
                $this->line('Or upload manually via:');
                $this->line('POST /{phone_number_id}/whatsapp_business_encryption');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to generate keys: ' . $e->getMessage());
            return Command::FAILURE;
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
    protected function generateKeyPair(string $outputDir, ?string $passphrase = null): array
    {
        // Ensure output directory exists
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $privateKeyPath = $outputDir . DIRECTORY_SEPARATOR . 'whatsapp_flows_private.pem';
        $publicKeyPath = $outputDir . DIRECTORY_SEPARATOR . 'whatsapp_flows_public.pem';

        // Generate 2048-bit RSA key pair
        // Note: On Windows (XAMPP), we need to specify the openssl.cnf path
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        // Try to find OpenSSL config on Windows
        $opensslCnfPath = getenv('OPENSSL_CONF');
        if (!$opensslCnfPath && PHP_OS_FAMILY === 'Windows') {
            // Common XAMPP locations
            $possiblePaths = [
                'C:\\xampp\\apache\\conf\\openssl.cnf',
                'C:\\xampp\\php\\extras\\openssl\\openssl.cnf',
                'C:\\Program Files\\OpenSSL\\openssl.cnf',
                'C:\\OpenSSL-Win64\\bin\\openssl.cfg',
            ];
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $config['config'] = $path;
                    break;
                }
            }
        } elseif ($opensslCnfPath) {
            $config['config'] = $opensslCnfPath;
        }

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

        // Set restrictive permissions on private key (cross-platform compatible)
        @chmod($privateKeyPath, 0600);
        @chmod($publicKeyPath, 0644);

        return [
            'private' => $privateKeyPath,
            'public' => $publicKeyPath,
        ];
    }
}

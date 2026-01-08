<?php

namespace App\Exceptions;

use Exception;

class MissingFilesException extends Exception
{
    protected array $missingFiles;

    public function __construct(string $message, array $missingFiles)
    {
        parent::__construct($message);
        $this->missingFiles = $missingFiles;
    }

    public function getMissingFiles(): array
    {
        return $this->missingFiles;
    }

    public function render($request)
    {
        return response()->json([
            'status' => 'error',
            'message' => $this->getMessage(),
            'missing_files' => $this->missingFiles,
        ], 422);
    }
}

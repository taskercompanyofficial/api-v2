<?php

namespace App\Logging;

use App\Models\ApplicationLog;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Illuminate\Support\Facades\Request;

class DatabaseLogHandler extends AbstractProcessingHandler
{
    /**
     * Write the log record to the database.
     */
    protected function write(LogRecord $record): void
    {
        try {
            // Extract exception details if present
            $exceptionClass = null;
            $exceptionMessage = null;
            $stackTrace = null;
            $file = null;
            $line = null;

            if (isset($record->context['exception']) && $record->context['exception'] instanceof \Throwable) {
                $exception = $record->context['exception'];
                $exceptionClass = get_class($exception);
                $exceptionMessage = $exception->getMessage();
                $stackTrace = $exception->getTraceAsString();
                $file = $exception->getFile();
                $line = $exception->getLine();
            }

            // Extract request ID from context if available
            $requestId = $record->context['request_id'] ?? null;

            // Remove exception from context to avoid duplication
            $context = $record->context;
            unset($context['exception']);

            ApplicationLog::create([
                'request_id' => $requestId,
                'level' => strtolower($record->level->getName()),
                'channel' => $record->channel,
                'message' => $record->message,
                'context' => !empty($context) ? $context : null,
                'exception_class' => $exceptionClass,
                'exception_message' => $exceptionMessage,
                'stack_trace' => $stackTrace,
                'file' => $file,
                'line' => $line,
                'user_id' => auth()->id(),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'url' => Request::fullUrl(),
                'method' => Request::method(),
            ]);
        } catch (\Exception $e) {
            // Prevent infinite loop if database logging fails
            // Log to file instead
            error_log('Failed to write log to database: ' . $e->getMessage());
        }
    }
}

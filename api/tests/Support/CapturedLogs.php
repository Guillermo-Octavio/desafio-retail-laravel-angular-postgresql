<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\Log;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Monolog\Processor\PsrLogMessageProcessor;

/**
 * Sends the application logs to an in-memory channel with the same JSON formatter as stderr.
 */
final class CapturedLogs
{
    private function __construct(private readonly TestHandler $handler) {}

    public static function install(): self
    {
        config([
            'logging.channels.captured' => [
                'driver' => 'monolog',
                'handler' => TestHandler::class,
                'formatter' => JsonFormatter::class,
                'processors' => [PsrLogMessageProcessor::class],
            ],
            'logging.default' => 'captured',
        ]);
        Log::forgetChannel('captured');

        /** @var TestHandler $handler */
        $handler = Log::channel('captured')->getLogger()->getHandlers()[0];

        return new self($handler);
    }

    /**
     * Every line as the JSON object written to the output.
     *
     * @return list<array<string, mixed>>
     */
    public function lines(): array
    {
        return array_map(
            fn (LogRecord $record) => json_decode((string) $record->formatted, true, flags: JSON_THROW_ON_ERROR),
            $this->handler->getRecords(),
        );
    }

    /**
     * @return list<string>
     */
    public function messages(): array
    {
        return array_column($this->lines(), 'message');
    }

    /**
     * @return array<string, mixed>
     */
    public function first(string $message): array
    {
        foreach ($this->lines() as $line) {
            if ($line['message'] === $message) {
                return $line;
            }
        }

        throw new \RuntimeException("No log line with the message {$message}.");
    }

    public function raw(): string
    {
        return implode('', array_map(fn (LogRecord $record) => (string) $record->formatted, $this->handler->getRecords()));
    }
}

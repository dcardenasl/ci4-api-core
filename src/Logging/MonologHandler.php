<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Logging;

use CodeIgniter\Log\Handlers\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Sentry\Monolog\Handler as SentryHandler;

class MonologHandler implements HandlerInterface
{
    protected MonologLogger $logger;

    /**
     * @var array<int, string>
     */
    protected array $handles = [];

    /**
     * Constructor
     *
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->handles = $config['handles'] ?? [];

        // Create Monolog logger
        $this->logger = new MonologLogger('ci4-api');

        // Audit B10.1 (2026-05-07): tag every log record with the current
        // request's correlation ID so cross-service joins are trivial.
        // The processor reads from RequestIdHolder, which the
        // CorrelationIdFilter populates in `before()`.
        $this->logger->pushProcessor(static function (\Monolog\LogRecord $record): \Monolog\LogRecord {
            $requestId = \dcardenasl\Ci4ApiCore\Http\RequestIdHolder::get();
            if ($requestId === null) {
                return $record;
            }

            return $record->with(extra: array_merge($record->extra, ['request_id' => $requestId]));
        });

        // Determine log format
        $logFormat = env('LOG_FORMAT', 'json');

        if ($logFormat === 'json') {
            $formatter = new JsonFormatter();
        } else {
            $formatter = new \Monolog\Formatter\LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'Y-m-d H:i:s'
            );
        }

        // Add file handler
        $logPath = WRITEPATH . 'logs/monolog-' . date('Y-m-d') . '.log';
        $fileHandler = new StreamHandler($logPath, MonologLogger::DEBUG);
        $fileHandler->setFormatter($formatter);
        $this->logger->pushHandler($fileHandler);

        // Add Sentry handler if configured
        $sentryDsn = env('SENTRY_DSN', '');
        if (! empty($sentryDsn)) {
            try {
                \Sentry\init([
                    'dsn' => $sentryDsn,
                    'environment' => env('SENTRY_ENVIRONMENT', 'production'),
                    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.2),
                ]);

                $sentryHandler = new SentryHandler(
                    \Sentry\SentrySdk::getCurrentHub(),
                    MonologLogger::ERROR
                );
                $this->logger->pushHandler($sentryHandler);
            } catch (\Throwable $e) {
                // Sentry initialization failed, continue without it
                error_log('Sentry initialization failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Handle log message
     *
     * @param string $level
     * @param string $message
     * @return bool
     */
    public function handle($level, $message): bool
    {
        // Convert CI4 level to Monolog level
        /** @var \Monolog\Level $monologLevel */
        $monologLevel = $this->mapLevel((string) $level);

        // Extract context from message if present
        $context = [];

        // Log to Monolog
        $this->logger->log($monologLevel, $message, $context);

        return true;
    }

    /**
     * Can this handler handle this log level?
     *
     * @param string $level
     * @return bool
     */
    public function canHandle($level): bool
    {
        return in_array($level, $this->handles, true);
    }

    /**
     * Set handles
     *
     * @param array<string> $handles
     * @return HandlerInterface
     */
    public function setHandles(array $handles = []): HandlerInterface
    {
        $this->handles = $handles;

        return $this;
    }

    /**
     * Set date format (required by interface, not used in Monolog)
     *
     * @param string $dateFormat
     * @return HandlerInterface
     */
    public function setDateFormat(string $dateFormat = 'Y-m-d H:i:s'): HandlerInterface
    {
        // Monolog handles its own date formatting
        return $this;
    }

    /**
     * Map CodeIgniter log level to Monolog level
     *
     * @param string $level
     * @return \Monolog\Level
     */
    protected function mapLevel(string $level): \Monolog\Level
    {
        return match (strtolower($level)) {
            'emergency' => \Monolog\Level::Emergency,
            'alert'     => \Monolog\Level::Alert,
            'critical'  => \Monolog\Level::Critical,
            'error'     => \Monolog\Level::Error,
            'warning'   => \Monolog\Level::Warning,
            'notice'    => \Monolog\Level::Notice,
            'debug'     => \Monolog\Level::Debug,
            default     => \Monolog\Level::Info,
        };
    }
}

<?php

namespace dcardenasl\Ci4ApiCore\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

class JsonFormatter implements FormatterInterface
{
    /**
     * Format a log record as JSON
     *
     * @param LogRecord $record
     * @return string
     */
    public function format(LogRecord $record): string
    {
        $data = [
            'timestamp' => $record->datetime->format('Y-m-d H:i:s'),
            'level' => $record->level->getName(),
            'message' => $record->message,
            'context' => $record->context,
            'extra' => $record->extra,
        ];

        // Add trace ID if available
        if (isset($record->extra['trace_id'])) {
            $data['trace_id'] = $record->extra['trace_id'];
        }

        // Add request info if available
        if (isset($record->context['request'])) {
            $data['request'] = $record->context['request'];
        }

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * Format a batch of log records
     *
     * @param array<LogRecord> $records
     * @return string
     */
    public function formatBatch(array $records): string
    {
        $output = '';

        foreach ($records as $record) {
            $output .= $this->format($record);
        }

        return $output;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration\Logging;

use dcardenasl\Ci4ApiCore\Logging\JsonFormatter;
use Monolog\Formatter\FormatterInterface;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class JsonFormatterTest extends TestCase
{
    private JsonFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new JsonFormatter();
    }

    public function testFormatProducesValidJsonWithRequiredKeys(): void
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Hello world',
            context: [],
            extra: [],
        );

        $output = $this->formatter->format($record);

        $decoded = json_decode(trim($output), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('timestamp', $decoded);
        $this->assertArrayHasKey('level', $decoded);
        $this->assertArrayHasKey('message', $decoded);
        $this->assertSame('Hello world', $decoded['message']);
        $this->assertSame('INFO', $decoded['level']);
    }

    public function testFormatBatchProducesOneLinePerRecord(): void
    {
        $make = static fn (string $msg) => new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Debug,
            message: $msg,
            context: [],
            extra: [],
        );

        $output = $this->formatter->formatBatch([$make('a'), $make('b'), $make('c')]);

        $lines = array_filter(explode("\n", trim($output)));
        $this->assertCount(3, $lines);
    }

    public function testImplementsMonologFormatterInterface(): void
    {
        $this->assertInstanceOf(FormatterInterface::class, $this->formatter);
    }
}

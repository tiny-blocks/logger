<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

use Psr\Log\LoggerTrait;
use Stringable;
use TinyBlocks\Logger\Exceptions\UnknownLogLevel;
use TinyBlocks\Logger\Internal\Metrics\MetricWriter;
use TinyBlocks\Logger\Internal\Redactor\Redactions;
use TinyBlocks\Logger\Internal\Stream\LogStream;
use TinyBlocks\Logger\Metrics\Metric;
use TinyBlocks\Logger\Metrics\MetricFormat;

/**
 * A structured logger that also publishes metrics, on the same stream.
 *
 * <p>Two kinds of record travel here, and they never share a line. An entry is narrative and carries
 * a severity; a metric is a measurement and carries none, so a threshold raised to quiet the logs
 * does not stop a series. What the logger knows about itself rides on both, which is what lets a
 * series be traced back to the surrounding entries.</p>
 * <p>Build a {@see StreamLogger} instead when nothing is measured. This type exists so that
 * having metrics is a choice made once, where the logger is assembled, and not an argument repeated
 * at every call.</p>
 */
final readonly class TelemetryLogger implements Logger
{
    use LoggerTrait;

    private function __construct(
        private StreamLogger $logger,
        private MetricWriter $writer,
        private ?Correlation $correlation
    ) {
    }

    /**
     * Creates a TelemetryLogger from what its builder describes.
     *
     * @param TelemetryLoggerBuilder $builder The metric format, plus everything the log side takes.
     * @return TelemetryLogger The created logger instance.
     */
    public static function from(TelemetryLoggerBuilder $builder): TelemetryLogger
    {
        return new TelemetryLogger(
            logger: $builder->logger->build(),
            writer: new MetricWriter(
                format: $builder->format,
                stream: LogStream::from(resource: $builder->logger->stream),
                component: $builder->logger->component,
                redactions: Redactions::createFrom(elements: $builder->logger->redactions)
            ),
            correlation: $builder->logger->correlation
        );
    }

    /**
     * Creates a TelemetryLoggerBuilder around the format that renders every metric it writes.
     *
     * <p>The format is taken here rather than at build time because a logger of this kind without one
     * has nothing to measure with, and an object that cannot be valid should not be reachable.</p>
     *
     * @param MetricFormat $format The format rendering a metric for the backend that reads it.
     * @return TelemetryLoggerBuilder A new builder for configuring a TelemetryLogger.
     */
    public static function builder(MetricFormat $format): TelemetryLoggerBuilder
    {
        return TelemetryLoggerBuilder::from(format: $format);
    }

    /**
     * Writes a log entry when its severity reaches the configured minimum level.
     *
     * @param mixed $level The severity, as received by the PSR-3 contract.
     * @param string|Stringable $message The message key identifying the event.
     * @param array<string, mixed> $context The context data carried by the entry.
     * @throws UnknownLogLevel If the level is outside the supported PSR-3 set.
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }

    /**
     * Writes a metric, in the shape the configured format renders it.
     *
     * @param Metric $metric The metric to write.
     */
    public function metric(Metric $metric): void
    {
        $this->writer->write(metric: $metric, correlation: $this->correlation);
    }

    public function withCorrelation(Correlation $correlation): TelemetryLogger
    {
        return new TelemetryLogger(
            logger: $this->logger->withCorrelation(correlation: $correlation),
            writer: $this->writer,
            correlation: $correlation
        );
    }
}

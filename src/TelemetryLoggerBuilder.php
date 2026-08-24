<?php

declare(strict_types=1);

namespace TinyBlocks\Logger;

use TinyBlocks\Logger\Metrics\MetricFormat;
use TinyBlocks\Logger\Redactions\Redaction;

/**
 * Fluent description of a TelemetryLogger: a metric format, plus everything a StreamLogger takes.
 *
 * <p>What describes the log side is a {@see StreamLoggerBuilder}, so the two loggers are configured
 * by the same vocabulary and the metric format is the only thing this one adds.</p>
 */
final readonly class TelemetryLoggerBuilder
{
    private function __construct(public MetricFormat $format, public StreamLoggerBuilder $logger)
    {
    }

    /**
     * Creates a TelemetryLoggerBuilder around the format that renders every metric it writes.
     *
     * @param MetricFormat $format The format rendering a metric for the backend that reads it.
     * @return TelemetryLoggerBuilder A builder writing to standard error, with no redaction and no threshold.
     */
    public static function from(MetricFormat $format): TelemetryLoggerBuilder
    {
        return new TelemetryLoggerBuilder(format: $format, logger: StreamLoggerBuilder::default());
    }

    /**
     * Builds a TelemetryLogger from what the builder describes.
     *
     * @return TelemetryLogger The configured logger instance.
     */
    public function build(): TelemetryLogger
    {
        return TelemetryLogger::from(builder: $this);
    }

    /**
     * Returns a copy of the builder with the stream replaced.
     *
     * @param mixed $stream The stream both records are written to.
     * @return TelemetryLoggerBuilder A copy of the builder with the stream set.
     */
    public function withStream(mixed $stream): TelemetryLoggerBuilder
    {
        return new TelemetryLoggerBuilder(
            format: $this->format,
            logger: $this->logger->withStream(stream: $stream)
        );
    }

    /**
     * Returns a copy of the builder with the format template replaced.
     *
     * @param string $template The format template applied to every entry.
     * @return TelemetryLoggerBuilder A copy of the builder with the template set.
     */
    public function withTemplate(string $template): TelemetryLoggerBuilder
    {
        return new TelemetryLoggerBuilder(
            format: $this->format,
            logger: $this->logger->withTemplate(template: $template)
        );
    }

    /**
     * Returns a copy of the builder with the component replaced.
     *
     * @param string $component The component name identifying the source of both records.
     * @return TelemetryLoggerBuilder A copy of the builder with the component set.
     */
    public function withComponent(string $component): TelemetryLoggerBuilder
    {
        return new TelemetryLoggerBuilder(
            format: $this->format,
            logger: $this->logger->withComponent(component: $component)
        );
    }

    /**
     * Returns a copy of the builder with the redactions added to the ones already described.
     *
     * @param Redaction ...$redactions The redaction strategies applied before writing.
     * @return TelemetryLoggerBuilder A copy of the builder carrying the previous and the new redactions.
     */
    public function withRedactions(Redaction ...$redactions): TelemetryLoggerBuilder
    {
        return new TelemetryLoggerBuilder(
            format: $this->format,
            logger: $this->logger->withRedactions(...$redactions)
        );
    }

    /**
     * Returns a copy of the builder with the correlation replaced.
     *
     * @param Correlation $correlation The correlation shared across log entries and metrics.
     * @return TelemetryLoggerBuilder A copy of the builder with the correlation set.
     */
    public function withCorrelation(Correlation $correlation): TelemetryLoggerBuilder
    {
        return new TelemetryLoggerBuilder(
            format: $this->format,
            logger: $this->logger->withCorrelation(correlation: $correlation)
        );
    }

    /**
     * Returns a copy of the builder with the minimum level replaced.
     *
     * @param LogLevel $minimumLevel The lowest severity that is written.
     * @return TelemetryLoggerBuilder A copy of the builder with the minimum level set.
     */
    public function withMinimumLevel(LogLevel $minimumLevel): TelemetryLoggerBuilder
    {
        return new TelemetryLoggerBuilder(
            format: $this->format,
            logger: $this->logger->withMinimumLevel(minimumLevel: $minimumLevel)
        );
    }
}

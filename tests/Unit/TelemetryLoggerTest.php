<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Logger\Unit;

use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\Logger\Models\EmbeddedMetricPayload;
use TinyBlocks\Logger\Correlation;
use TinyBlocks\Logger\Exceptions\RedactedDimension;
use TinyBlocks\Logger\Exceptions\UnknownLogLevel;
use TinyBlocks\Logger\LogLevel;
use TinyBlocks\Logger\Metrics\CloudWatch\EmbeddedMetricFormat;
use TinyBlocks\Logger\Metrics\Metric;
use TinyBlocks\Logger\Metrics\MetricUnit;
use TinyBlocks\Logger\Redactions\GenericRedaction;
use TinyBlocks\Logger\Redactions\Mask;
use TinyBlocks\Logger\Redactions\SecretRedaction;
use TinyBlocks\Logger\TelemetryLogger;

final class TelemetryLoggerTest extends TestCase
{
    private InMemoryStream $logStream;

    protected function setUp(): void
    {
        $this->logStream = InMemoryStream::create();
    }

    protected function tearDown(): void
    {
        $this->logStream->close();
    }

    public function testMetricWhenWrittenThenTheLineIsTheRecordAlone(): void
    {
        /** @Given a telemetry logger writing to a stream */
        $logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'schedule')
            ->build();

        /** @When writing a measurement */
        $logger->metric(metric: Metric::of(name: 'AppointmentNoShow', namespace: 'Acme/Schedule'));

        /** @Then the line is a JSON object and nothing else, which is what the agent parses */
        self::assertSame(
            1,
            json_decode(trim($this->logStream->contents()), true)['AppointmentNoShow']
        );
    }

    public function testLogWhenLevelIsUnknownThenThrowsUnknownLogLevel(): void
    {
        /** @Given a telemetry logger */
        $logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
            ->withStream(stream: $this->logStream->handle())
            ->build();

        /** @Then logging at an unsupported level raises an unknown log level error */
        $this->expectException(UnknownLogLevel::class);

        /** @When logging at a level outside the supported set */
        $logger->log('not-a-level', 'some.key');
    }

    public function testMetricWhenComponentGivenThenTheLineCarriesItAsAField(): void
    {
        /** @Given a telemetry logger that knows which component it speaks for */
        $logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'payment')
            ->build();

        /** @When writing a measurement */
        $logger->metric(metric: Metric::of(name: 'ChargeAccepted', namespace: 'Acme/Payment'));

        /** @Then the component travels on the metric, so a series can be traced to who emitted it */
        self::assertSame('payment', json_decode(trim($this->logStream->contents()), true)['component']);
    }

    public function testBuilderWhenRedactionsGivenInTwoCallsThenBothReachTheMetric(): void
    {
        /** @Given a telemetry logger told about one redaction */
        $logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'identity')
            ->withRedactions(SecretRedaction::default())
            ->withRedactions(GenericRedaction::removing(fields: ['stack_trace']))
            ->build();

        /** @And a measurement covered by both */
        $metric = Metric::of(name: 'SignInFailed', namespace: 'Acme/Identity')
            ->withField(name: 'password', value: 'super-secret')
            ->withField(name: 'stack_trace', value: '#0 /var/www/html/src/SignIn.php(7)');

        /** @When writing it */
        $logger->metric(metric: $metric);

        /** @Then the later list was added to the earlier one, and not put in its place */
        $record = json_decode(trim($this->logStream->contents()), true);

        self::assertSame('********', $record['password']);
        self::assertArrayNotHasKey('stack_trace', $record);
    }

    public function testMetricWhenNotCorrelatedThenTheLineCarriesAnEmptyIdentifier(): void
    {
        /** @Given a telemetry logger with no correlation bound */
        $logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'schedule')
            ->build();

        /** @When writing a measurement */
        $logger->metric(metric: Metric::of(name: 'AppointmentNoShow', namespace: 'Acme/Schedule'));

        /** @Then the field is present and empty, so the shape of the record never varies */
        self::assertSame('', json_decode(trim($this->logStream->contents()), true)['correlation_id']);
    }

    public function testWithCorrelationWhenDerivedThenTheOriginalLoggerStaysUnbound(): void
    {
        /** @Given a telemetry logger with no correlation bound */
        $logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'schedule')
            ->build();

        /** @And a correlated logger derived from it */
        $correlated = $logger->withCorrelation(correlation: Correlation::from(correlationId: 'req-derived'));

        /** @When writing a measurement through the original */
        $logger->metric(metric: Metric::of(name: 'AppointmentCreated', namespace: 'Acme/Schedule'));

        /** @Then the derived logger is another instance and the original was not bound by it */
        self::assertNotSame($logger, $correlated);
        self::assertSame('', json_decode(trim($this->logStream->contents()), true)['correlation_id']);
    }

    public function testMetricWhenTheLoggerIsCorrelatedThenTheLineCarriesTheIdentifier(): void
    {
        /** @Given a telemetry logger built around a correlation */
        $logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'schedule')
            ->withCorrelation(correlation: Correlation::from(correlationId: 'req-abc-123'))
            ->build();

        /** @When writing a measurement */
        $logger->metric(metric: Metric::of(name: 'AppointmentNoShow', namespace: 'Acme/Schedule'));

        /** @Then the identifier travels on the metric, which is what stitches it to the entries around it */
        self::assertSame('req-abc-123', json_decode(trim($this->logStream->contents()), true)['correlation_id']);
    }

    public function testMetricAndEntryWhenBothWrittenThenNeitherSharesALineWithTheOther(): void
    {
        /** @Given a telemetry logger writing to a stream */
        $logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'schedule')
            ->build();

        /** @And an entry already written through it */
        $logger->info(message: 'appointment.created', context: ['appointment_id' => 42]);

        /** @When writing a measurement after it */
        $logger->metric(metric: Metric::of(name: 'AppointmentCreated', namespace: 'Acme/Schedule'));

        /** @Then the entry keeps the log shape and the metric keeps the record shape, on two lines */
        $lines = explode(PHP_EOL, trim($this->logStream->contents()));

        self::assertCount(2, $lines);
        self::assertStringContainsString('key=appointment.created', $lines[0]);
        self::assertStringContainsString('data={"appointment_id":42}', $lines[0]);
        self::assertSame(1, json_decode($lines[1], true)['AppointmentCreated']);
    }

    public function testMetricWhenAFieldIsCoveredByARedactionThenTheLineCarriesItMasked(): void
    {
        /** @Given a telemetry logger that masks passwords */
        $logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'identity')
            ->withRedactions(SecretRedaction::default())
            ->build();

        /** @And a measurement carrying a field the redaction covers */
        $metric = Metric::of(name: 'SignInFailed', namespace: 'Acme/Identity')
            ->withField(name: 'password', value: 'super-secret');

        /** @When writing it */
        $logger->metric(metric: $metric);

        /** @Then the metric line carries the masked value, as the log line would */
        self::assertSame('********', json_decode(trim($this->logStream->contents()), true)['password']);
    }

    public function testBuilderWhenTemplateGivenThenTheEntryFollowsItAndTheMetricDoesNot(): void
    {
        /** @Given a telemetry logger built with a template of its own */
        $logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
            ->withStream(stream: $this->logStream->handle())
            ->withTemplate(template: "%4\$s|%5\$s|%6\$s\n")
            ->withComponent(component: 'schedule')
            ->build();

        /** @And an entry already written through it */
        $logger->warning(message: 'appointment.late', context: ['minutes' => 15]);

        /** @When writing a measurement after it */
        $logger->metric(metric: Metric::of(name: 'AppointmentLate', namespace: 'Acme/Schedule'));

        /** @Then the entry follows the template and the metric keeps the shape its format renders */
        $lines = explode(PHP_EOL, trim($this->logStream->contents()));

        self::assertSame('WARNING|appointment.late|{"minutes":15}', $lines[0]);
        self::assertSame(1, json_decode($lines[1], true)['AppointmentLate']);
    }

    public function testMetricWhenADimensionIsNotCoveredByARedactionThenTheLineCarriesIt(): void
    {
        /** @Given a telemetry logger that masks a field name */
        $logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'subscription')
            ->withRedactions(GenericRedaction::masking(mask: Mask::proportional(), fields: ['document']))
            ->build();

        /** @And a measurement broken down by a dimension no redaction covers */
        $metric = Metric::of(name: 'SubscriptionRenewed', namespace: 'Acme/Subscription')
            ->withDimension(name: 'subscription_type', value: 'saas');

        /** @When writing it */
        $logger->metric(metric: $metric);

        /** @Then nothing is refused, and the dimension is declared and repeated at the root */
        $record = json_decode(trim($this->logStream->contents()), true);

        self::assertSame('saas', $record['subscription_type']);
        self::assertSame([['subscription_type']], EmbeddedMetricPayload::from(payload: $record)->dimensions());
    }

    public function testMetricWhenAFieldIsRemovedByARedactionThenItLeavesTheLineEntirely(): void
    {
        /** @Given a telemetry logger that drops a field instead of masking it */
        $logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'payment')
            ->withRedactions(GenericRedaction::removing(fields: ['stack_trace']))
            ->build();

        /** @And a measurement carrying the field that is dropped and one that is kept */
        $metric = Metric::of(name: 'ChargeFailed', namespace: 'Acme/Payment')
            ->withField(name: 'stack_trace', value: '#0 /var/www/html/src/Charge.php(42)')
            ->withField(name: 'gateway', value: 'acquirer-a');

        /** @When writing it */
        $logger->metric(metric: $metric);

        /** @Then the dropped field is absent from the record and the other one survives */
        $record = json_decode(trim($this->logStream->contents()), true);

        self::assertArrayNotHasKey('stack_trace', $record);
        self::assertSame('acquirer-a', $record['gateway']);
    }

    public function testBuilderWhenDerivedThenTheEarlierBuilderStillBuildsWhatItDescribed(): void
    {
        /** @Given a builder describing a component */
        $builder = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'schedule');

        /** @And a copy of it describing another component */
        $derived = $builder->withComponent(component: 'payment');

        /** @When building through the earlier builder */
        $builder->build()->metric(metric: Metric::of(name: 'AppointmentNoShow', namespace: 'Acme/Schedule'));

        /** @Then the copy is another builder and the earlier one was not changed by it */
        self::assertNotSame($builder, $derived);
        self::assertSame('schedule', json_decode(trim($this->logStream->contents()), true)['component']);
    }

    public function testMetricWhenMeasuredInAUnitThenTheLineDeclaresItAndKeepsThePrecision(): void
    {
        /** @Given a telemetry logger writing to a stream */
        $logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'schedule')
            ->build();

        /** @And a measurement that is a duration and not a count */
        $metric = Metric::of(name: 'AppointmentDuration', namespace: 'Acme/Schedule')
            ->withUnit(unit: MetricUnit::SECONDS)
            ->withValue(value: 12.5);

        /** @When writing it */
        $logger->metric(metric: $metric);

        /** @Then the declaration carries the unit and the value keeps its precision */
        $record = json_decode(trim($this->logStream->contents()), true);

        self::assertSame(
            [['Name' => 'AppointmentDuration', 'Unit' => 'Seconds']],
            EmbeddedMetricPayload::from(payload: $record)->metrics()
        );
        self::assertSame(12.5, $record['AppointmentDuration']);
    }

    public function testMetricWhenEntriesAreSilencedByTheThresholdThenTheSeriesStillTravels(): void
    {
        /** @Given a telemetry logger quiet enough to discard an informational entry */
        $logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'schedule')
            ->withMinimumLevel(minimumLevel: LogLevel::ERROR)
            ->build();

        /** @And an entry below the threshold, which is discarded */
        $logger->info(message: 'appointment.created');

        /** @When writing a measurement */
        $logger->metric(metric: Metric::of(name: 'AppointmentCreated', namespace: 'Acme/Schedule'));

        /** @Then only the metric reached the stream, because a measurement carries no severity */
        $lines = explode(PHP_EOL, trim($this->logStream->contents()));

        self::assertCount(1, $lines);
        self::assertSame(1, json_decode($lines[0], true)['AppointmentCreated']);
    }

    public function testWithCorrelationWhenDerivedThenTheEntryAndTheMetricCarryTheIdentifier(): void
    {
        /** @Given a telemetry logger with no correlation bound */
        $logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'schedule')
            ->build();

        /** @And a correlated logger derived from it */
        $correlated = $logger->withCorrelation(correlation: Correlation::from(correlationId: 'req-derived'));

        /** @And an entry already written through the derived logger */
        $correlated->info(message: 'appointment.created');

        /** @When writing a measurement through it */
        $correlated->metric(metric: Metric::of(name: 'AppointmentCreated', namespace: 'Acme/Schedule'));

        /** @Then both records carry the identifier the derived logger was bound to */
        $lines = explode(PHP_EOL, trim($this->logStream->contents()));

        self::assertStringContainsString('correlation_id=req-derived', $lines[0]);
        self::assertSame('req-derived', json_decode($lines[1], true)['correlation_id']);
    }

    public function testMetricWhenADimensionIsCoveredByARedactionThenRefusesAndNamesTheAlternative(): void
    {
        /** @Given a telemetry logger that masks a field name */
        $logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'identity')
            ->withRedactions(GenericRedaction::masking(mask: Mask::proportional(), fields: ['document']))
            ->build();

        /** @And a measurement broken down by a dimension carrying that same name */
        $metric = Metric::of(name: 'SignInFailed', namespace: 'Acme/Identity')
            ->withDimension(name: 'document', value: '12345678900');

        /** @Then the refusal names the dimension and points at the field */
        $this->expectException(RedactedDimension::class);
        $this->expectExceptionMessage(
            'The dimension <document> is covered by a redaction. A dimension value reaches the metric index of '
            . 'the backend, where no redaction and no log retention can reach it. Emit it as a field instead.'
        );

        /** @When writing it */
        $logger->metric(metric: $metric);
    }
}

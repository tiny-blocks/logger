<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Logger\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Test\TinyBlocks\Logger\Models\EmbeddedMetricPayload;
use TinyBlocks\Logger\Exceptions\UnboundedDimension;
use TinyBlocks\Logger\Metrics\CloudWatch\EmbeddedMetricFormat;
use TinyBlocks\Logger\Metrics\Metric;
use TinyBlocks\Logger\Metrics\MetricUnit;

final class EmbeddedMetricFormatTest extends TestCase
{
    public function testFormatWhenFieldCarriedThenCreatesNoSeries(): void
    {
        /** @Given a counter carrying granular attributes as fields */
        $metric = Metric::of(name: 'OfferExpired', namespace: 'Acme/Waitlist')
            ->withField(name: 'tenant_id', value: 'abc-123')
            ->withField(name: 'attempt', value: 2);

        /** @When rendering it */
        $payload = EmbeddedMetricFormat::default()->format(metric: $metric);

        /** @Then they are in the record and the dimension set stays empty */
        self::assertSame('abc-123', $payload['tenant_id']);
        self::assertSame(2, $payload['attempt']);
        self::assertSame([[]], EmbeddedMetricPayload::from(payload: $payload)->dimensions());
    }

    public function testFormatWhenTimestampRenderedThenIsInMilliseconds(): void
    {
        /** @Given a counter */
        $metric = Metric::of(name: 'AppointmentNoShow', namespace: 'Acme/Schedule');

        /** @When rendering it */
        $timestamp = EmbeddedMetricPayload::from(
            payload: EmbeddedMetricFormat::default()->format(metric: $metric)
        )->timestamp();

        /** @Then the timestamp is the current instant expressed in milliseconds */
        $secondsNow = time();
        $lowerBound = (($secondsNow - 1) * 1000);
        $upperBound = (($secondsNow + 2) * 1000);

        self::assertIsInt($timestamp);
        self::assertGreaterThanOrEqual($lowerBound, $timestamp);
        self::assertLessThan($upperBound, $timestamp);
    }

    #[DataProvider('unitProvider')]

    public function testFormatWhenUnitGivenThenSpellsItAsCloudWatchReadsIt(MetricUnit $unit, string $token): void
    {
        /** @Given a measurement in one of the units the neutral vocabulary carries */
        $metric = Metric::of(name: 'OfferResponseTime', namespace: 'Acme/Waitlist')
            ->withUnit(unit: $unit)
            ->withValue(value: 12.5);

        /** @When rendering it */
        $payload = EmbeddedMetricFormat::default()->format(metric: $metric);

        /** @Then the declaration carries the CloudWatch token, and the value keeps its precision */
        self::assertSame(
            [['Name' => 'OfferResponseTime', 'Unit' => $token]],
            EmbeddedMetricPayload::from(payload: $payload)->metrics()
        );
        self::assertSame(12.5, $payload['OfferResponseTime']);
    }

    public function testFormatWhenNoNameIsRefusedThenAnyDimensionIsAccepted(): void
    {
        /** @Given a format that was told to refuse nothing */
        $format = EmbeddedMetricFormat::default();

        /** @And a counter broken down by a name another consumer might refuse */
        $metric = Metric::of(name: 'OfferDeclined', namespace: 'Acme/Waitlist')
            ->withDimension(name: 'tenant_id', value: 'abc-123');

        /** @When rendering it */
        $payload = $format->format(metric: $metric);

        /** @Then nothing is refused, because which names are unbounded is not the library's to know */
        self::assertSame([['tenant_id']], EmbeddedMetricPayload::from(payload: $payload)->dimensions());
    }

    public function testFormatWhenNoDimensionThenDeclaresTheEmptyDimensionSet(): void
    {
        /** @Given a counter with no dimension and no field */
        $metric = Metric::of(name: 'OfferAccepted', namespace: 'Acme/Waitlist');

        /** @When rendering it */
        $payload = EmbeddedMetricFormat::default()->format(metric: $metric);

        /** @Then the metric declares one empty dimension set and counts one */
        $record = EmbeddedMetricPayload::from(payload: $payload);

        self::assertSame('Acme/Waitlist', $record->namespaced());
        self::assertSame([[]], $record->dimensions());
        self::assertSame([['Name' => 'OfferAccepted', 'Unit' => 'Count']], $record->metrics());
        self::assertSame(1, $payload['OfferAccepted']);
    }

    public function testFormatWhenDimensionDeclaredThenIsRepeatedAsAFieldOfTheRecord(): void
    {
        /** @Given a counter broken down by a bounded dimension */
        $metric = Metric::of(name: 'SubscriptionRenewed', namespace: 'Acme/Subscription')
            ->withDimension(name: 'subscription_type', value: 'saas');

        /** @When rendering it */
        $payload = EmbeddedMetricFormat::default()->format(metric: $metric);

        /** @Then the dimension is declared and repeated at the root, which is what EMF resolves */
        self::assertSame([['subscription_type']], EmbeddedMetricPayload::from(payload: $payload)->dimensions());
        self::assertSame('saas', $payload['subscription_type']);
    }

    public function testFormatWhenAFieldIsNamedAfterTheEnvelopeThenTheEnvelopeSurvives(): void
    {
        /** @Given a counter carrying a field whose name collides with the envelope key */
        $metric = Metric::of(name: 'OfferAccepted', namespace: 'Acme/Waitlist')
            ->withField(name: '_aws', value: 'oops');

        /** @When rendering it */
        $payload = EmbeddedMetricFormat::default()->format(metric: $metric);

        /** @Then the envelope wins, so the record is still a metric and not a plain log line */
        self::assertSame('Acme/Waitlist', EmbeddedMetricPayload::from(payload: $payload)->namespaced());
    }

    public function testWithUnboundedDimensionsWhenCalledTwiceThenTheEarlierNameSurvives(): void
    {
        /** @Given a format told about one name and then, in another call, about a different one */
        $format = EmbeddedMetricFormat::default()
            ->withUnboundedDimensions('user_id')
            ->withUnboundedDimensions('tenant_id');

        /** @And a counter broken down by the name of the first call */
        $metric = Metric::of(name: 'OfferDeclined', namespace: 'Acme/Waitlist')
            ->withDimension(name: 'user_id', value: 'abc-123');

        /** @Then the earlier list is added to, and not replaced by the later one */
        $this->expectException(UnboundedDimension::class);

        /** @When rendering it */
        $format->format(metric: $metric);
    }

    public function testWithUnboundedDimensionsWhenDerivedThenTheOriginalStillAcceptsTheName(): void
    {
        /** @Given a format that refuses nothing */
        $format = EmbeddedMetricFormat::default();

        /** @And a copy of it that refuses a name */
        $derived = $format->withUnboundedDimensions('tenant_id');

        /** @And a counter broken down by that name */
        $metric = Metric::of(name: 'OfferDeclined', namespace: 'Acme/Waitlist')
            ->withDimension(name: 'tenant_id', value: 'abc-123');

        /** @When rendering through the original */
        $payload = $format->format(metric: $metric);

        /** @Then the copy is another instance and the original was not mutated by it */
        self::assertNotSame($format, $derived);
        self::assertSame([['tenant_id']], EmbeddedMetricPayload::from(payload: $payload)->dimensions());
    }

    #[DataProvider('unboundedDimensionProvider')]

    public function testFormatWhenDimensionWasDeclaredUnboundedThenRefusesAndNamesTheAlternative(
        string $dimension
    ): void {
        /** @Given a format told which names the consumer treats as unbounded */
        $format = EmbeddedMetricFormat::default()->withUnboundedDimensions('user_id', 'tenant_id');

        /** @And a counter broken down by one of them */
        $metric = Metric::of(name: 'OfferDeclined', namespace: 'Acme/Waitlist')
            ->withDimension(name: $dimension, value: 'anything');

        /** @Then the refusal names the dimension and points at the field */
        $this->expectException(UnboundedDimension::class);
        $this->expectExceptionMessage(
            sprintf(
                'The dimension <%s> was declared unbounded. Emit it as a field of the same record instead.',
                $dimension
            )
        );

        /** @When rendering it */
        $format->format(metric: $metric);
    }

    public static function unitProvider(): iterable
    {
        $tokens = [
            'bit'     => 'Bits',
            '1'       => 'None',
            'By'      => 'Bytes',
            '{count}' => 'Count',
            '%'       => 'Percent',
            's'       => 'Seconds'
        ];

        foreach (MetricUnit::cases() as $unit) {
            yield $unit->value => ['unit' => $unit, 'token' => $tokens[$unit->value]];
        }
    }

    public static function unboundedDimensionProvider(): iterable
    {
        yield 'user identifier' => ['dimension' => 'user_id'];
        yield 'tenant identifier' => ['dimension' => 'tenant_id'];
    }
}

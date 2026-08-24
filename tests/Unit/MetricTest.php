<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Logger\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TinyBlocks\Logger\Exceptions\BlankMetricIdentifier;
use TinyBlocks\Logger\Exceptions\DuplicateMetricIdentifier;
use TinyBlocks\Logger\Metrics\Metric;
use TinyBlocks\Logger\Metrics\MetricUnit;

final class MetricTest extends TestCase
{
    public function testOfWhenCreatedThenCountsOneOccurrence(): void
    {
        /** @Given nothing but a name and a namespace */
        /** @When creating a metric */
        $metric = Metric::of(name: 'OfferAccepted', namespace: 'Acme/Waitlist');

        /** @Then it counts one, carries no dimension and no field */
        self::assertSame('OfferAccepted', $metric->name->value);
        self::assertSame(MetricUnit::COUNT, $metric->unit);
        self::assertSame(1, $metric->value);
        self::assertSame([], $metric->fields);
        self::assertSame('Acme/Waitlist', $metric->namespace->value);
        self::assertSame([], $metric->dimensions);
    }

    public function testWithFieldWhenTheNameIsBlankThenRefusesIt(): void
    {
        /** @Given a metric */
        $metric = Metric::of(name: 'OfferAccepted', namespace: 'Acme/Waitlist');

        /** @Then a field with no name is refused, for the same reason */
        $this->expectException(BlankMetricIdentifier::class);
        $this->expectExceptionMessage('The metric field name cannot be blank.');

        /** @When carrying it */
        $metric->withField(name: '  ', value: 'abc-123');
    }

    public function testWithDimensionWhenTheNameIsBlankThenRefusesIt(): void
    {
        /** @Given a metric */
        $metric = Metric::of(name: 'OfferAccepted', namespace: 'Acme/Waitlist');

        /** @Then a dimension with no name is refused, because it resolves to nothing downstream */
        $this->expectException(BlankMetricIdentifier::class);
        $this->expectExceptionMessage('The metric dimension name cannot be blank.');

        /** @When declaring it */
        $metric->withDimension(name: ' ', value: 'saas');
    }

    #[DataProvider('blankIdentifierProvider')]

    public function testOfWhenAnIdentifierIsBlankThenRefusesAndNamesIt(
        string $name,
        string $namespace,
        string $identifier
    ): void {
        /** @Given a name or a namespace that carries nothing */
        /** @Then the refusal names which one is blank */
        $this->expectException(BlankMetricIdentifier::class);
        $this->expectExceptionMessage(sprintf('The metric %s cannot be blank.', $identifier));

        /** @When creating the metric */
        Metric::of(name: $name, namespace: $namespace);
    }

    public function testWithDimensionWhenTwoAddedThenBothSurviveInOrder(): void
    {
        /** @Given a counter */
        $metric = Metric::of(name: 'SubscriptionRenewed', namespace: 'Acme/Subscription');

        /** @When adding two dimensions in sequence */
        $derived = $metric
            ->withDimension(name: 'subscription_type', value: 'saas')
            ->withDimension(name: 'billing_cycle', value: 'monthly');

        /** @Then the second does not replace the first, and the original carries neither */
        self::assertSame('saas', $derived->dimensions['subscription_type']->value);
        self::assertSame('monthly', $derived->dimensions['billing_cycle']->value);
        self::assertSame([], $metric->dimensions);
    }

    public function testOfWhenMeasuredThenDerivesFromTheCounterItStartsAs(): void
    {
        /** @Given a measurement that is not a count */
        /** @When deriving it from the only factory */
        $metric = Metric::of(name: 'OfferResponseTime', namespace: 'Acme/Waitlist')
            ->withUnit(unit: MetricUnit::SECONDS)
            ->withValue(value: 12.5);

        /** @Then both survive, and a duration is seconds and never a prefixed unit */
        self::assertSame(12.5, $metric->value);
        self::assertSame(MetricUnit::SECONDS, $metric->unit);
    }

    public function testWithDimensionWhenNameRepeatedThenTheLastValueWins(): void
    {
        /** @Given a counter already broken down by a dimension */
        $metric = Metric::of(name: 'SubscriptionCanceled', namespace: 'Acme/Subscription')
            ->withDimension(name: 'subscription_type', value: 'saas');

        /** @When the same name is declared again */
        $derived = $metric->withDimension(name: 'subscription_type', value: 'b2c');

        /** @Then the copy carries the last value, without duplicating the name */
        self::assertSame(['subscription_type'], array_keys($derived->dimensions));
        self::assertSame('b2c', $derived->dimensions['subscription_type']->value);
    }

    public function testWithDimensionWhenTheNameIsAlreadyAFieldThenRefusesIt(): void
    {
        /** @Given a counter already carrying a field */
        $metric = Metric::of(name: 'OfferDeclined', namespace: 'Acme/Waitlist')
            ->withField(name: 'plan', value: 'saas');

        /** @Then the refusal names the identifier both would land under, in this order too */
        $this->expectException(DuplicateMetricIdentifier::class);
        $this->expectExceptionMessage(
            'The name <plan> is carried by a field and by a dimension at once. Both land at the root of the '
            . 'same record, so one would silently replace the other.'
        );

        /** @When declaring a dimension under the same name */
        $metric->withDimension(name: 'plan', value: 'b2c');
    }

    public function testWithFieldWhenTheNameIsAlreadyADimensionThenRefusesIt(): void
    {
        /** @Given a counter already broken down by a dimension */
        $metric = Metric::of(name: 'OfferDeclined', namespace: 'Acme/Waitlist')
            ->withDimension(name: 'plan', value: 'saas');

        /** @Then the refusal names the identifier both would land under */
        $this->expectException(DuplicateMetricIdentifier::class);
        $this->expectExceptionMessage(
            'The name <plan> is carried by a field and by a dimension at once. Both land at the root of the '
            . 'same record, so one would silently replace the other.'
        );

        /** @When carrying a field under the same name */
        $metric->withField(name: 'plan', value: 'b2c');
    }

    public function testWithFieldWhenGranularThenTravelsAsFieldAndNotAsDimension(): void
    {
        /** @Given a counter */
        $metric = Metric::of(name: 'OfferExpired', namespace: 'Acme/Waitlist');

        /** @When carrying granular attributes */
        $derived = $metric
            ->withField(name: 'tenant_id', value: 'abc-123')
            ->withField(name: 'attempt', value: 2);

        /** @Then they are fields, the dimension set stays empty, and the original carries neither */
        self::assertSame('abc-123', $derived->fields['tenant_id']->value);
        self::assertSame(2, $derived->fields['attempt']->value);
        self::assertSame([], $derived->dimensions);
        self::assertSame([], $metric->fields);
    }

    public function testWithUnitWhenReplacedThenCopyCarriesItAndOriginalIsUnchanged(): void
    {
        /** @Given a counter */
        $metric = Metric::of(name: 'AppointmentDuration', namespace: 'Acme/Schedule');

        /** @When deriving a copy in another unit */
        $derived = $metric->withUnit(unit: MetricUnit::SECONDS);

        /** @Then the copy carries it and the original stays a count */
        self::assertSame(MetricUnit::SECONDS, $derived->unit);
        self::assertSame(MetricUnit::COUNT, $metric->unit);
    }

    public function testWithValueWhenReplacedThenCopyCarriesItAndOriginalIsUnchanged(): void
    {
        /** @Given a counter */
        $metric = Metric::of(name: 'AppointmentCompleted', namespace: 'Acme/Schedule');

        /** @When deriving a copy with another value */
        $derived = $metric->withValue(value: 7);

        /** @Then the copy carries it and the original still counts one */
        self::assertSame(7, $derived->value);
        self::assertSame(1, $metric->value);
    }

    public static function blankIdentifierProvider(): array
    {
        return [
            'empty name'           => ['', 'Acme/Waitlist', 'name'],
            'blank name'           => ['   ', 'Acme/Waitlist', 'name'],
            'empty namespace'      => ['OfferAccepted', '', 'namespace'],
            'blank namespace'      => ['OfferAccepted', "\t", 'namespace']
        ];
    }
}

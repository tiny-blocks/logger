<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Logger;

use PHPUnit\Framework\TestCase;
use TinyBlocks\Logger\Exceptions\InvalidRedactionPattern;
use TinyBlocks\Logger\Exceptions\NegativeVisibleLength;
use TinyBlocks\Logger\Mask;
use TinyBlocks\Logger\Redactions\DocumentRedaction;
use TinyBlocks\Logger\Redactions\NameRedaction;
use TinyBlocks\Logger\Redactions\PhoneRedaction;
use TinyBlocks\Logger\Redactions\Rules\AllowedFieldsRedaction;
use TinyBlocks\Logger\Redactions\Rules\FullMaskRedaction;
use TinyBlocks\Logger\Redactions\Rules\PatternRedaction;
use TinyBlocks\Logger\Redactions\Rules\RemovedFieldsRedaction;
use TinyBlocks\Logger\Redactions\Rules\ScopedRedaction;
use TinyBlocks\Logger\Redactions\Rules\VisibleEdgesRedaction;
use TinyBlocks\Logger\Redactions\Rules\WordwiseRedaction;
use TinyBlocks\Logger\StructuredLogger;

final class RedactionsTest extends TestCase
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

    public function testRedactWhenVisibleEdgesThenKeepsBothEnds(): void
    {
        /** @Given a structured logger keeping the head and the tail of the phone */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'contact-service')
            ->withRedactions(
                VisibleEdgesRedaction::from(
                    mask: Mask::proportional(),
                    fields: ['phone'],
                    visiblePrefixLength: 5,
                    visibleSuffixLength: 4
                )
            )
            ->build();

        /** @When logging with a phone field */
        $logger->info(message: 'contact.updated', context: ['phone' => '+5511999887766']);

        /** @Then both edges of the value stay visible and the middle is masked */
        self::assertStringContainsString('data={"phone":"+5511*****7766"}', $this->logStream->contents());
    }

    public function testRedactWhenWordwiseThenMasksEachWordApart(): void
    {
        /** @Given a structured logger masking each word of a name with a fixed-width mask */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'user-service')
            ->withRedactions(
                WordwiseRedaction::from(
                    mask: Mask::fixed(length: 3),
                    fields: ['name'],
                    visiblePrefixLength: 2
                )
            )
            ->build();

        /** @When logging with a name made of several words */
        $logger->info(message: 'user.created', context: ['name' => 'Gustavo Freze']);

        /** @Then every word keeps its own visible prefix */
        self::assertStringContainsString('data={"name":"Gu*** Fr***"}', $this->logStream->contents());
    }

    public function testRedactWhenFullMaskThenHidesTheValueLength(): void
    {
        /** @Given a structured logger masking two fields of different lengths with a fixed mask */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'audit-service')
            ->withRedactions(
                FullMaskRedaction::from(mask: Mask::fixed(length: 4), fields: ['user_agent', 'session_id'])
            )
            ->build();

        /** @When logging with both fields and an unrelated one */
        $logger->info(message: 'request.received', context: [
            'user_agent' => 'Mozilla/5.0',
            'session_id' => 'abc',
            'route'      => '/v1/users'
        ]);

        /** @Then both masks have the same width regardless of the original length */
        $output = $this->logStream->contents();

        self::assertStringContainsString('"user_agent":"****"', $output);
        self::assertStringContainsString('"session_id":"****"', $output);
        self::assertStringContainsString('"route":"/v1/users"', $output);
    }

    public function testRedactWhenValueIsNumericThenMasksItAsText(): void
    {
        /** @Given a structured logger with document redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'kyc-service')
            ->withRedactions(DocumentRedaction::default())
            ->build();

        /** @When logging with a document decoded from JSON as an integer */
        $logger->info(message: 'kyc.verified', context: ['document' => 12345678900, 'amount' => 100]);

        /** @Then the numeric document is masked as text and the unrelated number is preserved */
        self::assertStringContainsString('data={"document":"********900","amount":100}', $this->logStream->contents());
    }

    public function testRedactWhenListOfValuesThenMasksEachElement(): void
    {
        /** @Given a structured logger with phone redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'notification-service')
            ->withRedactions(PhoneRedaction::from(fields: ['phone'], visibleSuffixLength: 4))
            ->build();

        /** @When logging with a sensitive field holding a list of values */
        $logger->info(message: 'sms.queued', context: ['phone' => ['+5511999887766', '+5521988776655']]);

        /** @Then every element of the list is masked */
        $output = $this->logStream->contents();

        self::assertStringContainsString('data={"phone":["**********7766","**********6655"]}', $output);
        self::assertStringNotContainsString('+5511999887766', $output);
    }

    public function testRedactWhenValueIsNullThenLeavesItUntouched(): void
    {
        /** @Given a structured logger with document redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'kyc-service')
            ->withRedactions(DocumentRedaction::default())
            ->build();

        /** @When logging with a sensitive field holding no value */
        $logger->info(message: 'kyc.skipped', context: ['document' => null, 'status' => 'pending']);

        /** @Then the absent value is written as is */
        self::assertStringContainsString('data={"document":null,"status":"pending"}', $this->logStream->contents());
    }

    public function testRedactWhenWildcardFieldThenMasksEveryMatch(): void
    {
        /** @Given a structured logger with a name redaction targeting a field name pattern */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'messaging-service')
            ->withRedactions(NameRedaction::from(fields: ['*_name'], visiblePrefixLength: 2))
            ->build();

        /** @When logging with a matching field, a field that does not match, and a list */
        $logger->info(message: 'message.dispatched', context: [
            'client_name' => 'João Silva',
            'name'        => 'Maria',
            'tags'        => ['first', 'retry']
        ]);

        /** @Then only the matching field is masked */
        $output = $this->logStream->contents();

        self::assertStringContainsString('"client_name":"Jo********"', $output);
        self::assertStringContainsString('"name":"Maria"', $output);

        /** @And the numeric keys of the list are matched against the pattern without failing */
        self::assertStringContainsString('"tags":["first","retry"]', $output);
    }

    public function testRedactWhenScopedThenMasksOnlyInsideTheScope(): void
    {
        /** @Given a structured logger masking the value field only under a document parent */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'payment-service')
            ->withRedactions(
                ScopedRedaction::under(
                    parent: 'document',
                    redaction: DocumentRedaction::from(fields: ['value'], visibleSuffixLength: 2)
                )
            )
            ->build();

        /** @When logging with the same field name inside and outside the scope */
        $logger->info(message: 'payment.created', context: [
            'document' => ['type' => 'cpf', 'value' => '12345678901'],
            'metadata' => ['value' => 'operational-marker'],
            'charge'   => ['document' => ['value' => '98765432100']]
        ]);

        /** @Then the value inside the scope is masked */
        $output = $this->logStream->contents();

        self::assertStringContainsString('"document":{"type":"cpf","value":"*********01"}', $output);

        /** @And the value outside the scope is preserved */
        self::assertStringContainsString('"metadata":{"value":"operational-marker"}', $output);

        /** @And the scope is honored at any depth */
        self::assertStringContainsString('"charge":{"document":{"value":"*********00"}}', $output);
    }

    public function testRedactWhenPatternMatchesTextThenMasksTheMatch(): void
    {
        /** @Given a structured logger masking eleven-digit runs found in any text */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'error-service')
            ->withRedactions(PatternRedaction::from(pattern: '/\d{11}/', replacement: '[REDACTED]'))
            ->build();

        /** @When logging an exception message and a URI carrying the document */
        $logger->error(message: 'request.failed', context: [
            'message'  => 'Document 12345678901 is invalid.',
            'attempts' => 3,
            'nested'   => ['uri' => '/clients/12345678901']
        ]);

        /** @Then the match inside the free text is replaced */
        $output = $this->logStream->contents();

        self::assertStringContainsString('"message":"Document [REDACTED] is invalid."', $output);

        /** @And the match inside the nested URI is replaced */
        self::assertStringContainsString('"nested":{"uri":"/clients/[REDACTED]"}', $output);

        /** @And values that are not text are left alone */
        self::assertStringContainsString('"attempts":3', $output);
    }

    public function testRedactWhenAllowListThenMasksEveryFieldOutsideIt(): void
    {
        /** @Given a structured logger allowing only the city and the state under the address */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'payment-service')
            ->withRedactions(
                ScopedRedaction::under(
                    parent: 'address',
                    redaction: AllowedFieldsRedaction::from(mask: Mask::fixed(length: 3), fields: ['city', 'state'])
                )
            )
            ->build();

        /** @When logging with an address and a sibling field outside the scope */
        $logger->info(message: 'payment.created', context: [
            'address' => ['city' => 'São Paulo', 'state' => 'BR-SP', 'street' => 'Rua Example'],
            'name'    => 'Maria'
        ]);

        /** @Then the allowed fields survive and everything else in the scope is masked */
        $output = $this->logStream->contents();

        self::assertStringContainsString('"city":"São Paulo","state":"BR-SP","street":"***"', $output);

        /** @And fields outside the scope are untouched */
        self::assertStringContainsString('"name":"Maria"', $output);
    }

    public function testRedactWhenScopeHoldsScalarThenLeavesItUntouched(): void
    {
        /** @Given a structured logger masking the value field only under a document parent */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'payment-service')
            ->withRedactions(
                ScopedRedaction::under(
                    parent: 'document',
                    redaction: DocumentRedaction::from(fields: ['value'], visibleSuffixLength: 2)
                )
            )
            ->build();

        /** @When logging with the scope parent holding a scalar instead of a sub payload */
        $logger->info(message: 'payment.created', context: ['document' => 'plain-value']);

        /** @Then the scalar is written as is */
        self::assertStringContainsString('data={"document":"plain-value"}', $this->logStream->contents());
    }

    public function testRedactWhenCommonSecretsThenMasksEverySecretField(): void
    {
        /** @Given a structured logger with the common secrets redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'auth-service')
            ->withRedactions(FullMaskRedaction::commonSecrets())
            ->build();

        /** @When logging with one field per secret naming convention */
        $logger->info(message: 'auth.check', context: [
            'user_id'          => 'u-1',
            'credentials'      => 'basic',
            'access_token'     => 'at-1',
            'authorization'    => 'Bearer x',
            'client_secret'    => 'cs-1',
            'stripe_api_key'   => 'ak-1',
            'rsa_private_key'  => 'pk-1',
            'current_password' => 'pw-1'
        ]);

        /** @Then every secret field is fully masked */
        $output = $this->logStream->contents();

        self::assertStringContainsString('"credentials":"********"', $output);
        self::assertStringContainsString('"access_token":"********"', $output);
        self::assertStringContainsString('"authorization":"********"', $output);
        self::assertStringContainsString('"client_secret":"********"', $output);
        self::assertStringContainsString('"stripe_api_key":"********"', $output);
        self::assertStringContainsString('"rsa_private_key":"********"', $output);
        self::assertStringContainsString('"current_password":"********"', $output);

        /** @And fields outside the convention are preserved */
        self::assertStringContainsString('"user_id":"u-1"', $output);
    }

    public function testRedactWhenFieldsRemovedThenDropsThemFromTheEntry(): void
    {
        /** @Given a structured logger dropping the trace and every token field */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'error-service')
            ->withRedactions(RemovedFieldsRedaction::from(fields: ['trace', '*_token']))
            ->build();

        /** @When logging with those fields at the root and nested */
        $logger->error(message: 'request.failed', context: [
            'message' => 'boom',
            'trace'   => '#0 stack',
            'nested'  => ['access_token' => 'at-1', 'id' => '7']
        ]);

        /** @Then the dropped fields leave no trace in the entry */
        self::assertStringContainsString('data={"message":"boom","nested":{"id":"7"}}', $this->logStream->contents());
    }

    public function testRedactWhenSeparatorsKeptThenPreservesPunctuation(): void
    {
        /** @Given a structured logger masking a postal code while keeping its separators */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'payment-service')
            ->withRedactions(
                VisibleEdgesRedaction::from(
                    mask: Mask::preservingSeparators(),
                    fields: ['postal_code', 'reference', 'coordinates'],
                    visiblePrefixLength: 3
                )
            )
            ->build();

        /** @When logging with a formatted postal code, an accented reference, and a coordinate */
        $logger->info(message: 'address.saved', context: [
            'postal_code' => '01310-100',
            'reference'   => 'Rua Açaí, 42',
            'coordinates' => 'S 23° 33'
        ]);

        /** @Then digits are hidden and the separator survives */
        $output = $this->logStream->contents();

        self::assertStringContainsString('"postal_code":"013**-***"', $output);

        /** @And accented letters are hidden as single characters */
        self::assertStringContainsString('"reference":"Rua ****, **"', $output);

        /** @And symbols outside the letter and digit categories survive */
        self::assertStringContainsString('"coordinates":"S 2*° **"', $output);
    }

    public function testRedactWhenMapOfValuesThenDescendsInsteadOfMasking(): void
    {
        /** @Given a structured logger with document redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'kyc-service')
            ->withRedactions(DocumentRedaction::default())
            ->build();

        /** @When logging with a sensitive field holding a map instead of a value */
        $logger->info(message: 'kyc.verified', context: [
            'document' => ['type' => 'cnpj', 'value' => '12345678000199', 'issued_at' => '2020-01-01']
        ]);

        /** @Then the map is descended into, so the fields that carry their own meaning survive */
        self::assertStringContainsString(
            'data={"document":{"type":"cnpj","value":"12345678000199","issued_at":"2020-01-01"}}',
            $this->logStream->contents()
        );
    }

    public function testRedactWhenPatternIsInvalidThenThrowsInvalidPattern(): void
    {
        /** @Then an exception describing the rejected pattern is raised */
        $this->expectException(InvalidRedactionPattern::class);

        /** @And the message names the offending pattern */
        $this->expectExceptionMessage('Pattern is not a valid regular expression: /[unclosed/.');

        /** @When building a redaction from a pattern the engine cannot compile */
        PatternRedaction::from(pattern: '/[unclosed/', replacement: '[REDACTED]');
    }

    public function testRedactWhenPrefixIsNegativeThenThrowsNegativeLength(): void
    {
        /** @Then an exception describing the rejected configuration is raised */
        $this->expectException(NegativeVisibleLength::class);

        /** @And the message names both visible lengths */
        $this->expectExceptionMessage('Visible length cannot be negative, got prefix -1 and suffix 4.');

        /** @When building a redaction that leaves a negative number of leading characters visible */
        VisibleEdgesRedaction::from(
            mask: Mask::proportional(),
            fields: ['phone'],
            visiblePrefixLength: -1,
            visibleSuffixLength: 4
        );
    }

    public function testRedactWhenSuffixIsNegativeThenThrowsNegativeLength(): void
    {
        /** @Then an exception describing the rejected configuration is raised */
        $this->expectException(NegativeVisibleLength::class);

        /** @And the message names both visible lengths */
        $this->expectExceptionMessage('Visible length cannot be negative, got prefix 2 and suffix -1.');

        /** @When building a redaction that leaves a negative number of trailing characters visible */
        VisibleEdgesRedaction::from(
            mask: Mask::proportional(),
            fields: ['phone'],
            visiblePrefixLength: 2,
            visibleSuffixLength: -1
        );
    }

    public function testRedactWhenWordwiseHasNoPrefixThenMasksEveryWordHead(): void
    {
        /** @Given a structured logger masking each word down to its last character */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'user-service')
            ->withRedactions(
                WordwiseRedaction::from(
                    mask: Mask::proportional(),
                    fields: ['name'],
                    visibleSuffixLength: 1
                )
            )
            ->build();

        /** @When logging with a name made of several words */
        $logger->info(message: 'user.created', context: ['name' => 'Gustavo Freze']);

        /** @Then every word keeps only its trailing character */
        self::assertStringContainsString('data={"name":"******o ****e"}', $this->logStream->contents());
    }

    public function testRedactWhenAllowListAndNullValueThenLeavesItUntouched(): void
    {
        /** @Given a structured logger allowing only the city field */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'payment-service')
            ->withRedactions(AllowedFieldsRedaction::from(mask: Mask::fixed(length: 3), fields: ['city']))
            ->build();

        /** @When logging with an absent value and a nested allowed field */
        $logger->info(message: 'address.saved', context: [
            'city'   => 'São Paulo',
            'street' => null,
            'nested' => ['city' => 'Rio', 'label' => 'x']
        ]);

        /** @Then the absent value is written as is and the nested allow list still applies */
        $output = $this->logStream->contents();

        self::assertStringContainsString('"city":"São Paulo","street":null', $output);
        self::assertStringContainsString('"nested":{"city":"Rio","label":"***"}', $output);
    }
}

<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Logger;

use PHPUnit\Framework\TestCase;
use TinyBlocks\Logger\LogContext;
use TinyBlocks\Logger\Redactions\DocumentRedaction;
use TinyBlocks\Logger\Redactions\EmailRedaction;
use TinyBlocks\Logger\Redactions\PhoneRedaction;
use TinyBlocks\Logger\StructuredLogger;

final class StructuredLoggerTest extends TestCase
{
    /** @var resource */
    private mixed $stream;

    protected function setUp(): void
    {
        $this->stream = fopen('php://memory', 'r+');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    public function testLogInfo(): void
    {
        /** @Given a structured logger */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'account-service')
            ->build();

        /** @When logging an info entry */
        $logger->info(message: 'account.created', context: ['accountId' => 1]);

        /** @Then the output should contain the expected level, component, key, and data */
        $output = $this->streamContents();

        self::assertStringContainsString('component=account-service', $output);
        self::assertStringContainsString('level=INFO', $output);
        self::assertStringContainsString('key=account.created', $output);
        self::assertStringContainsString('"accountId":1', $output);
    }

    public function testLogWarning(): void
    {
        /** @Given a structured logger */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'inventory-service')
            ->build();

        /** @When logging a warning entry */
        $logger->warning(message: 'stock.low', context: ['productId' => 7, 'remaining' => 2]);

        /** @Then the output should contain the warning level */
        $output = $this->streamContents();

        self::assertStringContainsString('level=WARNING', $output);
        self::assertStringContainsString('key=stock.low', $output);
    }

    public function testLogError(): void
    {
        /** @Given a structured logger */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'payment-service')
            ->build();

        /** @When logging an error entry */
        $logger->error(message: 'payment.failed', context: ['reason' => 'timeout']);

        /** @Then the output should contain the error level */
        $output = $this->streamContents();

        self::assertStringContainsString('level=ERROR', $output);
        self::assertStringContainsString('key=payment.failed', $output);
    }

    public function testLogDebug(): void
    {
        /** @Given a structured logger */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'debug-service')
            ->build();

        /** @When logging a debug entry */
        $logger->debug(message: 'query.executed', context: ['sql' => 'SELECT 1']);

        /** @Then the output should contain the debug level */
        $output = $this->streamContents();

        self::assertStringContainsString('level=DEBUG', $output);
        self::assertStringContainsString('key=query.executed', $output);
    }

    public function testLogWithEmptyData(): void
    {
        /** @Given a structured logger */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'minimal-service')
            ->build();

        /** @When logging with no data */
        $logger->info(message: 'heartbeat');

        /** @Then the output should contain an empty JSON array for data */
        self::assertStringContainsString('data=[]', $this->streamContents());
    }

    public function testLogWithoutContextHasEmptyCorrelationId(): void
    {
        /** @Given a structured logger without any context */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'no-context-service')
            ->build();

        /** @When logging without a context */
        $logger->info(message: 'no.context.event');

        /** @Then the correlation ID should be empty between the markers */
        $output = $this->streamContents();

        self::assertMatchesRegularExpression('/correlation_id= level=/', $output);
    }

    public function testLogPreservesSlashesAndUnicodeInData(): void
    {
        /** @Given a structured logger */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'encoding-service')
            ->build();

        /** @When logging with data containing slashes and Unicode characters */
        $logger->info(message: 'path.resolved', context: [
            'url'  => 'https://example.com/api/v1/users',
            'name' => 'José María'
        ]);

        /** @Then the slashes should not be escaped */
        $output = $this->streamContents();

        self::assertStringContainsString('https://example.com/api/v1/users', $output);
        self::assertStringNotContainsString('https:\/\/example.com\/api\/v1\/users', $output);

        /** @And the Unicode characters should not be escaped */
        self::assertStringContainsString('José María', $output);
        self::assertStringNotContainsString('\u00e9', $output);
    }

    public function testLogWithCorrelationId(): void
    {
        /** @Given a structured logger with a correlation context derived after creation */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'order-service')
            ->build();

        $loggerWithContext = $logger->withContext(context: LogContext::from(correlationId: 'req-abc-123'));

        /** @When logging from the contextual logger */
        $loggerWithContext->info(message: 'order.placed', context: ['orderId' => 42]);

        /** @Then the output should contain the correlation ID */
        self::assertStringContainsString('correlation_id=req-abc-123', $this->streamContents());
    }

    public function testLogWithCorrelationIdFromCreation(): void
    {
        /** @Given a structured logger created with a correlation context */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withContext(context: LogContext::from(correlationId: 'req-initial'))
            ->withComponent(component: 'order-service')
            ->build();

        /** @When logging */
        $logger->info(message: 'order.started');

        /** @Then the output should contain the correlation ID */
        self::assertStringContainsString('correlation_id=req-initial', $this->streamContents());
    }

    public function testWithContextReturnsNewInstanceWithoutMutatingOriginal(): void
    {
        /** @Given a structured logger and a contextual copy */
        $original = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'auth-service')
            ->build();

        $contextual = $original->withContext(context: LogContext::from(correlationId: 'ctx-999'));

        /** @When logging from both instances */
        $original->info(message: 'auth.check');
        $contextual->info(message: 'auth.success');

        /** @Then the original log should not contain the correlation ID and the contextual one should */
        $lines = array_filter(explode("\n", $this->streamContents()));

        self::assertStringNotContainsString('correlation_id=ctx-999', $lines[0]);
        self::assertStringContainsString('correlation_id=ctx-999', $lines[1]);
    }

    public function testLogWithDocumentRedaction(): void
    {
        /** @Given a structured logger with document redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'payment-service')
            ->withRedactions(DocumentRedaction::default())
            ->build();

        /** @When logging with a document field */
        $logger->error(message: 'payment.failed', context: ['document' => '12345678900', 'amount' => 100.50]);

        /** @Then the document should be redacted showing only the last 3 characters */
        $output = $this->streamContents();

        self::assertStringContainsString('********900', $output);
        self::assertStringNotContainsString('12345678900', $output);
        self::assertStringContainsString('100.5', $output);
    }

    public function testLogWithDocumentRedactionOnMultipleFields(): void
    {
        /** @Given a structured logger with document redaction targeting multiple field names */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'kyc-service')
            ->withRedactions(DocumentRedaction::from(fields: ['cpf', 'cnpj'], visibleSuffixLength: 5))
            ->build();

        /** @When logging with both fields */
        $logger->info(message: 'kyc.verified', context: [
            'cpf'  => '12345678900',
            'cnpj' => '12345678000199'
        ]);

        /** @Then both fields should be redacted showing only the last 5 characters */
        $output = $this->streamContents();

        self::assertStringContainsString('******78900', $output);
        self::assertStringContainsString('*********00199', $output);
        self::assertStringNotContainsString('12345678900', $output);
        self::assertStringNotContainsString('12345678000199', $output);
    }

    public function testLogWithDocumentRedactionWhenValueIsShorterThanVisibleLength(): void
    {
        /** @Given a structured logger with document redaction configured to show 10 characters */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'kyc-service')
            ->withRedactions(DocumentRedaction::from(fields: ['document'], visibleSuffixLength: 10))
            ->build();

        /** @When logging with a document shorter than the visible length */
        $logger->info(message: 'kyc.check', context: ['document' => 'abc']);

        /** @Then the value should remain exactly as-is with no masking asterisks */
        $output = $this->streamContents();

        self::assertStringContainsString('"document":"abc"', $output);
        self::assertStringNotContainsString('*', $output);
    }

    public function testLogWithDocumentRedactionExactLengthMatch(): void
    {
        /** @Given a structured logger with document redaction where visible length equals value length */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'kyc-service')
            ->withRedactions(DocumentRedaction::from(fields: ['document'], visibleSuffixLength: 3))
            ->build();

        /** @When logging with a document whose length equals the visible suffix length */
        $logger->info(message: 'kyc.check', context: ['document' => 'abc']);

        /** @Then the value should remain exactly as-is with no masking asterisks */
        $output = $this->streamContents();

        self::assertStringContainsString('"document":"abc"', $output);
        self::assertStringNotContainsString('*', $output);
    }

    public function testLogWithEmailRedaction(): void
    {
        /** @Given a structured logger with email redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'user-service')
            ->withRedactions(EmailRedaction::default())
            ->build();

        /** @When logging with an email field */
        $logger->info(message: 'user.registered', context: ['email' => 'john@example.com']);

        /** @Then the email should be redacted preserving only the first 2 characters of the local part */
        $output = $this->streamContents();

        self::assertStringContainsString('jo**@example.com', $output);
        self::assertStringNotContainsString('john@example.com', $output);
    }

    public function testLogWithEmailRedactionOnMultipleFields(): void
    {
        /** @Given a structured logger with email redaction targeting multiple field names */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'notification-service')
            ->withRedactions(
                EmailRedaction::from(
                    fields: ['email', 'contact_email', 'recoveryEmail'],
                    visiblePrefixLength: 2
                )
            )
            ->build();

        /** @When logging with multiple email field variations */
        $logger->info(message: 'notification.sent', context: [
            'email'         => 'john@example.com',
            'contact_email' => 'jane@corp.io',
            'recoveryEmail' => 'admin@recovery.org'
        ]);

        /** @Then all email fields should be redacted */
        $output = $this->streamContents();

        self::assertStringContainsString('jo**@example.com', $output);
        self::assertStringContainsString('ja**@corp.io', $output);
        self::assertStringContainsString('ad***@recovery.org', $output);
    }

    public function testLogWithEmailRedactionWithoutAtSign(): void
    {
        /** @Given a structured logger with email redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'user-service')
            ->withRedactions(EmailRedaction::default())
            ->build();

        /** @When logging with an invalid email (no @ sign) */
        $logger->info(message: 'user.attempt', context: ['email' => 'invalidemail']);

        /** @Then the entire value should be fully masked */
        $output = $this->streamContents();

        self::assertStringContainsString('************', $output);
        self::assertStringNotContainsString('invalidemail', $output);
    }

    public function testLogWithEmailRedactionWhenLocalPartIsShorterThanVisibleLength(): void
    {
        /** @Given a structured logger with email redaction configured to show 10 characters */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'user-service')
            ->withRedactions(EmailRedaction::from(fields: ['email'], visiblePrefixLength: 10))
            ->build();

        /** @When logging with an email whose local part is shorter than the visible length */
        $logger->info(message: 'user.check', context: ['email' => 'ab@test.com']);

        /** @Then the email should remain exactly as-is with no masking asterisks */
        $output = $this->streamContents();

        self::assertStringContainsString('"email":"ab@test.com"', $output);
        self::assertStringNotContainsString('*', $output);
    }

    public function testLogWithPhoneRedaction(): void
    {
        /** @Given a structured logger with phone redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'notification-service')
            ->withRedactions(PhoneRedaction::default())
            ->build();

        /** @When logging with a phone field */
        $logger->info(message: 'sms.sent', context: ['phone' => '+5511999887766']);

        /** @Then the phone should be redacted showing only the last 4 characters */
        $output = $this->streamContents();

        self::assertStringContainsString('**********7766', $output);
        self::assertStringNotContainsString('+5511999887766', $output);
    }

    public function testLogWithPhoneRedactionOnMultipleFields(): void
    {
        /** @Given a structured logger with phone redaction targeting multiple field names */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'contact-service')
            ->withRedactions(
                PhoneRedaction::from(
                    fields: ['phone', 'mobile', 'whatsapp'],
                    visibleSuffixLength: 4
                )
            )
            ->build();

        /** @When logging with multiple phone field variations */
        $logger->info(message: 'contact.updated', context: [
            'phone'    => '+5511999887766',
            'mobile'   => '+5521988776655',
            'whatsapp' => '+5531977665544'
        ]);

        /** @Then all phone fields should be redacted */
        $output = $this->streamContents();

        self::assertStringContainsString('**********7766', $output);
        self::assertStringContainsString('**********6655', $output);
        self::assertStringContainsString('**********5544', $output);
    }

    public function testLogWithPhoneRedactionWhenValueIsShorterThanVisibleLength(): void
    {
        /** @Given a structured logger with phone redaction configured to show 10 characters */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'notification-service')
            ->withRedactions(PhoneRedaction::from(fields: ['phone'], visibleSuffixLength: 10))
            ->build();

        /** @When logging with a phone shorter than the visible length */
        $logger->info(message: 'sms.check', context: ['phone' => '1234']);

        /** @Then the value should remain exactly as-is with no masking asterisks */
        $output = $this->streamContents();

        self::assertStringContainsString('"phone":"1234"', $output);
        self::assertStringNotContainsString('*', $output);
    }

    public function testLogWithPhoneRedactionExactLengthMatch(): void
    {
        /** @Given a structured logger with phone redaction where visible length equals value length */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'notification-service')
            ->withRedactions(PhoneRedaction::from(fields: ['phone'], visibleSuffixLength: 4))
            ->build();

        /** @When logging with a phone whose length equals the visible suffix length */
        $logger->info(message: 'sms.check', context: ['phone' => '1234']);

        /** @Then the value should remain exactly as-is with no masking asterisks */
        $output = $this->streamContents();

        self::assertStringContainsString('"phone":"1234"', $output);
        self::assertStringNotContainsString('*', $output);
    }

    public function testLogWithMultipleRedactions(): void
    {
        /** @Given a structured logger with multiple redactions */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'user-service')
            ->withRedactions(
                DocumentRedaction::default(),
                EmailRedaction::default(),
                PhoneRedaction::default()
            )
            ->build();

        /** @When logging with multiple sensitive fields */
        $logger->info(message: 'user.registered', context: [
            'document' => '12345678900',
            'email'    => 'john@example.com',
            'phone'    => '+5511999887766',
            'name'     => 'John'
        ]);

        /** @Then each field should be redacted according to its rule */
        $output = $this->streamContents();

        self::assertStringContainsString('********900', $output);
        self::assertStringContainsString('jo**@example.com', $output);
        self::assertStringContainsString('**********7766', $output);
        self::assertStringContainsString('John', $output);
    }

    public function testLogWithoutRedaction(): void
    {
        /** @Given a structured logger without any redactions */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'simple-service')
            ->build();

        /** @When logging with data that could be sensitive */
        $logger->info(message: 'data.processed', context: ['document' => '12345678900']);

        /** @Then the data should appear unmodified */
        self::assertStringContainsString('12345678900', $this->streamContents());
    }

    public function testLogRedactsNestedArrayAndScalarFieldsInSameLevel(): void
    {
        /** @Given a structured logger with document redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'multi-level-service')
            ->withRedactions(DocumentRedaction::default())
            ->build();

        /** @When logging with a nested array followed by a scalar field that both require redaction */
        $logger->info(message: 'multi.level', context: [
            'nested'   => ['document' => '11111111100'],
            'document' => '99999999900'
        ]);

        /** @Then both the nested and scalar documents should be redacted */
        $output = $this->streamContents();

        self::assertStringContainsString('********100', $output);
        self::assertStringContainsString('********900', $output);
        self::assertStringNotContainsString('11111111100', $output);
        self::assertStringNotContainsString('99999999900', $output);
    }

    public function testLogRedactsMultipleScalarFieldsAfterNestedArray(): void
    {
        /** @Given a structured logger with redaction for two fields */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'batch-service')
            ->withRedactions(DocumentRedaction::from(fields: ['document', 'taxId'], visibleSuffixLength: 3))
            ->build();

        /** @When logging with a nested array followed by multiple scalar fields that need redaction */
        $logger->info(message: 'batch.process', context: [
            'metadata' => ['document' => '11111111100'],
            'document' => '22222222200',
            'taxId'    => '33333333300',
            'status'   => 'active'
        ]);

        /** @Then all three documents should be redacted and status preserved */
        $output = $this->streamContents();

        self::assertStringContainsString('********100', $output);
        self::assertStringContainsString('********200', $output);
        self::assertStringContainsString('********300', $output);
        self::assertStringNotContainsString('11111111100', $output);
        self::assertStringNotContainsString('22222222200', $output);
        self::assertStringNotContainsString('33333333300', $output);
        self::assertStringContainsString('active', $output);
    }

    public function testLogRedactsNestedArrayPreservingAllSiblingFields(): void
    {
        /** @Given a structured logger with document redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'nested-service')
            ->withRedactions(DocumentRedaction::default())
            ->build();

        /** @When logging with a nested array containing the target field and multiple sibling fields */
        $logger->info(message: 'nested.check', context: [
            'profile' => [
                'document' => '12345678900',
                'name'     => 'John',
                'role'     => 'admin',
                'active'   => true
            ]
        ]);

        /** @Then the document should be redacted within the nested structure */
        $output = $this->streamContents();

        self::assertStringContainsString('********900', $output);
        self::assertStringNotContainsString('12345678900', $output);

        /** @And all sibling fields in the nested array must be preserved */
        self::assertStringContainsString('"name":"John"', $output);
        self::assertStringContainsString('"role":"admin"', $output);
        self::assertStringContainsString('"active":true', $output);
    }

    public function testLogRedactsDeeplyNestedFields(): void
    {
        /** @Given a structured logger with document redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'deep-service')
            ->withRedactions(DocumentRedaction::default())
            ->build();

        /** @When logging with a deeply nested structure where a sub-array precedes a target scalar field */
        $logger->info(message: 'deep.check', context: [
            'level1' => [
                'level2'   => [
                    'document' => '12345678900',
                    'label'    => 'deep-value'
                ],
                'document' => '99988877700'
            ]
        ]);

        /** @Then the deeply nested document should be redacted */
        $output = $this->streamContents();

        self::assertStringContainsString('********900', $output);
        self::assertStringNotContainsString('12345678900', $output);

        /** @And the sibling field in the deepest level must be preserved */
        self::assertStringContainsString('"label":"deep-value"', $output);

        /** @And the scalar document after the sub-array must also be redacted */
        self::assertStringContainsString('********700', $output);
        self::assertStringNotContainsString('99988877700', $output);
    }

    public function testLogWithCustomTemplate(): void
    {
        /** @Given a structured logger with a custom template */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withTemplate(template: "[%s] %s | %s | %s | %s | %s\n")
            ->withComponent(component: 'custom-service')
            ->build();

        /** @When logging an info entry */
        $logger->info(message: 'custom.event', context: ['value' => 42]);

        /** @Then the output should follow the custom format */
        $output = $this->streamContents();

        self::assertStringContainsString('custom-service', $output);
        self::assertStringContainsString('custom.event', $output);
        self::assertStringContainsString('"value":42', $output);
        self::assertStringNotContainsString('component=', $output);
    }

    public function testLogWithDefaultTemplate(): void
    {
        /** @Given a structured logger using the default template */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'default-service')
            ->build();

        /** @When logging an info entry */
        $logger->info(message: 'default.event');

        /** @Then the output should use the default template format */
        $output = $this->streamContents();

        self::assertStringContainsString('component=default-service', $output);
        self::assertStringContainsString('level=INFO', $output);
        self::assertStringContainsString('key=default.event', $output);
        self::assertStringContainsString('correlation_id=', $output);
    }

    public function testWithContextPreservesRedactions(): void
    {
        /** @Given a structured logger with a redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'secure-service')
            ->withRedactions(
                DocumentRedaction::from(
                    fields: ['secret'],
                    visibleSuffixLength: 3
                )
            )
            ->build();

        /** @When creating a contextual logger and logging */
        $contextual = $logger->withContext(context: LogContext::from(correlationId: 'ctx-preserve'));
        $contextual->error(message: 'secure.action', context: ['secret' => 'my-secret-value']);

        /** @Then the redaction should still be applied */
        $output = $this->streamContents();

        self::assertStringNotContainsString('my-secret-value', $output);
        self::assertStringContainsString('correlation_id=ctx-preserve', $output);
    }

    public function testWithContextPreservesCustomTemplate(): void
    {
        /** @Given a structured logger with a custom template */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withTemplate(template: "[%s] %s | %s | %s | %s | %s\n")
            ->withComponent(component: 'template-service')
            ->build();

        /** @When creating a contextual logger and logging */
        $contextual = $logger->withContext(context: LogContext::from(correlationId: 'ctx-tmpl'));
        $contextual->info(message: 'template.event');

        /** @Then the custom template should be preserved */
        $output = $this->streamContents();

        self::assertStringNotContainsString('component=', $output);
        self::assertStringContainsString('template-service', $output);
    }

    public function testBuilderAccumulatesRedactionsFromMultipleCalls(): void
    {
        /** @Given a structured logger built with redactions added in separate calls */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'multi-call-service')
            ->withRedactions(DocumentRedaction::default())
            ->withRedactions(EmailRedaction::default())
            ->build();

        /** @When logging with both sensitive fields */
        $logger->info(message: 'multi.call', context: [
            'document' => '12345678900',
            'email'    => 'john@example.com'
        ]);

        /** @Then both fields should be redacted */
        $output = $this->streamContents();

        self::assertStringContainsString('********900', $output);
        self::assertStringContainsString('jo**@example.com', $output);
        self::assertStringNotContainsString('12345678900', $output);
        self::assertStringNotContainsString('john@example.com', $output);
    }

    private function streamContents(): string
    {
        rewind($this->stream);
        return stream_get_contents($this->stream);
    }
}

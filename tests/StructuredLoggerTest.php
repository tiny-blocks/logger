<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Logger;

use PHPUnit\Framework\TestCase;
use TinyBlocks\Logger\LogContext;
use TinyBlocks\Logger\Redactions\DocumentRedaction;
use TinyBlocks\Logger\Redactions\EmailRedaction;
use TinyBlocks\Logger\Redactions\NameRedaction;
use TinyBlocks\Logger\Redactions\PasswordRedaction;
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

    public function testLogReplacesUnencodableDataWithEncodingFailurePayload(): void
    {
        /** @Given a structured logger */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'broken-json-service')
            ->build();

        /** @When logging a payload that json_encode cannot serialize */
        $logger->info(message: 'bad.payload', context: ['value' => "\xB1\x31"]);

        /** @Then the data section should contain the encoding failure payload */
        self::assertStringContainsString('data={"error":"encoding_failed"}', $this->streamContents());
    }

    public function testLogEscapesControlCharactersInMessageKey(): void
    {
        /** @Given a structured logger */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'injection-test')
            ->build();

        /** @When logging with a message key that contains a newline */
        $logger->info(message: "safe\nkey");

        /** @Then newline characters should appear escaped in the log line */
        self::assertStringContainsString('key=safe\\nkey', $this->streamContents());
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

        /** @When logging with a multibyte value missing an @ sign */
        $logger->info(message: 'user.attempt', context: ['email' => 'çãoabc']);

        /** @Then the mask length matches the number of characters, not bytes */
        $output = $this->streamContents();

        self::assertStringContainsString('"email":"******"', $output);
        self::assertStringNotContainsString('çãoabc', $output);
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

    public function testLogWithPasswordRedaction(): void
    {
        /** @Given a structured logger with password redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'auth-service')
            ->withRedactions(PasswordRedaction::default())
            ->build();

        /** @When logging with a password field */
        $logger->info(message: 'login.attempt', context: ['password' => 's3cr3t!', 'username' => 'john']);

        /** @Then the password should be fully masked */
        $output = $this->streamContents();

        self::assertStringContainsString('*******', $output);
        self::assertStringNotContainsString('s3cr3t!', $output);
        self::assertStringContainsString('john', $output);
    }

    public function testLogWithPasswordRedactionOnMultipleFields(): void
    {
        /** @Given a structured logger with password redaction targeting multiple field names */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'auth-service')
            ->withRedactions(PasswordRedaction::from(fields: ['password', 'secret', 'token']))
            ->build();

        /** @When logging with multiple password field variations */
        $logger->info(message: 'auth.check', context: [
            'password' => 'myP@ssw0rd',
            'secret'   => 'hidden-value',
            'token'    => 'abc123xyz'
        ]);

        /** @Then all password fields should be fully masked */
        $output = $this->streamContents();

        self::assertStringNotContainsString('myP@ssw0rd', $output);
        self::assertStringNotContainsString('hidden-value', $output);
        self::assertStringNotContainsString('abc123xyz', $output);
    }

    public function testLogWithPasswordRedactionOnShortValue(): void
    {
        /** @Given a structured logger with password redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'auth-service')
            ->withRedactions(PasswordRedaction::default())
            ->build();

        /** @When logging with a short password */
        $logger->info(message: 'login.attempt', context: ['password' => 'ab']);

        /** @Then the password should still be fully masked */
        $output = $this->streamContents();

        self::assertStringContainsString('********', $output);
        self::assertStringNotContainsString('"password":"ab"', $output);
    }

    public function testLogWithPasswordRedactionOnNestedField(): void
    {
        /** @Given a structured logger with password redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'auth-service')
            ->withRedactions(PasswordRedaction::default())
            ->build();

        /** @When logging with a nested structure containing a password field */
        $logger->info(message: 'credentials.received', context: [
            'credentials' => [
                'password' => 'sup3rS3cret',
                'username' => 'admin'
            ]
        ]);

        /** @Then the nested password should be fully masked */
        $output = $this->streamContents();

        self::assertStringNotContainsString('sup3rS3cret', $output);
        self::assertStringContainsString('"username":"admin"', $output);
    }


    public function testLogWithPasswordRedactionDoesNotRevealValueLength(): void
    {
        /** @Given a structured logger with password redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'auth-service')
            ->withRedactions(PasswordRedaction::default())
            ->build();

        /** @When logging passwords of different lengths */
        $logger->info(message: 'login.short', context: ['password' => '123']);
        $logger->info(message: 'login.long', context: ['password' => 'mySuperLongP@ssw0rd!123']);

        /** @Then both should produce the same fixed-length mask */
        $lines = array_filter(explode("\n", $this->streamContents()));

        self::assertStringContainsString('"password":"********"', $lines[0]);
        self::assertStringContainsString('"password":"********"', $lines[1]);
    }

    public function testLogWithPasswordRedactionWithCustomFixedMaskLength(): void
    {
        /** @Given a structured logger with password redaction configured with a custom fixed mask length */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'auth-service')
            ->withRedactions(PasswordRedaction::from(fields: ['password'], fixedMaskLength: 12))
            ->build();

        /** @When logging with a password field */
        $logger->info(message: 'login.attempt', context: ['password' => 'abc']);

        /** @Then the mask should have exactly 12 asterisks */
        $output = $this->streamContents();

        self::assertStringContainsString('"password":"************"', $output);
        self::assertStringNotContainsString('abc', $output);
    }

    public function testLogWithNameRedaction(): void
    {
        /** @Given a structured logger with name redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'user-service')
            ->withRedactions(NameRedaction::default())
            ->build();

        /** @When logging with a name field */
        $logger->info(message: 'user.created', context: ['name' => 'Gustavo', 'role' => 'admin']);

        /** @Then the name should be redacted preserving only the first 2 characters */
        $output = $this->streamContents();

        self::assertStringContainsString('Gu*****', $output);
        self::assertStringNotContainsString('"name":"Gustavo"', $output);
        self::assertStringContainsString('admin', $output);
    }

    public function testLogWithNameRedactionOnMultipleFields(): void
    {
        /** @Given a structured logger with name redaction targeting multiple field names */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'user-service')
            ->withRedactions(
                NameRedaction::from(
                    fields: ['name', 'full_name', 'firstName'],
                    visiblePrefixLength: 3
                )
            )
            ->build();

        /** @When logging with multiple name field variations */
        $logger->info(message: 'user.updated', context: [
            'name'      => 'Gustavo',
            'full_name' => 'Gustavo Freze',
            'firstName' => 'Maria'
        ]);

        /** @Then all name fields should be redacted showing only the first 3 characters */
        $output = $this->streamContents();

        self::assertStringContainsString('Gus****', $output);
        self::assertStringContainsString('Gus**********', $output);
        self::assertStringContainsString('Mar**', $output);
        self::assertStringNotContainsString('"name":"Gustavo"', $output);
        self::assertStringNotContainsString('"full_name":"Gustavo Freze"', $output);
        self::assertStringNotContainsString('"firstName":"Maria"', $output);
    }

    public function testLogWithNameRedactionWhenValueIsShorterThanVisibleLength(): void
    {
        /** @Given a structured logger with name redaction configured to show 10 characters */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'user-service')
            ->withRedactions(NameRedaction::from(fields: ['name'], visiblePrefixLength: 10))
            ->build();

        /** @When logging with a name shorter than the visible length */
        $logger->info(message: 'user.check', context: ['name' => 'Ana']);

        /** @Then the value should remain exactly as-is with no masking asterisks */
        $output = $this->streamContents();

        self::assertStringContainsString('"name":"Ana"', $output);
        self::assertStringNotContainsString('*', $output);
    }

    public function testLogWithNameRedactionExactLengthMatch(): void
    {
        /** @Given a structured logger with name redaction where visible length equals value length */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'user-service')
            ->withRedactions(NameRedaction::from(fields: ['name'], visiblePrefixLength: 3))
            ->build();

        /** @When logging with a name whose length equals the visible prefix length */
        $logger->info(message: 'user.check', context: ['name' => 'Ana']);

        /** @Then the value should remain exactly as-is with no masking asterisks */
        $output = $this->streamContents();

        self::assertStringContainsString('"name":"Ana"', $output);
        self::assertStringNotContainsString('*', $output);
    }

    public function testLogWithNameRedactionOnNestedField(): void
    {
        /** @Given a structured logger with name redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'user-service')
            ->withRedactions(NameRedaction::default())
            ->build();

        /** @When logging with a nested structure containing a name field */
        $logger->info(message: 'profile.loaded', context: [
            'profile' => [
                'name'  => 'Gustavo',
                'email' => 'gustavo@example.com'
            ]
        ]);

        /** @Then the nested name should be redacted */
        $output = $this->streamContents();

        self::assertStringContainsString('Gu*****', $output);
        self::assertStringNotContainsString('"name":"Gustavo"', $output);

        /** @And the sibling field should be preserved */
        self::assertStringContainsString('"email":"gustavo@example.com"', $output);
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

    public function testLogWithAllRedactions(): void
    {
        /** @Given a structured logger with all available redactions */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'full-service')
            ->withRedactions(
                DocumentRedaction::default(),
                EmailRedaction::default(),
                PhoneRedaction::default(),
                PasswordRedaction::default(),
                NameRedaction::default()
            )
            ->build();

        /** @When logging with all sensitive fields */
        $logger->info(message: 'user.full.register', context: [
            'document' => '12345678900',
            'email'    => 'john@example.com',
            'phone'    => '+5511999887766',
            'password' => 's3cr3t!',
            'name'     => 'John',
            'status'   => 'active'
        ]);

        /** @Then each field should be redacted according to its rule */
        $output = $this->streamContents();

        self::assertStringContainsString('********900', $output);
        self::assertStringContainsString('jo**@example.com', $output);
        self::assertStringContainsString('**********7766', $output);
        self::assertStringNotContainsString('s3cr3t!', $output);
        self::assertStringContainsString('Jo**', $output);
        self::assertStringNotContainsString('"name":"John"', $output);
        self::assertStringContainsString('active', $output);
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

    public function testLogWithNameRedactionPreservesMultibyteCharacterBoundaries(): void
    {
        /** @Given a structured logger with name redaction and a multibyte value */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'profile-service')
            ->withRedactions(NameRedaction::from(fields: ['name'], visiblePrefixLength: 2))
            ->build();

        /** @When logging with a name that contains a multibyte character */
        $logger->info(message: 'profile.viewed', context: ['name' => 'Ümit']);

        /** @Then the visible prefix should contain whole characters, not bytes */
        self::assertStringContainsString('"name":"Üm**"', $this->streamContents());
    }

    public function testLogWithPhoneRedactionPreservesMultibyteCharacterBoundaries(): void
    {
        /** @Given a structured logger with phone redaction and a multibyte suffix */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'contact-service')
            ->withRedactions(PhoneRedaction::from(fields: ['phone'], visibleSuffixLength: 3))
            ->build();

        /** @When logging with a phone that ends with a multibyte character */
        $logger->info(message: 'contact.updated', context: ['phone' => '+5511ÿÿÿ']);

        /** @Then the visible suffix should contain whole characters, not bytes */
        self::assertStringContainsString('"phone":"*****ÿÿÿ"', $this->streamContents());
    }

    public function testLogWithDocumentRedactionPreservesMultibyteCharacterBoundaries(): void
    {
        /** @Given a structured logger with document redaction and a multibyte suffix */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'kyc-service')
            ->withRedactions(DocumentRedaction::from(fields: ['document'], visibleSuffixLength: 3))
            ->build();

        /** @When logging with a document that ends with multibyte characters */
        $logger->info(message: 'kyc.check', context: ['document' => '1234ção']);

        /** @Then the visible suffix should contain whole characters, not bytes */
        self::assertStringContainsString('"document":"****ção"', $this->streamContents());
    }

    public function testLogWithEmailRedactionPreservesMultibyteCharacterBoundariesInLocalPart(): void
    {
        /** @Given a structured logger with email redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'user-service')
            ->withRedactions(EmailRedaction::from(fields: ['email'], visiblePrefixLength: 2))
            ->build();

        /** @When logging with an email whose local part contains multibyte characters */
        $logger->info(message: 'user.registered', context: ['email' => 'Ümit@example.com']);

        /** @Then the prefix is taken from characters, not bytes */
        self::assertStringContainsString('"email":"Üm**@example.com"', $this->streamContents());
    }

    public function testLogWithEmailRedactionStartsAtSignSearchFromTheBeginning(): void
    {
        /** @Given a structured logger with email redaction */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'user-service')
            ->withRedactions(EmailRedaction::from(fields: ['email'], visiblePrefixLength: 2))
            ->build();

        /** @When logging with an email whose @ sign is at position zero */
        $logger->info(message: 'user.registered', context: ['email' => '@example.com']);

        /** @Then the result keeps the @ at the start because the search begins at offset zero */
        self::assertStringContainsString('"email":"@example.com"', $this->streamContents());
    }

    public function testAppliesAllRedactionsRecursivelyAcrossNestedLevels(): void
    {
        /** @Given a StructuredLogger configured with default redactions for password, email, document, phone, and name */
        $logger = StructuredLogger::create()
            ->withStream(stream: $this->stream)
            ->withComponent(component: 'test')
            ->withRedactions(
                PasswordRedaction::default(),
                EmailRedaction::default(),
                DocumentRedaction::default(),
                PhoneRedaction::default(),
                NameRedaction::default()
            )
            ->build();

        /** @When the logger receives an info entry with the deeply nested context */
        $logger->info(message: 'order_processed', context: [
            'order_id' => 'ORD-12345',
            'customer' => [
                'name'     => 'Maria Silva',
                'email'    => 'maria.silva@example.com',
                'document' => '12345678900',
                'phone'    => '+5511999998888',
                'address'  => [
                    'street'  => 'Rua Example, 123',
                    'city'    => 'São Paulo',
                    'contact' => [
                        'phone' => '+5511888887777',
                        'email' => 'contact@example.com'
                    ]
                ]
            ],
            'payment'  => [
                'method'      => 'credit_card',
                'credentials' => [
                    'password' => 'super-secret-123',
                    'token'    => 'not-to-be-redacted'
                ]
            ],
            'items'    => [
                ['name' => 'Item A', 'price' => 100.00],
                ['name' => 'Item B', 'price' => 50.00]
            ],
            'metadata' => [
                'source' => 'mobile_app',
                'nested' => [
                    'deep' => [
                        'deeper' => [
                            'email'    => 'deep@example.com',
                            'password' => 'deep-secret'
                        ]
                    ]
                ]
            ]
        ]);

        /** @Then all sensitive fields are redacted regardless of nesting depth,
         *        and non-sensitive fields pass through unchanged */
        $output = $this->streamContents();
        $decoded = json_decode(explode(' data=', $output)[1], true);

        self::assertSame('ORD-12345', $decoded['order_id']);

        self::assertStringStartsWith('Ma', $decoded['customer']['name']);
        self::assertStringContainsString('*', $decoded['customer']['name']);
        self::assertStringNotContainsString('Maria Silva', $decoded['customer']['name']);

        self::assertStringStartsWith('ma', $decoded['customer']['email']);
        self::assertStringContainsString('*', $decoded['customer']['email']);
        self::assertStringContainsString('@example.com', $decoded['customer']['email']);
        self::assertStringNotContainsString('maria.silva@example.com', $decoded['customer']['email']);

        self::assertStringEndsWith('900', $decoded['customer']['document']);
        self::assertStringContainsString('*', $decoded['customer']['document']);
        self::assertStringNotContainsString('12345678900', $decoded['customer']['document']);

        self::assertStringEndsWith('8888', $decoded['customer']['phone']);
        self::assertStringContainsString('*', $decoded['customer']['phone']);
        self::assertStringNotContainsString('+5511999998888', $decoded['customer']['phone']);

        /** @And non-sensitive address fields pass through unchanged */
        self::assertSame('Rua Example, 123', $decoded['customer']['address']['street']);
        self::assertSame('São Paulo', $decoded['customer']['address']['city']);

        /** @And sensitive fields at nesting level 3 are also redacted */
        self::assertStringEndsWith('7777', $decoded['customer']['address']['contact']['phone']);
        self::assertStringContainsString('*', $decoded['customer']['address']['contact']['phone']);
        self::assertStringNotContainsString('+5511888887777', $decoded['customer']['address']['contact']['phone']);

        self::assertStringStartsWith('co', $decoded['customer']['address']['contact']['email']);
        self::assertStringContainsString('*', $decoded['customer']['address']['contact']['email']);
        self::assertStringContainsString('@example.com', $decoded['customer']['address']['contact']['email']);
        self::assertStringNotContainsString('contact@example.com', $decoded['customer']['address']['contact']['email']);

        /** @And payment fields are handled according to their redaction rules */
        self::assertSame('credit_card', $decoded['payment']['method']);

        self::assertStringContainsString('*', $decoded['payment']['credentials']['password']);
        self::assertStringNotContainsString('super-secret-123', $decoded['payment']['credentials']['password']);

        self::assertSame('not-to-be-redacted', $decoded['payment']['credentials']['token']);

        /** @And items name fields are masked because NameRedaction matches the literal key 'name' */
        self::assertStringContainsString('*', $decoded['items'][0]['name']);
        self::assertStringNotContainsString('Item A', $decoded['items'][0]['name']);
        self::assertSame(100, $decoded['items'][0]['price']);

        self::assertStringContainsString('*', $decoded['items'][1]['name']);
        self::assertStringNotContainsString('Item B', $decoded['items'][1]['name']);
        self::assertSame(50, $decoded['items'][1]['price']);

        /** @And non-sensitive metadata fields pass through unchanged */
        self::assertSame('mobile_app', $decoded['metadata']['source']);

        /** @And sensitive fields at nesting level 4 are also redacted */
        self::assertStringStartsWith('de', $decoded['metadata']['nested']['deep']['deeper']['email']);
        self::assertStringContainsString('*', $decoded['metadata']['nested']['deep']['deeper']['email']);
        self::assertStringContainsString('@example.com', $decoded['metadata']['nested']['deep']['deeper']['email']);
        self::assertStringNotContainsString(
            'deep@example.com',
            $decoded['metadata']['nested']['deep']['deeper']['email']
        );

        self::assertStringContainsString('*', $decoded['metadata']['nested']['deep']['deeper']['password']);
        self::assertStringNotContainsString(
            'deep-secret',
            $decoded['metadata']['nested']['deep']['deeper']['password']
        );
    }

    private function streamContents(): string
    {
        rewind($this->stream);
        return stream_get_contents($this->stream);
    }
}

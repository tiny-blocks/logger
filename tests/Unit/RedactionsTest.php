<?php

declare(strict_types=1);

namespace Test\TinyBlocks\Logger\Unit;

use PHPUnit\Framework\TestCase;
use TinyBlocks\Logger\Exceptions\MalformedRedactionPattern;
use TinyBlocks\Logger\Exceptions\NegativeVisibleLength;
use TinyBlocks\Logger\Redactions\BirthDateRedaction;
use TinyBlocks\Logger\Redactions\DocumentRedaction;
use TinyBlocks\Logger\Redactions\EmailRedaction;
use TinyBlocks\Logger\Redactions\FilterExpressionRedaction;
use TinyBlocks\Logger\Redactions\GenericRedaction;
use TinyBlocks\Logger\Redactions\Mask;
use TinyBlocks\Logger\Redactions\NameRedaction;
use TinyBlocks\Logger\Redactions\PhoneRedaction;
use TinyBlocks\Logger\Redactions\PostalCodeRedaction;
use TinyBlocks\Logger\Redactions\QueryParametersRedaction;
use TinyBlocks\Logger\Redactions\QueryStringRedaction;
use TinyBlocks\Logger\Redactions\SecretRedaction;
use TinyBlocks\Logger\Redactions\Visibility;
use TinyBlocks\Logger\StreamLogger;

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
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'contact-service')
            ->withRedactions(
                GenericRedaction::masking(
                    mask: Mask::proportional(),
                    fields: ['phone'],
                    visibility: Visibility::edges(prefixLength: 5, suffixLength: 4)
                )
            )
            ->build();

        /** @When logging with a phone field */
        $logger->info(message: 'contact.updated', context: ['phone' => '+5511999887766']);

        /** @Then both edges of the value stay visible and the middle is masked */
        self::assertStringContainsString('data={"phone":"+5511*****7766"}', $this->logStream->contents());
    }

    public function testRedactWhenVisibleWordsThenMasksEachWordApart(): void
    {
        /** @Given a structured logger masking each word of a name with a fixed-width mask */
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'user-service')
            ->withRedactions(
                GenericRedaction::masking(
                    mask: Mask::fixed(length: 3),
                    fields: ['name'],
                    visibility: Visibility::words(prefixLength: 2)
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
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'audit-service')
            ->withRedactions(
                GenericRedaction::masking(mask: Mask::fixed(length: 4), fields: ['user_agent', 'session_id'])
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
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'kyc-service')
            ->withRedactions(DocumentRedaction::default())
            ->build();

        /** @When logging with a document decoded from JSON as an integer */
        $logger->info(message: 'kyc.verified', context: ['document' => 12345678900, 'amount' => 100]);

        /** @Then the numeric document is masked as text and the unrelated number is preserved */
        self::assertStringContainsString('data={"document":"*********00","amount":100}', $this->logStream->contents());
    }

    public function testRedactWhenListOfValuesThenMasksEachElement(): void
    {
        /** @Given a structured logger with phone redaction */
        $logger = StreamLogger::builder()
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
        $logger = StreamLogger::builder()
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
        $logger = StreamLogger::builder()
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
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'payment-service')
            ->withRedactions(
                GenericRedaction::under(
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
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'error-service')
            ->withRedactions(GenericRedaction::replacing(pattern: '/\d{11}/', replacement: '[REDACTED]'))
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
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'payment-service')
            ->withRedactions(
                GenericRedaction::under(
                    parent: 'address',
                    redaction: GenericRedaction::keeping(fields: ['city', 'state'], mask: Mask::fixed(length: 3))
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
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'payment-service')
            ->withRedactions(
                GenericRedaction::under(
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
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'auth-service')
            ->withRedactions(SecretRedaction::default())
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
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'error-service')
            ->withRedactions(GenericRedaction::removing(fields: ['trace', '*_token']))
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
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'payment-service')
            ->withRedactions(
                GenericRedaction::masking(
                    mask: Mask::preservingSeparators(),
                    fields: ['postal_code', 'reference', 'coordinates'],
                    visibility: Visibility::edges(prefixLength: 3)
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
        $logger = StreamLogger::builder()
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
        $this->expectException(MalformedRedactionPattern::class);

        /** @And the message names the offending pattern */
        $this->expectExceptionMessage('Pattern is not a valid regular expression: /[unclosed/.');

        /** @When building a redaction from a pattern the engine cannot compile */
        GenericRedaction::replacing(pattern: '/[unclosed/', replacement: '[REDACTED]');
    }

    public function testRedactWhenPrefixIsNegativeThenThrowsNegativeLength(): void
    {
        /** @Then an exception describing the rejected configuration is raised */
        $this->expectException(NegativeVisibleLength::class);

        /** @And the message names both visible lengths */
        $this->expectExceptionMessage('Visible length cannot be negative, got prefix -1 and suffix 4.');

        /** @When building a redaction that leaves a negative number of leading characters visible */
        GenericRedaction::masking(
            mask: Mask::proportional(),
            fields: ['phone'],
            visibility: Visibility::edges(prefixLength: -1, suffixLength: 4)
        );
    }

    public function testRedactWhenSuffixIsNegativeThenThrowsNegativeLength(): void
    {
        /** @Then an exception describing the rejected configuration is raised */
        $this->expectException(NegativeVisibleLength::class);

        /** @And the message names both visible lengths */
        $this->expectExceptionMessage('Visible length cannot be negative, got prefix 2 and suffix -1.');

        /** @When building a redaction that leaves a negative number of trailing characters visible */
        GenericRedaction::masking(
            mask: Mask::proportional(),
            fields: ['phone'],
            visibility: Visibility::edges(prefixLength: 2, suffixLength: -1)
        );
    }

    public function testRedactWhenVisibleWordsHaveNoPrefixThenMasksEveryWordHead(): void
    {
        /** @Given a structured logger masking each word down to its last character */
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'user-service')
            ->withRedactions(
                GenericRedaction::masking(
                    mask: Mask::proportional(),
                    fields: ['name'],
                    visibility: Visibility::words(suffixLength: 1)
                )
            )
            ->build();

        /** @When logging with a name made of several words */
        $logger->info(message: 'user.created', context: ['name' => 'Gustavo Freze']);

        /** @Then every word keeps only its trailing character */
        self::assertStringContainsString('data={"name":"******o ****e"}', $this->logStream->contents());
    }

    public function testRedactWhenUrlCarriesAQueryStringThenOnlyThePathSurvives(): void
    {
        /** @Given a structured logger dropping every query string */
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'client-gateway')
            ->withRedactions(QueryStringRedaction::default())
            ->build();

        /** @When logging a route, a URL quoted inside a message, and a path with no query string */
        $logger->info(message: 'request.received', context: [
            'uri'     => '/v1/clients?document=12345678900&page=2',
            'route'   => '/v1/clients',
            'message' => 'GET https://api.example.com/v1/clients?token=abc123 failed'
        ]);

        /** @Then what follows the question mark is gone and the path is intact in all of them */
        self::assertStringContainsString(
            'data={"uri":"/v1/clients?","route":"/v1/clients",'
            . '"message":"GET https://api.example.com/v1/clients? failed"}',
            $this->logStream->contents()
        );
    }

    public function testRedactWhenFilterComparesThenOperandIsMaskedAndOperatorSurvives(): void
    {
        /** @Given a structured logger masking filter operands */
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'ledger')
            ->withRedactions(FilterExpressionRedaction::default())
            ->build();

        /** @When logging filters that compare, negate, and range over values */
        $logger->info(message: 'query.received', context: [
            'equality' => 'status==active;document==12345678900',
            'negation' => 'status!=canceled',
            'range'    => 'created_at=ge=2026-01-01,created_at=le=2026-02-01'
        ]);

        /** @Then every operand is masked and every field and operator stays readable */
        self::assertStringContainsString(
            'data={"equality":"status==********;document==********","negation":"status!=********",'
            . '"range":"created_at=ge=********,created_at=le=********"}',
            $this->logStream->contents()
        );
    }

    public function testRedactWhenFilterComparesAgainstAListThenTheWholeListIsMasked(): void
    {
        /** @Given a structured logger masking filter operands */
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'ledger')
            ->withRedactions(FilterExpressionRedaction::default())
            ->build();

        /** @When logging a filter whose operand is a parenthesized list */
        $logger->info(message: 'query.received', context: [
            'filter' => 'document=in=(12345678900,98765432100);status=out=(canceled,failed)'
        ]);

        /** @Then no value inside the parentheses survives, which is where a list leaks */
        self::assertStringContainsString(
            'data={"filter":"document=in=********;status=out=********"}',
            $this->logStream->contents()
        );
    }

    public function testRedactWhenValueCarriesNoComparisonThenLeavesItUntouched(): void
    {
        /** @Given a structured logger masking filter operands */
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'ledger')
            ->withRedactions(FilterExpressionRedaction::default())
            ->build();

        /** @When logging a value that carries no comparison at all */
        $logger->info(message: 'query.received', context: ['sort' => 'created_at desc']);

        /** @Then it travels unchanged, because there is no operand to mask */
        self::assertStringContainsString('data={"sort":"created_at desc"}', $this->logStream->contents());
    }

    public function testRedactWhenEmailPrefixIsGivenThenItOverridesTheDefaultWindow(): void
    {
        /** @Given a structured logger showing more of the local part than the default two characters */
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'user-service')
            ->withRedactions(EmailRedaction::from(fields: ['email'], visiblePrefixLength: 4))
            ->build();

        /** @When logging an address */
        $logger->info(message: 'user.created', context: ['email' => 'maria.silva@example.com']);

        /** @Then four characters of the local part survive, and not the two the default would keep */
        self::assertStringContainsString('data={"email":"mari*******@example.com"}', $this->logStream->contents());
    }

    public function testRedactWhenQueryParametersAreAllowedThenOnlyTheOthersAreMasked(): void
    {
        /** @Given a structured logger keeping the query parameters this API answers by */
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'ledger')
            ->withRedactions(QueryParametersRedaction::keeping(fields: ['sort', 'page*']))
            ->build();

        /** @When logging a request carrying an allowed parameter, a wildcard match, and one nobody declared */
        $logger->info(message: 'request.received', context: [
            'uri'              => '/v1/clients',
            'query_parameters' => ['sort' => 'created_at', 'page_size' => '20', 'document' => '12345678900'],
            'body'            => ['document' => '12345678900']
        ]);

        /** @Then the undeclared parameter is masked, the allowed ones survive, and nothing outside the branch moves */
        self::assertStringContainsString(
            'data={"uri":"/v1/clients","query_parameters":{"sort":"created_at","page_size":"20",'
            . '"document":"********"},"body":{"document":"12345678900"}}',
            $this->logStream->contents()
        );
    }

    public function testRedactWhenNoQueryParameterIsAllowedThenEveryOneOfThemIsMasked(): void
    {
        /** @Given a structured logger allowing no query parameter at all */
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'identity')
            ->withRedactions(QueryParametersRedaction::default())
            ->build();

        /** @When logging a request carrying two of them */
        $logger->info(message: 'request.received', context: [
            'query_parameters' => ['sort' => 'created_at', 'document' => '12345678900']
        ]);

        /** @Then both are masked, because a parameter is readable only when it is named */
        self::assertStringContainsString(
            'data={"query_parameters":{"sort":"********","document":"********"}}',
            $this->logStream->contents()
        );
    }

    public function testRedactWhenBirthDateTravelsUnderAnotherNameThenTheDefaultCoversIt(): void
    {
        /** @Given a structured logger with the default birth date redaction */
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'payment-service')
            ->withRedactions(BirthDateRedaction::default())
            ->build();

        /** @When logging the same date under each name it travels by, next to a field that only looks alike */
        $logger->info(message: 'payer.registered', context: [
            'birth_date'    => '1990-07-21',
            'birthdate'     => '1990-07-21',
            'date_of_birth' => '1990-07-21',
            'birthplace'    => 'São Paulo',
            'plan'          => 'saas'
        ]);

        /** @Then the year and the separators survive in each of them, and the lookalike is untouched */
        self::assertStringContainsString(
            'data={"birth_date":"1990-**-**","birthdate":"1990-**-**","date_of_birth":"1990-**-**",'
            . '"birthplace":"São Paulo","plan":"saas"}',
            $this->logStream->contents()
        );
    }

    public function testRedactWhenBirthDateWindowIsGivenThenItOverridesTheDefault(): void
    {
        /** @Given a structured logger hiding the year as well */
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'payment-service')
            ->withRedactions(BirthDateRedaction::from(fields: ['birth_date'], visiblePrefixLength: 0))
            ->build();

        /** @When logging a payer carrying a date of birth */
        $logger->info(message: 'payer.registered', context: ['birth_date' => '1990-07-21']);

        /** @Then nothing of the date survives but its shape, and not the year the default would keep */
        self::assertStringContainsString('data={"birth_date":"****-**-**"}', $this->logStream->contents());
    }

    public function testRedactWhenPostalCodeThenKeepsTheLeadingCharactersOfTheRegion(): void
    {
        /** @Given a structured logger with postal code redaction over three shapes of code */
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'payment-service')
            ->withRedactions(PostalCodeRedaction::from(fields: ['postal_code', 'zip', 'outward']))
            ->build();

        /** @When logging an address carrying each of them */
        $logger->info(message: 'address.given', context: [
            'postal_code' => '01310-100',
            'zip'         => '94103',
            'outward'     => 'SW1A 1AA'
        ]);

        /** @Then each keeps the leading characters that name the region, with the separators preserved */
        self::assertStringContainsString(
            'data={"postal_code":"013**-***","zip":"941**","outward":"SW1* ***"}',
            $this->logStream->contents()
        );
    }

    public function testRedactWhenPostalCodeTravelsUnderAnotherNameThenTheDefaultStillCoversIt(): void
    {
        /** @Given a structured logger with the default postal code redaction */
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'payment-service')
            ->withRedactions(PostalCodeRedaction::default())
            ->build();

        /** @When logging the same value under each name it travels by, next to fields that only look alike */
        $logger->info(message: 'address.given', context: [
            'postal_code' => '01310-100',
            'postcode'    => '01310-100',
            'zip_code'    => '01310-100',
            'zip'         => 'invoices.zip',
            'area_code'   => '11',
            'city'        => 'São Paulo'
        ]);

        /** @Then every name of the postal code is masked past the region, and the lookalikes are not */
        self::assertStringContainsString(
            'data={"postal_code":"013**-***","postcode":"013**-***","zip_code":"013**-***",'
            . '"zip":"invoices.zip","area_code":"11","city":"São Paulo"}',
            $this->logStream->contents()
        );
    }

    public function testRedactWhenPostalCodeWindowIsGivenThenItOverridesTheDefault(): void
    {
        /** @Given a structured logger keeping more of the code than the default three characters */
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'payment-service')
            ->withRedactions(PostalCodeRedaction::from(fields: ['postal_code'], visiblePrefixLength: 5))
            ->build();

        /** @When logging an address */
        $logger->info(message: 'address.given', context: ['postal_code' => '01310-100']);

        /** @Then five characters survive, and not the three the default would keep */
        self::assertStringContainsString('data={"postal_code":"01310-***"}', $this->logStream->contents());
    }

    public function testRedactWhenFieldIsSpelledInAnotherCaseThenTheSameRuleCoversIt(): void
    {
        /** @Given a structured logger with the strategies that name their fields in snake case */
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'payment-service')
            ->withRedactions(SecretRedaction::default(), PostalCodeRedaction::default(), BirthDateRedaction::default())
            ->build();

        /** @When logging a payload that spells the same names in camel case and with an acronym */
        $logger->info(message: 'payer.registered', context: [
            'postalCode'     => '01310-100',
            'birthDate'      => '1990-07-21',
            'accessToken'    => 'at-1',
            'debitCardToken' => 'tok-1',
            'APIKey'         => 'ak-1',
            'plan'           => 'saas'
        ]);

        /** @Then every spelling of a covered name is masked, and the field nobody named survives */
        self::assertStringContainsString(
            'data={"postalCode":"013**-***","birthDate":"1990-**-**","accessToken":"********",'
            . '"debitCardToken":"********","APIKey":"********","plan":"saas"}',
            $this->logStream->contents()
        );
    }

    public function testRedactWhenPatternRequiresASeparatorThenTheBareFieldStaysOutOfIt(): void
    {
        /** @Given a structured logger covering only field names that carry a qualifier */
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'user-service')
            ->withRedactions(NameRedaction::from(fields: ['*_name']))
            ->build();

        /** @When logging the qualified name in two spellings, next to the bare one */
        $logger->info(message: 'user.created', context: [
            'client_name' => 'Maria Silva',
            'clientName'  => 'Maria Silva',
            'name'        => 'Maria Silva'
        ]);

        /** @Then both spellings of the qualified name are masked and the bare one is not */
        self::assertStringContainsString(
            'data={"client_name":"Ma*********","clientName":"Ma*********","name":"Maria Silva"}',
            $this->logStream->contents()
        );
    }

    public function testRedactWhenSecretFieldsFollowTheNamingConventionThenEachIsMasked(): void
    {
        /** @Given a structured logger with the default secret redaction */
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'identity')
            ->withRedactions(SecretRedaction::default())
            ->build();

        /** @When logging fields that carry credentials under the conventional names */
        $logger->info(message: 'session.started', context: [
            'access_token'  => 'eyJhbGciOi',
            'client_secret' => 'super-secret',
            'authorization' => 'Bearer abc123',
            'credentials'   => 'user:password',
            'private_key'   => '-----BEGIN',
            'api_key'       => 'ak_live_1',
            'role'          => 'owner'
        ]);

        /** @Then each of them is masked to the same width and the field that is not a secret survives */
        self::assertStringContainsString(
            'data={"access_token":"********","client_secret":"********","authorization":"********",'
            . '"credentials":"********","private_key":"********","api_key":"********","role":"owner"}',
            $this->logStream->contents()
        );
    }

    public function testRedactWhenAllowListAndNullValueThenLeavesItUntouched(): void
    {
        /** @Given a structured logger allowing only the city field */
        $logger = StreamLogger::builder()
            ->withStream(stream: $this->logStream->handle())
            ->withComponent(component: 'payment-service')
            ->withRedactions(GenericRedaction::keeping(fields: ['city'], mask: Mask::fixed(length: 3)))
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

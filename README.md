# Logger

[![License](https://img.shields.io/badge/license-MIT-green)](https://github.com/tiny-blocks/logger/blob/main/LICENSE)

* [Overview](#overview)
* [Installation](#installation)
* [How to use](#how-to-use)
    + [Basic logging](#basic-logging)
    + [Correlation tracking](#correlation-tracking)
        - [At creation time](#at-creation-time)
        - [Derived from an existing logger](#derived-from-an-existing-logger)
    + [Minimum log level](#minimum-log-level)
    + [Sensitive data redaction](#sensitive-data-redaction)
        - [Strategy catalog](#strategy-catalog)
        - [Choosing a mask](#choosing-a-mask)
        - [Choosing what stays visible](#choosing-what-stays-visible)
        - [Field name patterns](#field-name-patterns)
        - [Document redaction](#document-redaction)
        - [Email redaction](#email-redaction)
        - [Phone redaction](#phone-redaction)
        - [Name redaction](#name-redaction)
        - [Birth date redaction](#birth-date-redaction)
        - [Postal code redaction](#postal-code-redaction)
        - [Secret redaction](#secret-redaction)
        - [Query string redaction](#query-string-redaction)
        - [Query parameters redaction](#query-parameters-redaction)
        - [Filter expression redaction](#filter-expression-redaction)
        - [Masking any field](#masking-any-field)
        - [Keeping an allow list](#keeping-an-allow-list)
        - [Removing fields](#removing-fields)
        - [Rewriting matched text](#rewriting-matched-text)
        - [Scoping to one branch](#scoping-to-one-branch)
        - [Composing multiple redactions](#composing-multiple-redactions)
        - [Custom redaction](#custom-redaction)
    + [Custom log template](#custom-log-template)
    + [Metrics](#metrics)
        - [The telemetry logger](#the-telemetry-logger)
        - [Emitting a metric](#emitting-a-metric)
        - [Refusing unbounded dimensions](#refusing-unbounded-dimensions)
        - [Refusing a redacted dimension](#refusing-a-redacted-dimension)
    + [Testing with the in-memory logger](#testing-with-the-in-memory-logger)
* [FAQ](#faq)
* [License](#license)
* [Contributing](#contributing)

<div id='overview'></div>

## Overview

Emits PSR-3 structured logs for PHP, with each entry carrying timestamp, component, correlation id, level, and a
structured data payload. Supports pluggable redactions for sensitive fields such as passwords, emails, phone numbers,
and identity documents, plus a severity threshold that drops quieter entries before they are rendered. It also emits
business metrics through the same stream, since a metric backend that reads them from a log record needs no second
transport. Built for consumption by log aggregators and SIEM pipelines in production environments.

Redaction is composable and split by how much the strategy already knows. The `Redactions` namespace holds the whole
concept: the `Redaction` contract, the `Mask` and `Visibility` vocabulary that decides what a masked value looks like,
the strategies named after the kind of data they protect, and `GenericRedaction`, which knows nothing until you point
it at something. The named ones are that same generic redaction with the decisions already made: which fields it
covers, how much of the value survives, which branch of the payload it reaches, and which patterns are masked wherever
they appear.

Metrics are split the same way, and for the same reason. The `Metrics` namespace holds what a measurement is regardless
of where it is published: `Metric`, the `MetricUnit` vocabulary in UCUM base units, and the `MetricFormat`
seam. Nested under it, a namespace per backend holds the implementation that writes for that one, which today is
`Metrics\CloudWatch` for the Amazon CloudWatch Embedded Metric Format. Nothing above the seam knows which backend reads
it.

<div id='installation'></div>

## Installation

```bash
composer require tiny-blocks/logger
```

<div id='how-to-use'></div>

## How to use

### Basic logging

Create a logger with `StreamLogger::builder()` and use the fluent builder to configure it. All PSR-3 log levels are
supported: `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, and `emergency`.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'order-service')
    ->build();

$logger->info(message: 'order.placed', context: ['orderId' => 42]);
```

Output (default template, written to `STDERR`):

```
2026-02-21T16:00:00+00:00 component=order-service correlation_id= level=INFO key=order.placed data={"orderId":42}
```

### Correlation tracking

A correlation ID can be attached at creation time or derived later using `withCorrelation`. The original instance is
never mutated.

#### At creation time

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Correlation;
use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withCorrelation(correlation: Correlation::from(correlationId: 'req-abc-123'))
    ->withComponent(component: 'payment-service')
    ->build();

$logger->info(message: 'payment.started', context: ['amount' => 100.50]);
```

#### Derived from an existing logger

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Correlation;
use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'payment-service')
    ->build();

$correlated = $logger->withCorrelation(correlation: Correlation::from(correlationId: 'req-abc-123'));

$correlated->info(message: 'payment.started', context: ['amount' => 100.50]);
```

### Minimum log level

Entries below the configured level are discarded before redaction and formatting run. The default is `LogLevel::DEBUG`,
which writes everything.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\LogLevel;
use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'order-service')
    ->withMinimumLevel(minimumLevel: LogLevel::WARNING)
    ->build();

$logger->info(message: 'order.placed');    # discarded
$logger->warning(message: 'stock.low');    # written
```

`LogLevel` also answers severity questions on its own:

```php
LogLevel::CRITICAL->isAtLeast(threshold: LogLevel::WARNING);
```

### Sensitive data redaction

Redaction is optional and configurable. Every strategy implements `Redaction`, and strategies compose: the logger
applies each one in the order it was registered.

#### Strategy catalog

A strategy either knows the kind of data it protects, or it is the generic one, pointed at whatever you name. The first
group is the second one with the decisions already made: which fields are covered, which mask renders them, and how much
of the value survives.

`TinyBlocks\Logger\Redactions`, by kind of data:

| Strategy                    | Protects                                        | Keeps visible                     |
|-----------------------------|-------------------------------------------------|-----------------------------------|
| `DocumentRedaction`         | A document number in a `document` field         | Trailing characters               |
| `EmailRedaction`            | An address in an `email` field                  | Local part prefix and full domain |
| `NameRedaction`             | A person or holder name in a `name` field       | Leading characters                |
| `BirthDateRedaction`        | A date of birth, under any of its names         | The year, and the date shape      |
| `PostalCodeRedaction`       | A postal or zip code, under any of its names    | The leading characters            |
| `PhoneRedaction`            | A number in a `phone` field                     | Trailing characters               |
| `SecretRedaction`           | Credentials, by the names they travel under     | Nothing                           |
| `QueryStringRedaction`      | Whatever rides after the `?` of any URL         | The path                          |
| `QueryParametersRedaction`  | The parsed query parameters of a request        | Only the parameters named         |
| `FilterExpressionRedaction` | The operand of every comparison in a filter     | The field and the operator        |

`GenericRedaction`, for everything else:

| Factory                            | What it does                                                            |
|------------------------------------|--------------------------------------------------------------------------|
| `GenericRedaction::under(...)`     | Applies another redaction only under one parent field                     |
| `GenericRedaction::keeping(...)`   | Masks every field except the ones named, with a fixed mask when none is given |
| `GenericRedaction::masking(...)`   | Masks the fields named, as much as the `Visibility` says                  |
| `GenericRedaction::removing(...)`  | Drops the fields named, so not even their presence reaches the log        |
| `GenericRedaction::replacing(...)` | Rewrites every match of a pattern, in every value, whatever the field is  |

#### Choosing a mask

Every strategy that masks takes a `Mask`, which decides how the hidden portion is rendered.

| Factory                        | Renders                                              | Reveals the original length |
|--------------------------------|------------------------------------------------------|-----------------------------|
| `Mask::proportional()`         | One mask character per hidden character              | Yes                         |
| `Mask::fixed()`                | The same number of characters every time, eight      | No                          |
| `Mask::preservingSeparators()` | Letters and digits masked, everything else preserved | Partially                   |

The strategies by kind of data use `Mask::proportional()`, so their output has the width of the original value. When
the width itself is sensitive, a password being the clearest case, reach for `Mask::fixed(...)`.

#### Choosing what stays visible

`Visibility` is the sibling decision: the `Mask` renders what is hidden, this one decides what is hidden at all.

| Factory                                                | Keeps visible                                    |
|--------------------------------------------------------|--------------------------------------------------|
| `Visibility::none()`                                   | Nothing                                          |
| `Visibility::edges(prefixLength: 2, suffixLength: 4)`  | A window at the head, at the tail, or at both     |
| `Visibility::words(prefixLength: 2)`                   | The same window on every word of the value        |
| `Visibility::localPart(prefixLength: 2)`               | The head of what precedes the `@`, plus the domain |

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\Mask;
use TinyBlocks\Logger\Redactions\GenericRedaction;
use TinyBlocks\Logger\Redactions\Visibility;

# "+5511999887766" → "+5511*****7766"
GenericRedaction::masking(
    mask: Mask::proportional(),
    fields: ['phone'],
    visibility: Visibility::edges(prefixLength: 5, suffixLength: 4)
);

# "01310-100" → "013**-***"
GenericRedaction::masking(
    mask: Mask::preservingSeparators(),
    fields: ['postal_code'],
    visibility: Visibility::edges(prefixLength: 3)
);
```

Omitting the visibility hides the value whole, which is what `Visibility::none()` says explicitly. A negative length is
rejected with `NegativeVisibleLength`, where the value is built and not later, when a log line is written.

#### Field name patterns

Every strategy accepts shell style wildcards in its field list, so a naming convention can be covered without listing
each field.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\NameRedaction;

NameRedaction::from(fields: ['*_name']);
# client_name "João Silva" → "Jo********"
# clientName  "João Silva" → "Jo********"
# name        "Maria"      → "Maria" (no match, the pattern requires the qualifier)
```

Field names are matched by name, not by spelling. A name declared as `postal_code` covers `postalCode`, `PostalCode`,
and `POSTAL_CODE`, because the key is read in the shape the pattern is written in before the two are compared. That is
what makes `SecretRedaction` cover an `accessToken` next to an `access_token`, and it never widens a pattern: `*_name`
still means a name with a qualifier in front of it, so a bare `name` stays out of it. What it does not do is invent a
separator that was never there, so a `postalcode` written as one word is a different name.

Values are matched at any depth, and numbers and other scalars are masked as text. When a matched field holds a list,
every element is masked, since a list carries values and nothing else. When it holds a map, the map is descended into
instead, because its keys carry their own meaning and masking them would destroy operational data. Use
`GenericRedaction::under(...)` to reach a specific key inside such a map.

#### Document redaction

Masks all characters except the last N (default: 3).

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\DocumentRedaction;
use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'kyc-service')
    ->withRedactions(DocumentRedaction::default())
    ->build();

$logger->info(message: 'kyc.verified', context: ['document' => '12345678900']);
# document → "*********00"
```

With custom fields and visible length:

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\DocumentRedaction;

DocumentRedaction::from(fields: ['cpf', 'cnpj'], visibleSuffixLength: 5);
# cpf "12345678900"     → "******78900"
# cnpj "12345678000199" → "*********00199"
```

#### Email redaction

Preserves the first N characters of the local part (default: 2) and the full domain.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\EmailRedaction;
use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'user-service')
    ->withRedactions(EmailRedaction::default())
    ->build();

$logger->info(message: 'user.registered', context: ['email' => 'john@example.com']);
# email → "jo**@example.com"
```

With custom fields:

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\EmailRedaction;

EmailRedaction::from(fields: ['email', 'contact_email', 'recoveryEmail'], visiblePrefixLength: 2);
```

#### Phone redaction

Masks all characters except the last N (default: 4).

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\PhoneRedaction;
use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'notification-service')
    ->withRedactions(PhoneRedaction::default())
    ->build();

$logger->info(message: 'sms.sent', context: ['phone' => '+5511999887766']);
# phone → "**********7766"
```

With custom fields:

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\PhoneRedaction;

PhoneRedaction::from(fields: ['phone', 'mobile', 'whatsapp'], visibleSuffixLength: 4);
```

#### Name redaction

Preserves the first N characters (default: 2) and masks the rest.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\NameRedaction;
use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'user-service')
    ->withRedactions(NameRedaction::default())
    ->build();

$logger->info(message: 'user.created', context: ['name' => 'Gustavo']);
# name → "Gu*****"
```

With custom fields and visible length:

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\NameRedaction;

NameRedaction::from(fields: ['name', 'full_name', 'firstName'], visiblePrefixLength: 3);
# "Gustavo"       → "Gus****"
# "Gustavo Freze" → "Gus**********"
# "Maria"         → "Mar**"
```

#### Birth date redaction

A full date of birth identifies a person almost as well as a document does, while the year alone answers most of what a
reader needs. The default keeps the four leading characters, which is the year of a date written as ISO 8601, and the
separators survive so the value still reads as a date. It covers `birth*date` and `date_of_birth`, which with the name
matching described above reaches `birth_date`, `birthDate`, `birthdate`, and `dateOfBirth` alike.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\BirthDateRedaction;
use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'payment-service')
    ->withRedactions(BirthDateRedaction::default())
    ->build();

$logger->info(message: 'payer.registered', context: [
    'birth_date' => '1990-07-21',
    'birthplace' => 'São Paulo',
    'plan'       => 'saas'
]);
# birth_date → "1990-**-**"
# birthplace → "São Paulo" (unchanged, another field entirely)
# plan       → "saas" (unchanged)
```

For a date written the other way around, name the window that keeps what you meant to keep:
`BirthDateRedaction::from(fields: ['birth_date'], visiblePrefixLength: 0)` hides the year as well.

#### Postal code redaction

A full postal code narrows to a street, and sometimes to a building. The leading characters name a region, which is
what tells one market from another, so the default keeps three of them. The same value travels under several names, so
the default covers `post*code` and `zip*code`, which with the name matching described above reaches `postal_code`,
`postalCode`, `postcode`, `zip_code`, `zipCode`, and `zipcode` alike. A bare `zip` is left out, since a field by that
name is as likely to carry an archive.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\PostalCodeRedaction;
use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'payment-service')
    ->withRedactions(PostalCodeRedaction::default())
    ->build();

$logger->info(message: 'address.given', context: [
    'postal_code' => '01310-100',
    'zipCode'     => '94103',
    'zip'         => 'invoices.zip',
    'area_code'   => '11'
]);
# postal_code → "013**-***"
# zipCode     → "941**"
# zip         → "invoices.zip" (unchanged, a bare zip is not a postal code)
# area_code   → "11" (unchanged, another code entirely)
```

Codes of other shapes keep the same rule, since the separators survive: a `SW1A 1AA` comes out as `SW1* ***`. For a
name outside the default set, `PostalCodeRedaction::from(fields: [...])` takes its own list.

#### Secret redaction

Masks credentials entirely, with a fixed mask, so neither the value nor its length reaches the log. The default field
list follows the naming conventions credentials travel under rather than a fixed set of names, which is what covers the
field nobody remembered to declare.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\SecretRedaction;
use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'auth-service')
    ->withRedactions(SecretRedaction::default())
    ->build();

$logger->info(message: 'session.started', context: [
    'access_token'  => 'eyJhbGciOi',
    'client_secret' => 's3cr3t!',
    'authorization' => 'Bearer abc123',
    'role'          => 'owner'
]);
# access_token  → "********"
# client_secret → "********"
# authorization → "********"
# role          → "owner" (unchanged)
```

The default covers `*token*`, `*secret*`, `*api_key*`, `*password*`, `*private_key*`, `credentials`, and
`authorization`. Name your own fields, and the mask length, with `SecretRedaction::from(...)`.

#### Query string redaction

A URL reaches the log as an ordinary string, so no field name covers what rides after the question mark: an identifier,
a token, a filter carrying a document. The path is what a reader needs to know which route was called, and it survives.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\QueryStringRedaction;
use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'client-gateway')
    ->withRedactions(QueryStringRedaction::default())
    ->build();

$logger->info(message: 'request.received', context: [
    'uri'     => '/v1/clients?document=12345678900&page=2',
    'route'   => '/v1/clients',
    'message' => 'GET https://api.example.com/v1/clients?token=abc123 failed'
]);
# uri     → "/v1/clients?"
# route   → "/v1/clients" (unchanged, there is nothing after a question mark)
# message → "GET https://api.example.com/v1/clients? failed"
```

#### Query parameters redaction

The parsed counterpart of the query string. A parameter carries whatever the caller sent, so naming what stays readable
removes the leak by omission: one added later is masked until it is allowed. It reaches the `query_parameters` branch,
which is where `tiny-blocks/http-logging` puts them.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\QueryParametersRedaction;
use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'ledger')
    ->withRedactions(QueryParametersRedaction::keeping(fields: ['sort', 'page*']))
    ->build();

$logger->info(message: 'request.received', context: [
    'uri'              => '/v1/clients',
    'query_parameters' => ['sort' => 'created_at', 'page_size' => '20', 'document' => '12345678900'],
    'body'             => ['document' => '12345678900']
]);
# query_parameters.sort      → "created_at" (allowed)
# query_parameters.page_size → "20" (allowed by the wildcard)
# query_parameters.document  → "********"
# body.document              → "12345678900" (untouched, another branch)
```

`QueryParametersRedaction::default()` allows none of them, which masks every parameter. For a payload carrying them
under another name, compose `GenericRedaction::under(...)` with `GenericRedaction::keeping(...)`.

#### Filter expression redaction

A filter arrives as one string, so the field names inside it are out of reach of any field based strategy, and the
operand is where the sensitive value sits. Written for the comparison syntax RSQL and FIQL share, including the
parenthesized list of an `=in=` comparison. What is asked stays readable, what is asked about does not.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\FilterExpressionRedaction;
use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'ledger')
    ->withRedactions(FilterExpressionRedaction::default())
    ->build();

$logger->info(message: 'query.received', context: [
    'equality' => 'status==active;document==12345678900',
    'range'    => 'created_at=ge=2026-01-01,created_at=le=2026-02-01',
    'list'     => 'document=in=(12345678900,98765432100)',
    'sort'     => 'created_at desc'
]);
# equality → "status==********;document==********"
# range    → "created_at=ge=********,created_at=le=********"
# list     → "document=in=********"
# sort     → "created_at desc" (unchanged, nothing is compared)
```

#### Masking any field

The generic strategy for a field the library knows nothing about. Name the fields, the mask, and how much survives.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\GenericRedaction;
use TinyBlocks\Logger\Redactions\Mask;
use TinyBlocks\Logger\StreamLogger;
use TinyBlocks\Logger\Redactions\Visibility;

$logger = StreamLogger::builder()
    ->withComponent(component: 'payment-service')
    ->withRedactions(
        GenericRedaction::masking(
            mask: Mask::proportional(),
            fields: ['birth_date'],
            visibility: Visibility::edges(prefixLength: 4)
        ),
        GenericRedaction::masking(mask: Mask::fixed(), fields: ['ip', 'user_agent', 'session_id'])
    )
    ->build();

$logger->info(message: 'payer.registered', context: [
    'birth_date' => '1990-07-21',
    'ip'         => '10.0.0.1',
    'route'      => '/v1/payers'
]);
# birth_date → "1990******"
# ip         → "********"
# route      → "/v1/payers" (unchanged)
```

With `Visibility::words(...)`, each word is masked on its own, so a full name keeps its shape instead of collapsing
into a single run:

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\Mask;
use TinyBlocks\Logger\Redactions\GenericRedaction;
use TinyBlocks\Logger\Redactions\Visibility;

GenericRedaction::masking(
    mask: Mask::fixed(length: 3),
    fields: ['name', 'holder'],
    visibility: Visibility::words(prefixLength: 2)
);
# "Gustavo Freze" → "Gu*** Fr***"
```

#### Keeping an allow list

The inverse of naming what to hide. Naming what to keep removes the leak by omission, since a field added later is
masked until it is explicitly allowed. Omitting the mask hides every other field behind a fixed one, which is the safe
choice when the width itself may carry meaning. Pair it with `GenericRedaction::under(...)` to apply the allow list to
one branch of the payload instead of all of it.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\GenericRedaction;
use TinyBlocks\Logger\Redactions\Mask;
use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'payment-service')
    ->withRedactions(
        GenericRedaction::under(
            parent: 'address',
            redaction: GenericRedaction::keeping(fields: ['city', 'state'], mask: Mask::fixed(length: 3))
        )
    )
    ->build();

$logger->info(message: 'payment.created', context: [
    'address' => ['city' => 'São Paulo', 'state' => 'BR-SP', 'street' => 'Rua Example', 'number' => '123']
]);
# address.city   → "São Paulo" (allowed)
# address.state  → "BR-SP" (allowed)
# address.street → "***"
# address.number → "***"
```

#### Removing fields

Drops the field instead of masking it. Preferred when the field carries no diagnostic value at all, so nothing about the
original reaches the log, not even its presence.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\GenericRedaction;
use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'error-service')
    ->withRedactions(GenericRedaction::removing(fields: ['trace', '*_token']))
    ->build();

$logger->error(message: 'request.failed', context: [
    'message' => 'boom',
    'trace'   => '#0 /app/src/Handler.php(42)',
    'nested'  => ['access_token' => 'at-1', 'id' => '7']
]);
# data={"message":"boom","nested":{"id":"7"}}
```

#### Rewriting matched text

Field based strategies cannot reach sensitive data embedded in free text: an exception message quoting a document, a
stack trace, a URI carrying a query string. Matching on the value instead of the key closes that gap.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\GenericRedaction;
use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'error-service')
    ->withRedactions(GenericRedaction::replacing(pattern: '/\d{11}/', replacement: '[REDACTED]'))
    ->build();

$logger->error(message: 'request.failed', context: [
    'message' => 'Document 12345678901 is invalid.',
    'uri'     => '/clients/12345678901'
]);
# message → "Document [REDACTED] is invalid."
# uri     → "/clients/[REDACTED]"
```

A pattern the regular expression engine rejects raises `MalformedRedactionPattern` at configuration time, not at the
first log call.

#### Scoping to one branch

Field names repeat across a payload with different meanings. A `value` under `document` is an identity document, a
`value` under `metadata` is an operational marker. Scoping a redaction to its parent keeps the first masked and the
second readable.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\DocumentRedaction;
use TinyBlocks\Logger\Redactions\GenericRedaction;
use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'payment-service')
    ->withRedactions(
        GenericRedaction::under(
            parent: 'document',
            redaction: DocumentRedaction::from(fields: ['value'], visibleSuffixLength: 2)
        )
    )
    ->build();

$logger->info(message: 'payment.created', context: [
    'document' => ['type' => 'cpf', 'value' => '12345678901'],
    'metadata' => ['value' => 'operational-marker']
]);
# document.value → "*********01"
# metadata.value → "operational-marker" (unchanged)
```

The scope applies at any depth, so a `document` nested under `charge` is covered by the same rule.

#### Composing multiple redactions

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\DocumentRedaction;
use TinyBlocks\Logger\Redactions\EmailRedaction;
use TinyBlocks\Logger\Redactions\NameRedaction;
use TinyBlocks\Logger\Redactions\PhoneRedaction;
use TinyBlocks\Logger\Redactions\SecretRedaction;
use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'user-service')
    ->withRedactions(
        NameRedaction::default(),
        EmailRedaction::default(),
        PhoneRedaction::default(),
        DocumentRedaction::default(),
        SecretRedaction::default()
    )
    ->build();

$logger->info(message: 'user.registered', context: [
    'name'         => 'John',
    'email'        => 'john@example.com',
    'phone'        => '+5511999887766',
    'status'       => 'active',
    'document'     => '12345678900',
    'access_token' => 'at-1'
]);
# name         → "Jo**"
# email        → "jo**@example.com"
# phone        → "**********7766"
# status       → "active" (unchanged)
# document     → "*********00"
# access_token → "********"
```

#### Custom redaction

Implement the `Redaction` interface to create your own strategy:

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\Redaction;

final readonly class ReversedRedaction implements Redaction
{
    public function redact(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->redact(payload: $value);
                continue;
            }

            if ($key === 'signature' && is_string($value)) {
                $payload[$key] = strrev($value);
            }
        }

        return $payload;
    }
}
```

Then add it to the logger:

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'auth-service')
    ->withRedactions(new ReversedRedaction())
    ->build();

$logger->info(message: 'user.logged_in', context: ['signature' => 'abc123']);
# signature → "321cba"
```

### Custom log template

The default output template is:

```
%s component=%s correlation_id=%s level=%s key=%s data=%s
```

You can replace it with any `sprintf` compatible template that accepts six string arguments (timestamp, component,
correlationId, level, key, data):

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\StreamLogger;

$logger = StreamLogger::builder()
    ->withComponent(component: 'custom-service')
    ->withTemplate(template: "[%s] %s | %s | %s | %s | %s\n")
    ->build();

$logger->info(message: 'custom.event', context: ['value' => 42]);
# [2026-02-21T16:00:00+00:00] custom-service |  | INFO | custom.event | {"value":42}
```

### Metrics

A `Metric` is a measurement expressed without reference to any metric backend: a namespace, a name, a value in a
`MetricUnit`, the dimensions it is broken down by, and the fields that ride along in the same record. `MetricUnit`
carries UCUM symbols and base units only, which is what the neutral specifications prescribe: a duration is `0.5` in
seconds and never `500` in a millisecond unit that does not exist here. A name, a namespace, a dimension name, or a
field name that carries nothing is refused with `BlankMetricIdentifier`, and one name used by a field and by a
dimension at once is refused with `DuplicateMetricIdentifier`, because both land at the root of the same record and one
would silently replace the other. What separates a dimension from a field is cost, not shape. A backend indexes each
distinct set of dimension values as a series of its own and bills it, while a field stays queryable without multiplying
anything, which is where a granular attribute belongs.

Rendering is a `MetricFormat` concern, and each implementation lives in the folder of the backend it writes for.
`Metrics\CloudWatch\EmbeddedMetricFormat` is the one for the Amazon CloudWatch Embedded Metric Format, which is a
CloudWatch specification and not a neutral one, so the namespace says whose it is. EMF publishes a metric by writing a
log record that carries the metric inside it, which spares the publish API call but never the custom metric tariff. The
`_aws` envelope and the spelling of every unit belong to that implementation alone: `Metric` and `MetricUnit` carry
neither, so a second format is a new class and not a change to what emits.

#### The telemetry logger

`TelemetryLogger` is the logger that measures as well as writes. It is built once around the format that renders every
metric, and from there `metric()` sits next to the PSR-3 methods:

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Correlation;
use TinyBlocks\Logger\Metrics\CloudWatch\EmbeddedMetricFormat;
use TinyBlocks\Logger\Metrics\Metric;
use TinyBlocks\Logger\TelemetryLogger;

$logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
    ->withComponent(component: 'schedule')
    ->withCorrelation(correlation: Correlation::from(correlationId: 'req-abc-123'))
    ->build();

$logger->info(message: 'appointment.created', context: ['appointment_id' => 42]);
$logger->metric(metric: Metric::of(name: 'AppointmentCreated', namespace: 'Acme/Schedule'));
```

Two records reach the stream, one per line, and they never share one:

```
2026-02-21T16:00:00+00:00 component=schedule correlation_id=req-abc-123 level=INFO key=appointment.created
data={"appointment_id":42}
{"component":"schedule","correlation_id":"req-abc-123","AppointmentCreated":1,"_aws":{"Timestamp":1771891200000,
"CloudWatchMetrics":[{"Namespace":"Acme/Schedule","Dimensions":[[]],"Metrics":[{"Name":"AppointmentCreated",
"Unit":"Count"}]}]}}
```

An entry is narrative and carries a severity; a metric is a measurement and carries none, so a threshold raised to
quiet the logs does not stop a series. The logger hangs `component` and `correlation_id` on the metric by itself,
which is what lets a series be read back against the entries around it. Every redaction configured on the builder
reaches both records. Build a [`StreamLogger`](#basic-logging) instead when nothing is measured: there the method does
not exist on the type. See [FAQ 05](#faq) for why the two records are two lines.

#### Emitting a metric

A metric is a counter of one until it is told otherwise. Fields ride along in the record, dimensions break the series
down:

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Metrics\CloudWatch\EmbeddedMetricFormat;
use TinyBlocks\Logger\Metrics\Metric;
use TinyBlocks\Logger\TelemetryLogger;

$logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
    ->withComponent(component: 'schedule')
    ->build();

$metric = Metric::of(name: 'OfferAccepted', namespace: 'Acme/Waitlist')
    ->withField(name: 'tenant_id', value: 'abc-123')
    ->withDimension(name: 'plan', value: 'saas');

$logger->metric(metric: $metric);
# {"tenant_id":"abc-123","component":"schedule","correlation_id":"","plan":"saas","OfferAccepted":1,
# "_aws":{"Timestamp":1771891200000,"CloudWatchMetrics":[{"Namespace":"Acme/Waitlist","Dimensions":[["plan"]],
# "Metrics":[{"Name":"OfferAccepted","Unit":"Count"}]}]}}
```

A measurement that is not a count derives from the same factory:

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Metrics\CloudWatch\EmbeddedMetricFormat;
use TinyBlocks\Logger\Metrics\Metric;
use TinyBlocks\Logger\Metrics\MetricUnit;
use TinyBlocks\Logger\TelemetryLogger;

$logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
    ->withComponent(component: 'schedule')
    ->build();

$metric = Metric::of(name: 'OfferResponseTime', namespace: 'Acme/Waitlist')
    ->withUnit(unit: MetricUnit::SECONDS)
    ->withValue(value: 12.5);

$logger->metric(metric: $metric);
# {"component":"schedule","correlation_id":"","OfferResponseTime":12.5,"_aws":{"Timestamp":1771891200000,
# "CloudWatchMetrics":[{"Namespace":"Acme/Waitlist","Dimensions":[[]],"Metrics":[{"Name":"OfferResponseTime",
# "Unit":"Seconds"}]}]}}
```

#### Refusing unbounded dimensions

Which dimension names take an unbounded number of values is a property of the emitting domain, so the format refuses
none until it is told. A name declared unbounded is refused with `UnboundedDimension`, and the message points at the
field that carries the same value without creating a series:

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Metrics\CloudWatch\EmbeddedMetricFormat;
use TinyBlocks\Logger\Metrics\Metric;
use TinyBlocks\Logger\TelemetryLogger;

$format = EmbeddedMetricFormat::default()->withUnboundedDimensions('tenant_id', 'user_id');

$logger = TelemetryLogger::builder(format: $format)
    ->withComponent(component: 'schedule')
    ->build();

$metric = Metric::of(name: 'OfferAccepted', namespace: 'Acme/Waitlist')
    ->withDimension(name: 'tenant_id', value: 'abc-123');

$logger->metric(metric: $metric);
# UnboundedDimension: The dimension <tenant_id> was declared unbounded. Emit it as a field of the same record instead.
```

#### Refusing a redacted dimension

A field covered by a redaction reaches the metric line masked, exactly as it reaches the log line. A dimension cannot
be masked the same way and still be a dimension: its value becomes part of the series identity in the backend index,
where no redaction and no log retention reach it. Declaring one under a name a redaction covers is refused with
`RedactedDimension`:

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Metrics\CloudWatch\EmbeddedMetricFormat;
use TinyBlocks\Logger\Metrics\Metric;
use TinyBlocks\Logger\Redactions\DocumentRedaction;
use TinyBlocks\Logger\TelemetryLogger;

$logger = TelemetryLogger::builder(format: EmbeddedMetricFormat::default())
    ->withComponent(component: 'identity')
    ->withRedactions(DocumentRedaction::default())
    ->build();

$metric = Metric::of(name: 'SignInFailed', namespace: 'Acme/Identity')
    ->withDimension(name: 'document', value: '12345678900');

$logger->metric(metric: $metric);
# RedactedDimension: The dimension <document> is covered by a redaction. A dimension value reaches the metric index
# of the backend, where no redaction and no log retention can reach it. Emit it as a field instead.
```

### Testing with the in-memory logger

`InMemoryLogger` records entries instead of writing them, so a test asserts on what was logged rather than on how it was
rendered. Payloads are kept exactly as received, with no redaction and no formatting. Loggers derived through
`withCorrelation` record into the same store, so entries are visible from either instance.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\InMemoryLogger;

$logger = InMemoryLogger::create();

$logger->info(message: 'user.created', context: ['document' => '12345678900']);

$logger->entries()->toArray();
# [['key' => 'user.created', 'level' => 'INFO', 'payload' => ['document' => '12345678900'], 'correlation' => null]]
```

<div id='faq'></div>

## FAQ

### 01. Why does a masked value keep the width of the original?

Only when the strategy asks for it. The strategies by kind of data use `Mask::proportional()`, which emits one
character per hidden character, because a support engineer reading `**********7766` can still tell a mobile number from
a short extension. That convenience costs information: the width of the output is the width of the input.

When the width itself is sensitive, a password being the clearest case, use `Mask::fixed(...)`, which emits the same run
every time and is what `SecretRedaction` already does.

### 02. Why scope a redaction to a parent field instead of listing field names?

Because field names are not unique. A payload carries `code` as a verification code in one branch and as an error code
in another, `number` as a card number in one place and as a page number in a query string in another. A flat list of
field names cannot tell them apart, so masking the sensitive one also destroys the diagnostic one.

`GenericRedaction::under(...)` restricts a strategy to the sub payloads under a given parent, the smallest piece of
context needed to disambiguate.

### 03. When should a field be dropped instead of masked?

When the masked value would still be noise. A stack trace, a payment brcode, or a raw user agent tells a reader nothing
once masked, and it still costs bytes in every aggregator downstream. `GenericRedaction::removing(...)` drops the key.

Masking stays the right answer whenever the shape of the value carries meaning, for example knowing that a document was
present and ended in `900`.

### 04. Why does the logger match field names with wildcards?

Because leaks happen by omission, not by mistake. A payload gains a `client_name` next to the `name` that was already
covered, and nothing in the configuration reacts. Patterns such as `*_name` or `*token*` follow the naming convention
rather than the current field list.

For payloads where even that is not enough, `GenericRedaction::keeping(...)` inverts the default: everything is masked
until it is named.

### 05. Why do metrics need a logger of their own?

They do not need a logger of their own. They need a record of their own, and `TelemetryLogger` writes both on the same
stream without mixing them.

The reason is the shape of the line. An EMF record has to reach the agent as a JSON object at the root, and an entry
has to reach a human with the timestamp, the component, the correlation id, the level and the key in front of it. Fold
one into the other and both stop working:

```
# a metric folded into an entry: the _aws key is no longer at the root, so the agent reads no metric
2026-02-21T16:00:00+00:00 component=schedule correlation_id=req-abc-123 level=INFO key=metric
data={"AppointmentCompleted":1,"_aws":{...}}

# an entry rendered as a bare payload, which is what a metric line looks like: nothing says what happened, or when
{"appointment_id":"abc-123"}
```

So the logger writes two lines, and what stitches them back together is what it hangs on the metric by itself: the
`component` and the `correlation_id` of the entries around it. EMF ignores a root key it does not know, and Logs
Insights can still query it.

The severity threshold follows the same split. It is a property of entries, so raising it to quiet the logs never stops
a series: a measurement carries no severity to compare against.

<div id='license'></div>

## License

Logger is licensed under [MIT](LICENSE).

<div id='contributing'></div>

## Contributing

Please follow the [contributing guidelines](https://github.com/tiny-blocks/tiny-blocks/blob/main/CONTRIBUTING.md) to
contribute to the project.

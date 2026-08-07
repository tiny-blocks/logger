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
        - [Field name patterns](#field-name-patterns)
        - [Document redaction](#document-redaction)
        - [Email redaction](#email-redaction)
        - [Phone redaction](#phone-redaction)
        - [Password redaction](#password-redaction)
        - [Name redaction](#name-redaction)
        - [Visible edges redaction](#visible-edges-redaction)
        - [Wordwise redaction](#wordwise-redaction)
        - [Full mask redaction](#full-mask-redaction)
        - [Scoped redaction](#scoped-redaction)
        - [Allowed fields redaction](#allowed-fields-redaction)
        - [Removed fields redaction](#removed-fields-redaction)
        - [Pattern redaction](#pattern-redaction)
        - [Composing multiple redactions](#composing-multiple-redactions)
        - [Custom redaction](#custom-redaction)
    + [Custom log template](#custom-log-template)
    + [Testing with the in-memory logger](#testing-with-the-in-memory-logger)
* [FAQ](#faq)
* [License](#license)
* [Contributing](#contributing)

<div id='overview'></div>

## Overview

Emits PSR-3 structured logs for PHP, with each entry carrying timestamp, component, correlation id, level, and a
structured data payload. Supports pluggable redactions for sensitive fields such as passwords, emails, phone numbers,
and identity documents, plus a severity threshold that drops quieter entries before they are rendered. Built for
consumption by log aggregators and SIEM pipelines in production environments.

Redaction is composable and split in two. The `Redactions` namespace holds strategies named after the kind of data they
protect, ready to use. Nested under it, `Redactions\Rules` holds the general ones, named after the rule they apply: how
much of a value stays visible, which branch of the payload a redaction reaches, which fields survive at all, and which
patterns are masked wherever they appear.

<div id='installation'></div>

## Installation

```bash
composer require tiny-blocks/logger
```

<div id='how-to-use'></div>

## How to use

### Basic logging

Create a logger with `StructuredLogger::create()` and use the fluent builder to configure it. All PSR-3 log levels are
supported: `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, and `emergency`.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\StructuredLogger;

$logger = StructuredLogger::create()
    ->withComponent(component: 'order-service')
    ->build();

$logger->info(message: 'order.placed', context: ['orderId' => 42]);
```

Output (default template, written to `STDERR`):

```
2026-02-21T16:00:00+00:00 component=order-service correlation_id= level=INFO key=order.placed data={"orderId":42}
```

### Correlation tracking

A correlation ID can be attached at creation time or derived later using `withContext`. The original instance is never
mutated.

#### At creation time

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\LogContext;
use TinyBlocks\Logger\StructuredLogger;

$logger = StructuredLogger::create()
    ->withContext(context: LogContext::from(correlationId: 'req-abc-123'))
    ->withComponent(component: 'payment-service')
    ->build();

$logger->info(message: 'payment.started', context: ['amount' => 100.50]);
```

#### Derived from an existing logger

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\LogContext;
use TinyBlocks\Logger\StructuredLogger;

$logger = StructuredLogger::create()
    ->withComponent(component: 'payment-service')
    ->build();

$contextual = $logger->withContext(context: LogContext::from(correlationId: 'req-abc-123'));

$contextual->info(message: 'payment.started', context: ['amount' => 100.50]);
```

### Minimum log level

Entries below the configured level are discarded before redaction and formatting run. The default is `LogLevel::DEBUG`,
which writes everything.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\LogLevel;
use TinyBlocks\Logger\StructuredLogger;

$logger = StructuredLogger::create()
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

Strategies live in two namespaces. `Redactions` holds the ready-made ones, each named after the kind of data it
protects. `Rules` holds the general ones, each named after the rule it applies, for whatever the ready-made set does not
cover.

`TinyBlocks\Logger\Redactions`, by kind of data:

| Strategy            | Keeps visible                     | Default field |
|---------------------|-----------------------------------|---------------|
| `DocumentRedaction` | Trailing characters               | `document`    |
| `EmailRedaction`    | Local part prefix and full domain | `email`       |
| `PhoneRedaction`    | Trailing characters               | `phone`       |
| `NameRedaction`     | Leading characters                | `name`        |
| `PasswordRedaction` | Nothing                           | `password`    |

`TinyBlocks\Logger\Redactions\Rules`, by rule:

| Strategy                 | Keeps visible                     | Typical use                                             |
|--------------------------|-----------------------------------|---------------------------------------------------------|
| `VisibleEdgesRedaction`  | Leading and trailing characters   | Any value needing a custom window                       |
| `WordwiseRedaction`      | The edges of every word           | Full names, multi word labels                           |
| `FullMaskRedaction`      | Nothing                           | Secrets, free text, addresses, user agents              |
| `ScopedRedaction`        | Delegates within one parent field | Field names that repeat with different meanings         |
| `AllowedFieldsRedaction` | Only the listed fields            | Deny by default, so a new field is masked until allowed |
| `RemovedFieldsRedaction` | Nothing, the field itself is gone | Stack traces, payment codes                             |
| `PatternRedaction`       | Everything except the matches     | Sensitive data embedded in free text                    |

#### Choosing a mask

The general primitives take a `Mask`, which decides how the hidden portion is rendered.

| Factory                        | Renders                                              | Reveals the original length |
|--------------------------------|------------------------------------------------------|-----------------------------|
| `Mask::proportional()`         | One mask character per hidden character              | Yes                         |
| `Mask::fixed(length: 8)`       | The same number of characters every time             | No                          |
| `Mask::preservingSeparators()` | Letters and digits masked, everything else preserved | Partially                   |

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Mask;
use TinyBlocks\Logger\Redactions\Rules\VisibleEdgesRedaction;

# "+5511999887766" → "+5511*****7766"
VisibleEdgesRedaction::from(
    mask: Mask::proportional(),
    fields: ['phone'],
    visiblePrefixLength: 5,
    visibleSuffixLength: 4
);

# "01310-100" → "013**-***"
VisibleEdgesRedaction::from(
    mask: Mask::preservingSeparators(),
    fields: ['postal_code'],
    visiblePrefixLength: 3
);
```

The field-specific strategies use `Mask::proportional()`, so their output has the width of the original value. When the
width itself is sensitive, reach for a primitive with `Mask::fixed(...)`.

#### Field name patterns

Every strategy accepts shell style wildcards in its field list, so a naming convention can be covered without listing
each field.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\NameRedaction;

NameRedaction::from(fields: ['*_name'], visiblePrefixLength: 2);
# client_name "João Silva" → "Jo********"
# name        "Maria"      → "Maria" (no match, the pattern requires the underscore)
```

Values are matched at any depth, and numbers and other scalars are masked as text. When a matched field holds a list,
every element is masked, since a list carries values and nothing else. When it holds a map, the map is descended into
instead, because its keys carry their own meaning and masking them would destroy operational data. Use
`ScopedRedaction` to reach a specific key inside such a map.

#### Document redaction

Masks all characters except the last N (default: 3).

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\StructuredLogger;
use TinyBlocks\Logger\Redactions\DocumentRedaction;

$logger = StructuredLogger::create()
    ->withComponent(component: 'kyc-service')
    ->withRedactions(DocumentRedaction::default())
    ->build();

$logger->info(message: 'kyc.verified', context: ['document' => '12345678900']);
# document → "********900"
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

use TinyBlocks\Logger\StructuredLogger;
use TinyBlocks\Logger\Redactions\EmailRedaction;

$logger = StructuredLogger::create()
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

use TinyBlocks\Logger\StructuredLogger;
use TinyBlocks\Logger\Redactions\PhoneRedaction;

$logger = StructuredLogger::create()
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

#### Password redaction

Masks the entire value with a fixed-length mask (default: 8 characters). The original value's length is never revealed
in the output, preventing information leakage about password size.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\StructuredLogger;
use TinyBlocks\Logger\Redactions\PasswordRedaction;

$logger = StructuredLogger::create()
    ->withComponent(component: 'auth-service')
    ->withRedactions(PasswordRedaction::default())
    ->build();

$logger->info(message: 'login.attempt', context: ['password' => 's3cr3t!']);
# password → "********"

$logger->info(message: 'login.attempt', context: ['password' => '123']);
# password → "********" (same mask regardless of length)
```

With custom fields and fixed mask length:

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\PasswordRedaction;

PasswordRedaction::from(fields: ['password', 'secret', 'token'], fixedMaskLength: 12);
# "s3cr3t!"       → "************"
# "ab"            → "************"
# "long_password" → "************"
```

#### Name redaction

Preserves the first N characters (default: 2) and masks the rest.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\StructuredLogger;
use TinyBlocks\Logger\Redactions\NameRedaction;

$logger = StructuredLogger::create()
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

#### Visible edges redaction

The general primitive behind the field-specific strategies. Keeps a window at the head, at the tail, or at both ends.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Mask;
use TinyBlocks\Logger\StructuredLogger;
use TinyBlocks\Logger\Redactions\Rules\VisibleEdgesRedaction;

$logger = StructuredLogger::create()
    ->withComponent(component: 'payment-service')
    ->withRedactions(
        VisibleEdgesRedaction::from(
            mask: Mask::proportional(),
            fields: ['birth_date'],
            visiblePrefixLength: 4
        )
    )
    ->build();

$logger->info(message: 'payer.registered', context: ['birth_date' => '1990-07-21']);
# birth_date → "1990******"
```

A negative visible length is rejected with `NegativeVisibleLength`.

#### Wordwise redaction

Masks each word of the value on its own, so a full name stays readable as a shape instead of collapsing into a single
run of mask characters.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Mask;
use TinyBlocks\Logger\StructuredLogger;
use TinyBlocks\Logger\Redactions\Rules\WordwiseRedaction;

$logger = StructuredLogger::create()
    ->withComponent(component: 'user-service')
    ->withRedactions(
        WordwiseRedaction::from(
            mask: Mask::fixed(length: 3),
            fields: ['name', 'holder'],
            visiblePrefixLength: 2
        )
    )
    ->build();

$logger->info(message: 'user.created', context: ['name' => 'Gustavo Freze']);
# name → "Gu*** Fr***"
```

#### Full mask redaction

Masks the value entirely. Suited to anything that carries no operational meaning once logged, such as secrets, free
text, street addresses, and user agents.

It is the named counterpart of `VisibleEdgesRedaction` with no visible edge. Both produce the same output, and the
separate name exists so the intent reads at the call site, the same way `DocumentRedaction` and `PhoneRedaction` are
both suffix strategies under two names.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Mask;
use TinyBlocks\Logger\StructuredLogger;
use TinyBlocks\Logger\Redactions\Rules\FullMaskRedaction;

$logger = StructuredLogger::create()
    ->withComponent(component: 'audit-service')
    ->withRedactions(
        FullMaskRedaction::from(mask: Mask::fixed(length: 8), fields: ['ip', 'user_agent', 'session_id'])
    )
    ->build();

$logger->info(message: 'request.received', context: ['ip' => '10.0.0.1', 'route' => '/v1/users']);
# ip    → "********"
# route → "/v1/users" (unchanged)
```

`commonSecrets()` covers the field names that carry secrets across most systems (`*token*`, `*secret*`, `*api_key*`,
`*password*`, `*private_key*`, `credentials`, and `authorization`) with a fixed mask:

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redactions\Rules\FullMaskRedaction;

FullMaskRedaction::commonSecrets();
# access_token     → "********"
# client_secret    → "********"
# current_password → "********"
```

#### Scoped redaction

Field names repeat across a payload with different meanings. A `value` under `document` is an identity document, a
`value` under `metadata` is an operational marker. Scoping a redaction to its parent keeps the first masked and the
second readable.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\StructuredLogger;
use TinyBlocks\Logger\Redactions\DocumentRedaction;
use TinyBlocks\Logger\Redactions\Rules\ScopedRedaction;

$logger = StructuredLogger::create()
    ->withComponent(component: 'payment-service')
    ->withRedactions(
        ScopedRedaction::under(
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

#### Allowed fields redaction

The inverse of the other strategies, which name what to hide. Naming what to keep removes the leak by omission, since a
field added later is masked until it is explicitly allowed. Pair it with `ScopedRedaction` to apply the allow list to
one branch of the payload instead of all of it.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Mask;
use TinyBlocks\Logger\StructuredLogger;
use TinyBlocks\Logger\Redactions\Rules\AllowedFieldsRedaction;
use TinyBlocks\Logger\Redactions\Rules\ScopedRedaction;

$logger = StructuredLogger::create()
    ->withComponent(component: 'payment-service')
    ->withRedactions(
        ScopedRedaction::under(
            parent: 'address',
            redaction: AllowedFieldsRedaction::from(mask: Mask::fixed(length: 3), fields: ['city', 'state'])
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

#### Removed fields redaction

Drops the field instead of masking it. Preferred when the field carries no diagnostic value at all, so nothing about the
original reaches the log, not even its presence.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\StructuredLogger;
use TinyBlocks\Logger\Redactions\Rules\RemovedFieldsRedaction;

$logger = StructuredLogger::create()
    ->withComponent(component: 'error-service')
    ->withRedactions(RemovedFieldsRedaction::from(fields: ['trace', '*_token']))
    ->build();

$logger->error(message: 'request.failed', context: [
    'message' => 'boom',
    'trace'   => '#0 /app/src/Handler.php(42)',
    'nested'  => ['access_token' => 'at-1', 'id' => '7']
]);
# data={"message":"boom","nested":{"id":"7"}}
```

#### Pattern redaction

Field-based strategies cannot reach sensitive data embedded in free text: an exception message quoting a document, a
stack trace, a URI carrying a query string. Matching on the value instead of the key closes that gap.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\StructuredLogger;
use TinyBlocks\Logger\Redactions\Rules\PatternRedaction;

$logger = StructuredLogger::create()
    ->withComponent(component: 'error-service')
    ->withRedactions(PatternRedaction::from(pattern: '/\d{11}/', replacement: '[REDACTED]'))
    ->build();

$logger->error(message: 'request.failed', context: [
    'message' => 'Document 12345678901 is invalid.',
    'uri'     => '/clients/12345678901'
]);
# message → "Document [REDACTED] is invalid."
# uri     → "/clients/[REDACTED]"
```

A pattern the regular expression engine rejects raises `InvalidRedactionPattern` at configuration time, not at the first
log call.

#### Composing multiple redactions

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\StructuredLogger;
use TinyBlocks\Logger\Redactions\DocumentRedaction;
use TinyBlocks\Logger\Redactions\EmailRedaction;
use TinyBlocks\Logger\Redactions\Rules\FullMaskRedaction;
use TinyBlocks\Logger\Redactions\NameRedaction;
use TinyBlocks\Logger\Redactions\PhoneRedaction;

$logger = StructuredLogger::create()
    ->withComponent(component: 'user-service')
    ->withRedactions(
        NameRedaction::default(),
        EmailRedaction::default(),
        PhoneRedaction::default(),
        DocumentRedaction::default(),
        FullMaskRedaction::commonSecrets()
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
# document     → "********900"
# access_token → "********"
```

#### Custom redaction

Implement the `Redaction` interface to create your own strategy:

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\Redaction;

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

use TinyBlocks\Logger\StructuredLogger;

$logger = StructuredLogger::create()
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

use TinyBlocks\Logger\StructuredLogger;

$logger = StructuredLogger::create()
    ->withComponent(component: 'custom-service')
    ->withTemplate(template: "[%s] %s | %s | %s | %s | %s\n")
    ->build();

$logger->info(message: 'custom.event', context: ['value' => 42]);
# [2026-02-21T16:00:00+00:00] custom-service |  | INFO | custom.event | {"value":42}
```

### Testing with the in-memory logger

`InMemoryLogger` records entries instead of writing them, so a test asserts on what was logged rather than on how it was
rendered. Payloads are kept exactly as received, with no redaction and no formatting. Loggers derived through
`withContext` record into the same store, so entries are visible from either instance.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Logger\InMemoryLogger;

$logger = InMemoryLogger::create();

$logger->info(message: 'user.created', context: ['document' => '12345678900']);

$logger->entries()->toArray();
# [['key' => 'user.created', 'level' => 'INFO', 'context' => null, 'payload' => ['document' => '12345678900']]]
```

<div id='faq'></div>

## FAQ

### 01. Why does a masked value keep the width of the original?

Only when the strategy asks for it. The field-specific strategies use `Mask::proportional()`, which emits one character
per hidden character, because a support engineer reading `**********7766` can still tell a mobile number from a short
extension. That convenience costs information: the width of the output is the width of the input.

When the width itself is sensitive, a password being the clearest case, use `Mask::fixed(...)`, which emits the same run
every time and is what `PasswordRedaction` and `FullMaskRedaction::commonSecrets()` already do.

### 02. Why scope a redaction to a parent field instead of listing field names?

Because field names are not unique. A payload carries `code` as a verification code in one branch and as an error code
in another, `number` as a card number in one place and as a page number in a query string in another. A flat list of
field names cannot tell them apart, so masking the sensitive one also destroys the diagnostic one.

`ScopedRedaction` restricts a strategy to the sub payloads found under a given parent, which is the smallest piece of
context needed to disambiguate.

### 03. When should a field be dropped instead of masked?

When the masked value would still be noise. A stack trace, a payment brcode, or a raw user agent tells a reader nothing
once masked, and it still costs bytes in every aggregator downstream. `RemovedFieldsRedaction` removes the key entirely.

Masking stays the right answer whenever the shape of the value carries meaning, for example knowing that a document was
present and ended in `900`.

### 04. Why does the logger match field names with wildcards?

Because leaks happen by omission, not by mistake. A payload gains a `client_name` next to the `name` that was already
covered, and nothing in the configuration reacts. Patterns such as `*_name` or `*token*` follow the naming convention
rather than the current field list.

For payloads where even that is not enough, `AllowedFieldsRedaction` inverts the default: everything is masked until it
is named.

<div id='license'></div>

## License

Logger is licensed under [MIT](LICENSE).

<div id='contributing'></div>

## Contributing

Please follow the [contributing guidelines](https://github.com/tiny-blocks/tiny-blocks/blob/main/CONTRIBUTING.md) to
contribute to the project.

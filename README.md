# Logger

[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

* [Overview](#overview)
* [Installation](#installation)
* [How to use](#how-to-use)
    * [Basic logging](#basic-logging)
    * [Correlation tracking](#correlation-tracking)
    * [Sensitive data redaction](#sensitive-data-redaction)
    * [Custom log template](#custom-log-template)
* [License](#license)
* [Contributing](#contributing)

<div id='overview'></div>

## Overview

Emits PSR-3 structured logs for PHP, with each entry carrying timestamp, component, correlation id, level, and a
structured data payload. Supports pluggable redactions for sensitive fields such as passwords, emails, phone numbers,
and identity documents. Built for consumption by log aggregators and SIEM pipelines in production environments.

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
use TinyBlocks\Logger\LogContext;
use TinyBlocks\Logger\StructuredLogger;

$logger = StructuredLogger::create()
    ->withComponent(component: 'payment-service')
    ->build();

$contextual = $logger->withContext(context: LogContext::from(correlationId: 'req-abc-123'));

$contextual->info(message: 'payment.started', context: ['amount' => 100.50]);
```

### Sensitive data redaction

Redaction is optional and configurable. Built-in redaction strategies are provided for common sensitive fields.
Each strategy accepts multiple field name variations and a configurable masking length.

#### Document redaction

Masks all characters except the last N (default: 3).

```php
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
use TinyBlocks\Logger\Redactions\DocumentRedaction;

DocumentRedaction::from(fields: ['cpf', 'cnpj'], visibleSuffixLength: 5);
# cpf "12345678900"     → "******78900"
# cnpj "12345678000199" → "*********00199"
```

#### Email redaction

Preserves the first N characters of the local part (default: 2) and the full domain.

```php
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
use TinyBlocks\Logger\Redactions\EmailRedaction;

EmailRedaction::from(fields: ['email', 'contact_email', 'recoveryEmail'], visiblePrefixLength: 2);
```

#### Phone redaction

Masks all characters except the last N (default: 4).

```php
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
use TinyBlocks\Logger\Redactions\PhoneRedaction;

PhoneRedaction::from(fields: ['phone', 'mobile', 'whatsapp'], visibleSuffixLength: 4);
```

#### Password redaction

Masks the entire value with a fixed-length mask (default: 8 characters). The original value's length is never revealed
in the output, preventing information leakage about password size.

```php
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
use TinyBlocks\Logger\Redactions\PasswordRedaction;

PasswordRedaction::from(fields: ['password', 'secret', 'token'], fixedMaskLength: 12);
# "s3cr3t!"       → "************"
# "ab"            → "************"
# "long_password" → "************"
```

#### Name redaction

Preserves the first N characters (default: 2) and masks the rest.

```php
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
use TinyBlocks\Logger\Redactions\NameRedaction;

NameRedaction::from(fields: ['name', 'full_name', 'firstName'], visiblePrefixLength: 3);
# "Gustavo"       → "Gus****"
# "Gustavo Freze" → "Gus**********"
# "Maria"         → "Mar**"
```

#### Composing multiple redactions

```php
use TinyBlocks\Logger\StructuredLogger;
use TinyBlocks\Logger\Redactions\DocumentRedaction;
use TinyBlocks\Logger\Redactions\EmailRedaction;
use TinyBlocks\Logger\Redactions\NameRedaction;
use TinyBlocks\Logger\Redactions\PasswordRedaction;
use TinyBlocks\Logger\Redactions\PhoneRedaction;

$logger = StructuredLogger::create()
    ->withComponent(component: 'user-service')
    ->withRedactions(
        DocumentRedaction::default(),
        EmailRedaction::default(),
        PhoneRedaction::default(),
        PasswordRedaction::default(),
        NameRedaction::default()
    )
    ->build();

$logger->info(message: 'user.registered', context: [
    'document' => '12345678900',
    'email'    => 'john@example.com',
    'phone'    => '+5511999887766',
    'password' => 's3cr3t!',
    'name'     => 'John',
    'status'   => 'active'
]);
# document → "********900"
# email    → "jo**@example.com"
# phone    → "**********7766"
# password → "*******"
# name     → "Jo**"
# status   → "active" (unchanged)
```

#### Custom redaction

Implement the `Redaction` interface to create your own strategy:

```php
use TinyBlocks\Logger\Redaction;

final readonly class TokenRedaction implements Redaction
{
    public function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redact(data: $value);
                continue;
            }

            if ($key === 'token' && is_string($value)) {
                $data[$key] = '***REDACTED***';
            }
        }

        return $data;
    }
}
```

Then add it to the logger:

```php
use TinyBlocks\Logger\StructuredLogger; 

$logger = StructuredLogger::create()
    ->withComponent(component: 'auth-service')
    ->withRedactions(new TokenRedaction())
    ->build();

$logger->info(message: 'user.logged_in', context: ['token' => 'abc123']);
# token → "***REDACTED***"
```

### Custom log template

The default output template is:

```
%s component=%s correlation_id=%s level=%s key=%s data=%s
```

You can replace it with any `sprintf` compatible template that accepts six string arguments (timestamp, component,
correlationId, level, key, data):

```php
use TinyBlocks\Logger\StructuredLogger;

$logger = StructuredLogger::create()
    ->withComponent(component: 'custom-service')
    ->withTemplate(template: "[%s] %s | %s | %s | %s | %s\n")
    ->build();

$logger->info(message: 'custom.event', context: ['value' => 42]);
# [2026-02-21T16:00:00+00:00] custom-service |  | INFO | custom.event | {"value":42}
```

<div id='license'></div>

## License

Logger is licensed under [MIT](LICENSE).

<div id='contributing'></div>

## Contributing

Please follow the [contributing guidelines](https://github.com/tiny-blocks/tiny-blocks/blob/main/CONTRIBUTING.md) to
contribute to the project.

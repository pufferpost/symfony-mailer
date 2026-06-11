# mailer-symfony

A Symfony Mailer transport for the EU-first transactional email API (ADR-0037). MIT-licensed.
Because **Laravel 9+ runs on Symfony Mailer**, the same transport serves Laravel too.

## Install

```bash
composer require pufferpost/symfony-mailer
```

## Configure

```dotenv
# .env
MAILER_DSN=pufferpost+api://key_live_…@default
# optional: point at another region/host
# MAILER_DSN=pufferpost+api://key_live_…@default?base_url=https://api.mailer.eu
```

Register the factory (Symfony autowires it as a `TransportFactoryInterface`); then plain
`$mailer->send($email)` flows through the API.

## Send a templated email

The API renders server-side from a template + data, carried as allow-listed `X-Mailer-*`
headers. Use `MailerEmail` so you never hand-write them:

```php
use Mailer\Symfony\MailerEmail;

$email = (new MailerEmail())
    ->from('no-reply@acme.com')
    ->to('jane@example.com')
    ->subject('Welcome')          // shown in clients; the template owns the rendered body
    ->text('Welcome!')            // Symfony requires a body part
    ->template('welcome')
    ->templateData(['name' => 'Jane'])
    ->idempotencyKey('order-1234');

$mailer->send($email);            // → POST /v1/messages
```

A plain `Email` without a template header is rejected — the current API is template-only.
Batch sends and workflow triggers are SDK-only (see `pufferpost/sdk`).

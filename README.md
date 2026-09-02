# mailer-symfony

A Symfony bundle + Mailer transport for the EU-first transactional email API (ADR-0037). MIT-licensed.
Because **Laravel 9+ runs on Symfony Mailer**, the same transport serves Laravel too.

## Install

```bash
composer require pufferpost/symfony-mailer
```

Enable the bundle (Symfony Flex does this automatically; otherwise add it by hand):

```php
// config/bundles.php
return [
    // …
    PufferPost\Symfony\PufferPostMailerBundle::class => ['all' => true],
];
```

## Configure

```dotenv
# .env
MAILER_DSN=pufferpost+api://key_live_…@default
# optional: point at another region/host
# MAILER_DSN=pufferpost+api://key_live_…@default?base_url=https://api.pufferpost.eu
```

The bundle registers the transport factory, so `$mailer->send($email)` flows through the API with no
further wiring.

## Drop-in: send an ordinary email

A plain `Email` with a subject and an HTML (or text) body is delivered as-is — the API renders
nothing. This is the drop-in path for existing apps:

```php
use Symfony\Component\Mime\Email;

$email = (new Email())
    ->from('no-reply@acme.com')
    ->to('jane@example.com')
    ->cc('ops@acme.com')
    ->subject('Your receipt')
    ->html('<h1>Thanks!</h1>')
    ->attachFromPath('/tmp/receipt.pdf');

$mailer->send($email);            // → POST /v1/messages (inline content)
```

`from` / `to` / `cc` / `bcc` / `reply-to` / `subject` / `html` / `text` / `attachments` map straight
to the API. One request is sent per `To` recipient, each carrying the same cc/bcc.

## Send a templated email

To render server-side from a stored template + data, use `MailerEmail` so you never hand-write the
API headers:

```php
use PufferPost\Symfony\MailerEmail;

$email = (new MailerEmail())
    ->from('no-reply@acme.com')
    ->to('jane@example.com')
    ->subject('Welcome')          // ignored when a template is set; the template owns the body
    ->text('Welcome!')            // ignored likewise; Symfony just requires a body part
    ->templateId('tpl_…')
    ->templateData(['name' => 'Jane'])
    ->metadata(['order_id' => 'o_9'])
    ->idempotencyKey('order-1234');

$mailer->send($email);            // → POST /v1/messages (templated)
```

Provide a template **or** inline subject + html, never both. Batch sends and workflow triggers are
SDK-only (see `pufferpost/sdk`).

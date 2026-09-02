# PufferPost Symfony Mailer

A Symfony **bundle** + Mailer **transport** for the PufferPost transactional email API.
MIT-licensed. Point `MAILER_DSN` at the API and existing `$mailer->send($email)` calls flow through
it — no code changes for ordinary mail.

Because **Laravel 9+ runs on Symfony Mailer**, the same transport serves Laravel too
(see [Laravel](laravel.md)).

## How it works

The transport translates a Symfony `Email` into `POST /v1/messages` (one request per `To`
recipient) via the [`pufferpost/sdk`](../../php-sdk/docs/index.md) client. Two shapes, mirroring the
API:

- **Inline (drop-in)** — a plain `Email` with a subject and an HTML/text body is delivered as-is.
- **Templated** — a `MailerEmail` referencing a `tpl_…` id renders server-side.

All logic stays server-side; the transport is thin. It identifies itself to the API in the
User-Agent — `pufferpost-symfony/<version> pufferpost-php/<version>` — so requests sent through the
transport are distinguishable from raw SDK calls.

## Guides

- [Installation](installation.md) — Composer, bundle registration, the DSN
- [Sending email](sending.md) — drop-in and templated, cc/bcc/attachments, idempotency
- [Laravel](laravel.md)
- [Testing](testing.md)

## At a glance

```dotenv
# .env
MAILER_DSN=pufferpost+api://key_live_…@default
```

```php
use Symfony\Component\Mime\Email;

$mailer->send(
    (new Email())
        ->from('no-reply@acme.com')
        ->to('jane@example.com')
        ->subject('Welcome')
        ->html('<h1>Hi Jane</h1>')
);
```

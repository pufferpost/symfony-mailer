# Sending email

The transport maps a Symfony `Email` to the API. Native fields — `from`, `to`, `cc`, `bcc`,
`reply-to`, `subject`, `html`, `text`, `attachments` — map straight through. One `POST /v1/messages`
is sent per `To` recipient, each carrying the same cc/bcc.

## Drop-in: an ordinary email

A plain `Email` with a subject and an HTML (or text) body is delivered inline — the API renders
nothing. Existing code keeps working unchanged:

```php
use Symfony\Component\Mime\Email;

$email = (new Email())
    ->from('no-reply@acme.com')
    ->to('jane@example.com')
    ->cc('ops@acme.com')
    ->bcc('audit@acme.com')
    ->replyTo('support@acme.com')
    ->subject('Your receipt')
    ->html('<h1>Thanks!</h1>')
    ->attachFromPath('/tmp/receipt.pdf');

$mailer->send($email);
```

If there is no HTML part, the text body is used as the body. An email with a body but **no subject**
and **no template** is rejected with a `TransportException` (the API needs a subject for an inline
send).

## Templated send

To render a stored template server-side, use `MailerEmail` — a subclass of Symfony's `Email` that
carries the API metadata as allow-listed `X-Mailer-*` headers so you never hand-write them:

```php
use PufferPost\Symfony\MailerEmail;

$email = (new MailerEmail())
    ->from('no-reply@acme.com')
    ->to('jane@example.com')
    ->subject('Welcome')            // ignored when a template is set; the template owns the body
    ->text('Welcome!')              // Symfony requires a body part; also ignored
    ->templateId('tpl_welcome')
    ->templateData(['name' => 'Jane'])
    ->metadata(['order_id' => 'o_9'])
    ->idempotencyKey('order-1234');

$mailer->send($email);
```

`MailerEmail` methods:

| Method               | Sets                                                        |
| -------------------- | ---------------------------------------------------------- |
| `templateId(string)` | The `tpl_…` template to render                             |
| `templateData(array)`| Render variables (JSON)                                    |
| `metadata(array)`    | Opaque key/values echoed back on the message + webhooks    |
| `idempotencyKey(string)` | Dedupe key so a retry is not delivered twice           |

`cc`, `bcc`, `reply-to`, and attachments work on a `MailerEmail` too (inherited from `Email`).

Provide a template **or** an inline subject + body, never both.

## What the transport does not do

Batch sends and workflow triggers are not expressible on a single Symfony `Email` — use the
[`pufferpost/sdk`](../../php-sdk/docs/index.md) client directly for those.

---

Next: [Laravel](laravel.md) · [Testing](testing.md)

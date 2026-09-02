# Laravel

Laravel 9+ sends mail through Symfony Mailer, so the same transport works in Laravel. A dedicated
Laravel service provider package (`mailer-laravel`) is planned; until then, register the transport
yourself in a few lines.

> Requires `laravel/framework` 9+ (Symfony Mailer under the hood).

## Register the transport

In a service provider's `boot()`, extend the mail manager with a `pufferpost` transport built from
the factory:

```php
use Illuminate\Support\Facades\Mail;
use PufferPost\Symfony\ApiTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

public function boot(): void
{
    Mail::extend('pufferpost', function (array $config) {
        return (new ApiTransportFactory())->create(
            new Dsn('pufferpost+api', 'default', $config['key'], options: [
                'base_url' => $config['base_url'] ?? 'https://api.pufferpost.com',
            ]),
        );
    });
}
```

## Configure the mailer

```php
// config/mail.php
'mailers' => [
    'pufferpost' => [
        'transport' => 'pufferpost',
        'key'       => env('PUFFERPOST_KEY'),
        'base_url'  => env('PUFFERPOST_BASE_URL', 'https://api.pufferpost.com'),
    ],
],
```

```dotenv
# .env
MAIL_MAILER=pufferpost
PUFFERPOST_KEY=key_live_…
```

## Send

Ordinary Laravel mail now flows through the API:

```php
Mail::to('jane@example.com')->send(new OrderShipped($order));
```

A Mailable with an HTML view sends inline. To render a stored template server-side instead, build a
`MailerEmail` (see [Sending email](sending.md)) and pass it via `Mail::send()` with a raw symfony
message, or send through the SDK directly for template + data ergonomics.

---

Next: [Testing](testing.md)

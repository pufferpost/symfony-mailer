# Installation

```bash
composer require pufferpost/symfony-mailer
```

Requires PHP 8.2+ and Symfony Mailer 6.4, 7.x, or 8.x.

## Register the bundle

Symfony Flex registers the bundle automatically. Without Flex, add it by hand:

```php
// config/bundles.php
return [
    // …
    PufferPost\Symfony\PufferPostMailerBundle::class => ['all' => true],
];
```

The bundle registers the transport factory (tagged `mailer.transport_factory`), so no service
wiring is needed on your side.

## Configure the DSN

```dotenv
# .env
MAILER_DSN=pufferpost+api://key_live_…@default
```

- **Scheme** — `pufferpost+api` (or the short alias `pufferpost`).
- **User** — your API key (`key_live_…` in production, `key_test_…` for test mode).
- **Host** — `default` (a placeholder; the real host comes from `base_url`).

### Point at your region/host

```dotenv
MAILER_DSN=pufferpost+api://key_live_…@default?base_url=https://pufferpost.com
```

`base_url` defaults to `https://pufferpost.com` when omitted.

## Verify

```php
// any controller/command
$mailer->send(
    (new \Symfony\Component\Mime\Email())
        ->from('no-reply@acme.com')
        ->to('you@example.com')
        ->subject('It works')
        ->text('Hello from PufferPost')
);
```

A misconfigured scheme raises `UnsupportedSchemeException`; a DSN without an API key raises
`IncompleteDsnException`.

---

Next: [Sending email](sending.md)

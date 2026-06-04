<?php

declare(strict_types=1);

namespace Mailer\Symfony;

use Mailer\Sdk\Client;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Builds {@see ApiTransport} from a Mailer DSN (ADR-0037): `ourmailer+api://API_KEY@default`,
 * optionally `?base_url=https://api.eu…`. Registering this factory lets Symfony — and Laravel,
 * via the same Symfony Mailer — route `$mailer->send($email)` through the API.
 */
final class ApiTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if (!\in_array($dsn->getScheme(), $this->getSupportedSchemes(), true)) {
            throw new UnsupportedSchemeException($dsn, 'ourmailer', $this->getSupportedSchemes());
        }

        $baseUrl = $dsn->getOption('base_url');
        $client = new Client(
            $this->getUser($dsn),
            \is_string($baseUrl) ? $baseUrl : 'https://api.mailer.eu',
            $this->client,
        );

        return new ApiTransport($client, $this->dispatcher, $this->logger);
    }

    /**
     * @return list<string>
     */
    protected function getSupportedSchemes(): array
    {
        return ['ourmailer', 'ourmailer+api'];
    }
}

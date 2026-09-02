<?php

declare(strict_types=1);

namespace PufferPost\Symfony;

use PufferPost\Sdk\Client;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Builds {@see ApiTransport} from a Mailer DSN: `pufferpost+api://API_KEY@default`,
 * optionally `?base_url=https://api.example…`. Registering this factory lets Symfony — and Laravel,
 * via the same Symfony Mailer — route `$mailer->send($email)` through the API.
 */
final class ApiTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if (!\in_array($dsn->getScheme(), $this->getSupportedSchemes(), true)) {
            throw new UnsupportedSchemeException($dsn, 'pufferpost', $this->getSupportedSchemes());
        }

        $baseUrl = $dsn->getOption('base_url');
        $client = new Client(
            $this->getUser($dsn),
            \is_string($baseUrl) ? $baseUrl : Client::DEFAULT_BASE_URL,
            $this->client,
            integration: 'pufferpost-symfony/'.ApiTransport::VERSION,
        );

        return new ApiTransport($client, $this->dispatcher, $this->logger);
    }

    /**
     * @return list<string>
     */
    protected function getSupportedSchemes(): array
    {
        return ['pufferpost', 'pufferpost+api'];
    }
}

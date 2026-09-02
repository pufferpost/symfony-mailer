<?php

declare(strict_types=1);

namespace PufferPost\Symfony;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Registers the {@see ApiTransportFactory} as a Mailer transport factory (ADR-0037), so a consuming
 * Symfony app only has to enable the bundle and point `MAILER_DSN` at `pufferpost+api://KEY@default`.
 * The factory inherits the abstract `mailer.transport_factory` service (event dispatcher, HTTP client,
 * logger), so Laravel — which runs on Symfony Mailer — is served by the same wiring.
 */
final class PufferPostMailerBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()
            ->set(ApiTransportFactory::class)
            ->parent('mailer.transport_factory.abstract')
            ->tag('mailer.transport_factory');
    }
}

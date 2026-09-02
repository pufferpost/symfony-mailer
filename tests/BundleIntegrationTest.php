<?php

declare(strict_types=1);

namespace PufferPost\Symfony\Tests;

use PHPUnit\Framework\TestCase;
use PufferPost\Symfony\ApiTransport;
use PufferPost\Symfony\ApiTransportFactory;
use PufferPost\Symfony\PufferPostMailerBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Proves the bundle makes the transport turnkey (ADR-0037): dropping
 * {@see PufferPostMailerBundle} into an app and pointing MAILER_DSN at
 * `pufferpost+api://…` is all it takes — Symfony resolves the scheme through
 * our factory with no hand-registered services.
 */
final class BundleIntegrationTest extends TestCase
{
    private string $cacheDir = '';

    protected function tearDown(): void
    {
        if ('' !== $this->cacheDir) {
            (new Filesystem())->remove($this->cacheDir);
        }
    }

    private function boot(): ContainerInterface
    {
        $this->cacheDir = sys_get_temp_dir().'/pufferpost-bundle-'.uniqid('', true);
        $kernel = new class($this->cacheDir) extends Kernel {
            use MicroKernelTrait;

            public function __construct(private readonly string $dir)
            {
                parent::__construct('test', true);
            }

            public function registerBundles(): iterable
            {
                return [new FrameworkBundle(), new PufferPostMailerBundle()];
            }

            protected function configureContainer(ContainerConfigurator $container): void
            {
                $container->extension('framework', [
                    'test' => true,
                    'secret' => 'test',
                    'http_method_override' => false,
                    'handle_all_throwables' => true,
                    'php_errors' => ['log' => true],
                    'mailer' => ['dsn' => 'pufferpost+api://key_test@default'],
                ]);
            }

            public function getCacheDir(): string
            {
                return $this->dir;
            }

            public function getLogDir(): string
            {
                return $this->dir.'/log';
            }
        };
        $kernel->boot();

        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    public function testTheBundleRegistersTheApiTransportFactory(): void
    {
        $factory = $this->boot()->get(ApiTransportFactory::class);

        self::assertInstanceOf(ApiTransportFactory::class, $factory);
    }

    public function testTheMailerDsnResolvesToTheApiTransport(): void
    {
        // A single MAILER_DSN is wrapped in a Transports aggregate; unwrap its default
        // to prove the `pufferpost+api://` scheme resolved through our factory.
        $transports = $this->boot()->get('mailer.default_transport');

        $default = (new \ReflectionProperty($transports, 'default'))->getValue($transports);

        self::assertInstanceOf(ApiTransport::class, $default);
    }
}

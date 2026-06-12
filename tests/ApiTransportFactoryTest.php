<?php

declare(strict_types=1);

namespace PufferPost\Symfony\Tests;

use PHPUnit\Framework\TestCase;
use PufferPost\Symfony\ApiTransport;
use PufferPost\Symfony\ApiTransportFactory;
use PufferPost\Symfony\MailerEmail;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\Dsn;

final class ApiTransportFactoryTest extends TestCase
{
    private function factory(?MockHttpClient $http = null): ApiTransportFactory
    {
        return new ApiTransportFactory(null, $http ?? new MockHttpClient(), null);
    }

    public function testCreatesATransportForTheSupportedScheme(): void
    {
        $transport = $this->factory()->create(new Dsn('pufferpost+api', 'default', 'key_live_abc'));

        self::assertInstanceOf(ApiTransport::class, $transport);
        self::assertSame('pufferpost+api://default', (string) $transport);
    }

    public function testHonoursTheBaseUrlOption(): void
    {
        $captured = [];
        $http = new MockHttpClient(static function (string $method, string $url) use (&$captured): MockResponse {
            $captured['url'] = $url;

            return new MockResponse((string) json_encode(['id' => 'm', 'status' => 'accepted']), ['http_code' => 202]);
        });
        $transport = $this->factory($http)->create(new Dsn('pufferpost+api', 'default', 'key', options: ['base_url' => 'https://eu.api.test']));

        $transport->send((new MailerEmail())->from('a@acme.com')->to('b@acme.com')->subject('Hi')->text('Hi!')->template('welcome'));

        self::assertSame('https://eu.api.test/v1/messages', $captured['url']);
    }

    public function testRejectsAnUnsupportedScheme(): void
    {
        $this->expectException(UnsupportedSchemeException::class);
        $this->factory()->create(new Dsn('smtp', 'default', 'key'));
    }

    public function testRejectsADsnWithoutAnApiKey(): void
    {
        $this->expectException(IncompleteDsnException::class);
        $this->factory()->create(new Dsn('pufferpost+api', 'default'));
    }
}

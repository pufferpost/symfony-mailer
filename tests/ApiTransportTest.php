<?php

declare(strict_types=1);

namespace Mailer\Symfony\Tests;

use Mailer\Sdk\Client;
use Mailer\Symfony\ApiTransport;
use Mailer\Symfony\MailerEmail;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;

final class ApiTransportTest extends TestCase
{
    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $requests = [];

    private function transport(): ApiTransport
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse((string) json_encode(['id' => 'msg_1', 'status' => 'accepted']), ['http_code' => 202]);
        });

        return new ApiTransport(new Client('key_test', 'https://api.test', $http));
    }

    public function testTranslatesAMailerEmailIntoAnApiSend(): void
    {
        $email = (new MailerEmail())
            ->from('no-reply@acme.com')
            ->to('jane@example.com')
            ->subject('Welcome')
            ->text('Welcome!')
            ->template('welcome')
            ->templateData(['name' => 'Jane'])
            ->metadata(['order_id' => 'o_9'])
            ->idempotencyKey('idem-1');

        $this->transport()->send($email);

        self::assertCount(1, $this->requests);
        self::assertSame('POST', $this->requests[0]['method']);
        self::assertSame('https://api.test/v1/messages', $this->requests[0]['url']);

        $body = json_decode((string) $this->requests[0]['options']['body'], true);
        self::assertSame(
            ['from' => 'no-reply@acme.com', 'to' => 'jane@example.com', 'template' => 'welcome', 'data' => ['name' => 'Jane'], 'metadata' => ['order_id' => 'o_9']],
            $body,
        );
        $headers = $this->requests[0]['options']['headers'] ?? [];
        self::assertIsArray($headers);
        self::assertContains('Idempotency-Key: idem-1', array_map(strval(...), $headers));
    }

    public function testSendsOneRequestPerRecipient(): void
    {
        $email = (new MailerEmail())
            ->from('no-reply@acme.com')
            ->to('jane@example.com', 'bob@example.com')
            ->subject('Welcome')
            ->text('Welcome!')
            ->template('welcome');

        $this->transport()->send($email);

        self::assertCount(2, $this->requests);
    }

    public function testRejectsAnEmailWithoutATemplate(): void
    {
        $email = (new Email())
            ->from('no-reply@acme.com')
            ->to('jane@example.com')
            ->subject('Welcome')
            ->text('plain body');

        $this->expectException(TransportException::class);
        $this->transport()->send($email);
    }
}

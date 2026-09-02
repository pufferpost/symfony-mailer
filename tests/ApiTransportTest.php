<?php

declare(strict_types=1);

namespace PufferPost\Symfony\Tests;

use PHPUnit\Framework\TestCase;
use PufferPost\Sdk\Client;
use PufferPost\Symfony\ApiTransport;
use PufferPost\Symfony\MailerEmail;
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
            ->templateId('tpl_welcome')
            ->templateData(['name' => 'Jane'])
            ->metadata(['order_id' => 'o_9'])
            ->idempotencyKey('idem-1');

        $this->transport()->send($email);

        self::assertCount(1, $this->requests);
        self::assertSame('POST', $this->requests[0]['method']);
        self::assertSame('https://api.test/v1/messages', $this->requests[0]['url']);

        $body = json_decode((string) $this->requests[0]['options']['body'], true);
        self::assertSame(
            ['from' => 'no-reply@acme.com', 'to' => 'jane@example.com', 'templateId' => 'tpl_welcome', 'data' => ['name' => 'Jane'], 'metadata' => ['order_id' => 'o_9']],
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
            ->templateId('tpl_welcome');

        $this->transport()->send($email);

        self::assertCount(2, $this->requests);
    }

    public function testSendsByTemplateId(): void
    {
        $email = (new MailerEmail())
            ->from('no-reply@acme.com')
            ->to('jane@example.com')
            ->subject('Welcome')
            ->text('Welcome!')
            ->templateId('tpl_abc123')
            ->templateData(['name' => 'Jane']);

        $this->transport()->send($email);

        self::assertCount(1, $this->requests);
        $body = json_decode((string) $this->requests[0]['options']['body'], true);
        self::assertIsArray($body);
        self::assertSame('tpl_abc123', $body['templateId']);
        self::assertArrayNotHasKey('template', $body);
    }

    public function testSendsAPlainInlineEmailWithoutATemplate(): void
    {
        $email = (new Email())
            ->from('no-reply@acme.com')
            ->to('jane@example.com')
            ->subject('Welcome')
            ->html('<p>Hi Jane</p>');

        $this->transport()->send($email);

        self::assertCount(1, $this->requests);
        $body = json_decode((string) $this->requests[0]['options']['body'], true);
        self::assertIsArray($body);
        self::assertSame('Welcome', $body['subject']);
        self::assertSame('<p>Hi Jane</p>', $body['html']);
        self::assertArrayNotHasKey('templateId', $body);
    }

    public function testFallsBackToTheTextBodyAsHtmlWhenNoHtmlPartIsSet(): void
    {
        $email = (new Email())
            ->from('no-reply@acme.com')
            ->to('jane@example.com')
            ->subject('Welcome')
            ->text('Plain hello');

        $this->transport()->send($email);

        $body = json_decode((string) $this->requests[0]['options']['body'], true);
        self::assertIsArray($body);
        self::assertSame('Plain hello', $body['html']);
    }

    public function testMapsCcBccReplyToAndAttachments(): void
    {
        $email = (new MailerEmail())
            ->from('no-reply@acme.com')
            ->to('jane@example.com')
            ->cc('ops@acme.com')
            ->bcc('audit@acme.com')
            ->replyTo('support@acme.com')
            ->subject('Welcome')
            ->text('Body')
            ->templateId('tpl_welcome')
            ->attach('file-bytes', 'note.txt', 'text/plain');

        $this->transport()->send($email);

        self::assertCount(1, $this->requests);
        $body = json_decode((string) $this->requests[0]['options']['body'], true);
        self::assertIsArray($body);
        self::assertSame(['ops@acme.com'], $body['cc']);
        self::assertSame(['audit@acme.com'], $body['bcc']);
        self::assertSame('support@acme.com', $body['replyTo']);
        self::assertSame([['filename' => 'note.txt', 'contentType' => 'text/plain', 'content' => base64_encode('file-bytes')]], $body['attachments']);
    }

    public function testFansOutOverEnvelopeRecipientsWhenThereIsNoToHeader(): void
    {
        // A Bcc-only email has no To header; the envelope still carries the recipient, so the
        // transport sends to it (with cc/bcc cleared, since it is already the primary).
        $email = (new MailerEmail())
            ->from('no-reply@acme.com')
            ->bcc('hidden@acme.com')
            ->subject('Welcome')
            ->text('Body')
            ->templateId('tpl_welcome');

        $this->transport()->send($email);

        self::assertCount(1, $this->requests);
        $body = json_decode((string) $this->requests[0]['options']['body'], true);
        self::assertIsArray($body);
        self::assertSame('hidden@acme.com', $body['to']);
        self::assertArrayNotHasKey('bcc', $body);
    }

    public function testRejectsAnInlineEmailThatHasNoSubject(): void
    {
        // Inline sends need a subject (the API requires subject + html together); without a
        // template and without a subject there is no valid request to build.
        $email = (new Email())
            ->from('no-reply@acme.com')
            ->to('jane@example.com')
            ->html('<p>orphan body</p>');

        $this->expectException(TransportException::class);
        $this->transport()->send($email);
    }
}

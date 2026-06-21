<?php

declare(strict_types=1);

namespace PufferPost\Symfony\Tests;

use PHPUnit\Framework\TestCase;
use PufferPost\Symfony\MailerEmail;

final class MailerEmailTest extends TestCase
{
    public function testSetsTheApiMetadataHeaders(): void
    {
        $email = (new MailerEmail())
            ->templateId('tpl_welcome')
            ->templateData(['name' => 'Jane'])
            ->metadata(['order_id' => 'o_9'])
            ->idempotencyKey('idem-1');

        $headers = $email->getHeaders();
        self::assertSame('tpl_welcome', $headers->getHeaderBody(MailerEmail::HEADER_TEMPLATE_ID));
        self::assertSame('{"name":"Jane"}', $headers->getHeaderBody(MailerEmail::HEADER_DATA));
        self::assertSame('{"order_id":"o_9"}', $headers->getHeaderBody(MailerEmail::HEADER_METADATA));
        self::assertSame('idem-1', $headers->getHeaderBody(MailerEmail::HEADER_IDEMPOTENCY_KEY));
    }

    public function testReplacesRatherThanDuplicatesAHeader(): void
    {
        $email = (new MailerEmail())->templateId('tpl_first')->templateId('tpl_second');

        self::assertCount(1, iterator_to_array($email->getHeaders()->all(MailerEmail::HEADER_TEMPLATE_ID)));
        self::assertSame('tpl_second', $email->getHeaders()->getHeaderBody(MailerEmail::HEADER_TEMPLATE_ID));
    }

    public function testSetsTheTemplateIdHeader(): void
    {
        $email = (new MailerEmail())->templateId('tpl_abc123');

        self::assertSame('tpl_abc123', $email->getHeaders()->getHeaderBody(MailerEmail::HEADER_TEMPLATE_ID));
    }
}

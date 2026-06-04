<?php

declare(strict_types=1);

namespace Mailer\Symfony\Tests;

use Mailer\Symfony\MailerEmail;
use PHPUnit\Framework\TestCase;

final class MailerEmailTest extends TestCase
{
    public function testSetsTheApiMetadataHeaders(): void
    {
        $email = (new MailerEmail())
            ->template('welcome')
            ->templateData(['name' => 'Jane'])
            ->idempotencyKey('idem-1');

        $headers = $email->getHeaders();
        self::assertSame('welcome', $headers->getHeaderBody(MailerEmail::HEADER_TEMPLATE));
        self::assertSame('{"name":"Jane"}', $headers->getHeaderBody(MailerEmail::HEADER_DATA));
        self::assertSame('idem-1', $headers->getHeaderBody(MailerEmail::HEADER_IDEMPOTENCY_KEY));
    }

    public function testReplacesRatherThanDuplicatesAHeader(): void
    {
        $email = (new MailerEmail())->template('first')->template('second');

        self::assertCount(1, iterator_to_array($email->getHeaders()->all(MailerEmail::HEADER_TEMPLATE)));
        self::assertSame('second', $email->getHeaders()->getHeaderBody(MailerEmail::HEADER_TEMPLATE));
    }
}

<?php

declare(strict_types=1);

namespace Mailer\Symfony;

use Mailer\Sdk\Client;
use Mailer\Sdk\Email as ApiEmail;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A thin Symfony Mailer transport (ADR-0037): it translates a Symfony {@see Email} into a
 * `POST /v1/messages` per recipient via the SDK {@see Client} — all logic stays server-side.
 * The template + data ride as `X-Mailer-*` headers (see {@see MailerEmail}); the current API is
 * template-only, so an email without a template header is rejected.
 *
 * Because Laravel 9+ runs on Symfony Mailer, this same transport serves Laravel too.
 */
final class ApiTransport extends AbstractTransport
{
    public function __construct(
        private readonly Client $client,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();

        $template = $this->header($email, MailerEmail::HEADER_TEMPLATE);
        if (null === $template) {
            throw new TransportException('The mailer API requires a template; set it via MailerEmail::template().');
        }

        $data = [];
        $rawData = $this->header($email, MailerEmail::HEADER_DATA);
        if (null !== $rawData) {
            $decoded = json_decode($rawData, true);
            if (\is_array($decoded)) {
                $data = $decoded;
            }
        }

        $idempotencyKey = $this->header($email, MailerEmail::HEADER_IDEMPOTENCY_KEY);
        $from = $envelope->getSender()->getAddress();

        foreach ($envelope->getRecipients() as $recipient) {
            $this->client->send(new ApiEmail($from, $recipient->getAddress(), $template, $data), $idempotencyKey);
        }
    }

    public function __toString(): string
    {
        return 'ourmailer+api://default';
    }

    private function header(Email $email, string $name): ?string
    {
        $body = $email->getHeaders()->getHeaderBody($name);

        return \is_string($body) && '' !== $body ? $body : null;
    }
}

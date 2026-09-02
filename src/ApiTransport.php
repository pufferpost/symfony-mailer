<?php

declare(strict_types=1);

namespace PufferPost\Symfony;

use Psr\Log\LoggerInterface;
use PufferPost\Sdk\Attachment;
use PufferPost\Sdk\Client;
use PufferPost\Sdk\Email as ApiEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A thin Symfony Mailer transport (ADR-0037): it translates a Symfony {@see Email} into one
 * `POST /v1/messages` per primary recipient via the SDK {@see Client} — all logic stays server-side.
 *
 * Two send shapes, mirroring the API:
 *   - **Templated** — set a template id (and optional data/metadata) via {@see MailerEmail}; the
 *     server renders it. The email's subject/body are ignored in favour of the template.
 *   - **Inline (drop-in)** — a plain `$mailer->send($email)` with a subject and an HTML (or text)
 *     body is delivered as-is, so the transport is a drop-in for ordinary transactional mail.
 *
 * Either shape carries cc/bcc/reply-to/attachments straight from the Symfony {@see Email}.
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

        $templateId = $this->header($email, MailerEmail::HEADER_TEMPLATE_ID);
        $subject = $email->getSubject();
        $html = $this->htmlBody($email);

        if (null === $templateId && (null === $subject || null === $html)) {
            throw new TransportException('Provide a template via MailerEmail::templateId(), or a subject with an HTML or text body for an inline send.');
        }

        $data = $this->jsonHeader($email, MailerEmail::HEADER_DATA);
        $metadata = $this->jsonHeader($email, MailerEmail::HEADER_METADATA);
        $idempotencyKey = $this->header($email, MailerEmail::HEADER_IDEMPOTENCY_KEY);
        $from = $envelope->getSender()->getAddress();
        $replyTo = $this->firstAddress($email->getReplyTo());
        $attachments = $this->attachments($email);

        // A template drives the content server-side; only an inline send carries subject/html.
        $inlineSubject = null === $templateId ? $subject : null;
        $inlineHtml = null === $templateId ? $html : null;

        $recipients = $email->getTo();
        $cc = $this->addresses($email->getCc());
        $bcc = $this->addresses($email->getBcc());
        if ([] === $recipients) {
            // No explicit To header: fan out over the envelope's recipients as individual sends.
            $recipients = $envelope->getRecipients();
            $cc = $bcc = [];
        }

        foreach ($recipients as $recipient) {
            $this->client->send(new ApiEmail(
                from: $from,
                to: $recipient->getAddress(),
                templateId: $templateId,
                data: $data,
                metadata: $metadata,
                replyTo: $replyTo,
                cc: $cc,
                subject: $inlineSubject,
                html: $inlineHtml,
                bcc: $bcc,
                attachments: $attachments,
            ), $idempotencyKey);
        }
    }

    /**
     * The HTML body, falling back to the plain-text body (the API's inline path is HTML-only).
     */
    private function htmlBody(Email $email): ?string
    {
        $html = $email->getHtmlBody();
        if (\is_string($html)) {
            return $html;
        }

        $text = $email->getTextBody();

        return \is_string($text) ? $text : null;
    }

    /**
     * @param array<Address> $addresses
     *
     * @return list<string>
     */
    private function addresses(array $addresses): array
    {
        return array_map(static fn (Address $address): string => $address->getAddress(), $addresses);
    }

    /**
     * @param array<Address> $addresses
     */
    private function firstAddress(array $addresses): ?string
    {
        return isset($addresses[0]) ? $addresses[0]->getAddress() : null;
    }

    /**
     * @return list<Attachment>
     */
    private function attachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $part) {
            if (!$part instanceof DataPart) {
                continue;
            }
            $attachments[] = Attachment::fromContents(
                $part->getFilename() ?? 'attachment',
                $part->getMediaType().'/'.$part->getMediaSubtype(),
                $part->getBody(),
            );
        }

        return $attachments;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function jsonHeader(Email $email, string $name): array
    {
        $raw = $this->header($email, $name);
        if (null === $raw) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    public function __toString(): string
    {
        return 'pufferpost+api://default';
    }

    private function header(Email $email, string $name): ?string
    {
        $body = $email->getHeaders()->getHeaderBody($name);

        return \is_string($body) && '' !== $body ? $body : null;
    }
}

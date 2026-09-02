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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A thin Symfony Mailer transport: it translates a Symfony {@see Email} into one
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
    /**
     * This transport's version, reported to the API as the integration marker in the User-Agent
     * (`pufferpost-symfony/<version> pufferpost-php/<sdk>`). Keep in step with the package tag.
     */
    public const VERSION = '0.1.0';

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
        $html = $this->body($email->getHtmlBody());
        $text = $this->body($email->getTextBody());

        if (null === $templateId && (null === $subject || (null === $html && null === $text))) {
            throw new TransportException('Provide a template via MailerEmail::templateId(), or a subject with an HTML or text body for an inline send.');
        }

        $data = $this->jsonHeader($email, MailerEmail::HEADER_DATA);
        $metadata = $this->jsonHeader($email, MailerEmail::HEADER_METADATA);
        $idempotencyKey = $this->header($email, MailerEmail::HEADER_IDEMPOTENCY_KEY);
        $unsubscribeGroup = $this->header($email, MailerEmail::HEADER_UNSUBSCRIBE_GROUP);
        $locale = $this->header($email, MailerEmail::HEADER_LOCALE);
        $timezone = $this->header($email, MailerEmail::HEADER_TIMEZONE);
        // The API's `from` is the visible sender identity, which must match a verified sender —
        // not the envelope sender (Symfony resolves that as Sender ?? Return-Path ?? From).
        $from = $this->firstAddress($email->getFrom()) ?? $envelope->getSender()->getAddress();
        $replyTo = $this->firstAddress($email->getReplyTo());
        $attachments = $this->attachments($email);

        // A template drives the content server-side; only an inline send carries subject/body.
        $inlineSubject = null === $templateId ? $subject : null;
        $inlineHtml = null === $templateId ? $html : null;
        $inlineText = null === $templateId ? $text : null;

        $recipients = $email->getTo();
        $cc = $this->addresses($email->getCc());
        $bcc = $this->addresses($email->getBcc());
        if ([] === $recipients) {
            // No explicit To header: fan out over the envelope's recipients as individual sends.
            $recipients = $envelope->getRecipients();
            $cc = $bcc = [];
        }

        $perRecipientKey = null !== $idempotencyKey && \count($recipients) > 1;

        foreach ($recipients as $recipient) {
            $address = $recipient->getAddress();
            $this->client->send(new ApiEmail(
                from: $from,
                to: $address,
                templateId: $templateId,
                data: $data,
                metadata: $metadata,
                replyTo: $replyTo,
                cc: $cc,
                unsubscribeGroup: $unsubscribeGroup,
                locale: $locale,
                timezone: $timezone,
                subject: $inlineSubject,
                html: $inlineHtml,
                text: $inlineText,
                bcc: $bcc,
                attachments: $attachments,
                // The API fingerprints the recipient, so one key cannot cover a fan-out; derive a
                // stable per-recipient key so retrying the same email still replays correctly.
            ), $perRecipientKey ? $idempotencyKey.':'.$address : $idempotencyKey);
        }
    }

    /**
     * A body part as a string; Symfony also accepts a resource, which the API cannot carry.
     */
    private function body(mixed $body): ?string
    {
        return \is_string($body) ? $body : null;
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
        // Email::getAttachments() returns DataPart instances.
        foreach ($email->getAttachments() as $part) {
            $attachments[] = Attachment::fromContents(
                $part->getFilename() ?? 'attachment',
                $part->getMediaType().'/'.$part->getMediaSubtype(),
                $part->getBody(),
            );
        }

        return $attachments;
    }

    /**
     * Decode a JSON `X-Mailer-*` header. A malformed value is an error rather than an empty array:
     * silently dropping it would send the message with its template variables missing.
     *
     * @return array<array-key, mixed>
     */
    private function jsonHeader(Email $email, string $name): array
    {
        $raw = $this->header($email, $name);
        if (null === $raw) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!\is_array($decoded)) {
            throw new TransportException(\sprintf('The "%s" header must contain a JSON object; set it via MailerEmail.', $name));
        }

        return $decoded;
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

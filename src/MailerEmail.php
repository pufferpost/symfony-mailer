<?php

declare(strict_types=1);

namespace PufferPost\Symfony;

use Symfony\Component\Mime\Email;

/**
 * A Symfony {@see Email} that carries the API-specific metadata as allow-listed `X-Mailer-*`
 * headers (ADR-0037), so users never hand-write headers and plain `$mailer->send($email)` keeps
 * working. The transport reads these to build the API request.
 */
final class MailerEmail extends Email
{
    public const HEADER_TEMPLATE = 'X-Mailer-Template';
    public const HEADER_DATA = 'X-Mailer-Data';
    public const HEADER_METADATA = 'X-Mailer-Metadata';
    public const HEADER_IDEMPOTENCY_KEY = 'X-Mailer-Idempotency-Key';

    public function template(string $template): static
    {
        return $this->setHeader(self::HEADER_TEMPLATE, $template);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function templateData(array $data): static
    {
        return $this->setHeader(self::HEADER_DATA, json_encode($data, \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, string> $metadata
     */
    public function metadata(array $metadata): static
    {
        return $this->setHeader(self::HEADER_METADATA, json_encode($metadata, \JSON_THROW_ON_ERROR));
    }

    public function idempotencyKey(string $key): static
    {
        return $this->setHeader(self::HEADER_IDEMPOTENCY_KEY, $key);
    }

    private function setHeader(string $name, string $value): static
    {
        $headers = $this->getHeaders();
        $headers->remove($name);
        $headers->addTextHeader($name, $value);

        return $this;
    }
}

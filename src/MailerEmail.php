<?php

declare(strict_types=1);

namespace PufferPost\Symfony;

use Symfony\Component\Mime\Email;

/**
 * A Symfony {@see Email} that carries the API-specific metadata as allow-listed `X-Mailer-*`
 * headers, so users never hand-write headers and plain `$mailer->send($email)` keeps
 * working. The transport reads these to build the API request.
 */
final class MailerEmail extends Email
{
    public const HEADER_TEMPLATE_ID = 'X-Mailer-Template-Id';
    public const HEADER_DATA = 'X-Mailer-Data';
    public const HEADER_METADATA = 'X-Mailer-Metadata';
    public const HEADER_IDEMPOTENCY_KEY = 'X-Mailer-Idempotency-Key';
    public const HEADER_UNSUBSCRIBE_GROUP = 'X-Mailer-Unsubscribe-Group';
    public const HEADER_LOCALE = 'X-Mailer-Locale';
    public const HEADER_TIMEZONE = 'X-Mailer-Timezone';

    /**
     * Reference a template by its stable `tpl_…` id.
     */
    public function templateId(string $templateId): static
    {
        return $this->setHeader(self::HEADER_TEMPLATE_ID, $templateId);
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

    /**
     * The subscription topic this message belongs to, for per-group one-click unsubscribe.
     */
    public function unsubscribeGroup(string $group): static
    {
        return $this->setHeader(self::HEADER_UNSUBSCRIBE_GROUP, $group);
    }

    /**
     * The recipient's locale, selecting a per-locale template variant.
     */
    public function locale(string $locale): static
    {
        return $this->setHeader(self::HEADER_LOCALE, $locale);
    }

    /**
     * The recipient's timezone, used to render dates in their local time.
     */
    public function timezone(string $timezone): static
    {
        return $this->setHeader(self::HEADER_TIMEZONE, $timezone);
    }

    private function setHeader(string $name, string $value): static
    {
        $headers = $this->getHeaders();
        $headers->remove($name);
        $headers->addTextHeader($name, $value);

        return $this;
    }
}

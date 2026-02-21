<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter;

use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorFormatterInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\Mailer\DataCollector\MessageDataCollector;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\String\UnicodeString;

/**
 * Formats Mailer collector data for AI consumption.
 *
 * Extracts email message details including recipients, subject,
 * body preview, attachments, and transport information.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<MessageDataCollector>
 */
final class MailerCollectorFormatter implements CollectorFormatterInterface
{
    private const MAX_BODY_LENGTH = 500;

    public function getName(): string
    {
        return 'mailer';
    }

    public function format(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof MessageDataCollector);

        $events = $collector->getEvents();
        $messages = [];

        foreach ($events->getEvents() as $event) {
            $message = $event->getMessage();
            $messageData = [
                'transport' => $event->getTransport(),
                'is_queued' => $event->isQueued(),
            ];

            if ($message instanceof Email) {
                $messageData = array_merge($messageData, $this->formatEmail($message));
            } else {
                $messageData['type'] = $message::class;
            }

            $messages[] = $messageData;
        }

        return [
            'message_count' => \count($messages),
            'messages' => $messages,
        ];
    }

    public function getSummary(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof MessageDataCollector);

        $events = $collector->getEvents();
        $subjects = [];

        foreach ($events->getEvents() as $event) {
            $message = $event->getMessage();
            if ($message instanceof Email) {
                $subjects[] = $message->getSubject() ?? '(no subject)';
            }
        }

        return [
            'message_count' => \count($events->getEvents()),
            'subjects' => $subjects,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatEmail(Email $email): array
    {
        $textBody = $email->getTextBody();

        return [
            'subject' => $email->getSubject(),
            'from' => $this->formatAddresses($email->getFrom()),
            'to' => $this->formatAddresses($email->getTo()),
            'cc' => $this->formatAddresses($email->getCc()),
            'bcc' => $this->formatAddresses($email->getBcc()),
            'reply_to' => $this->formatAddresses($email->getReplyTo()),
            'text_body' => null !== $textBody ? $this->truncateBody($textBody) : null,
            'links' => $this->extractLinks($textBody, $email->getHtmlBody()),
            'has_html_body' => null !== $email->getHtmlBody(),
            'attachments' => $this->formatAttachments($email),
        ];
    }

    /**
     * @param Address[] $addresses
     *
     * @return string[]
     */
    private function formatAddresses(array $addresses): array
    {
        return array_map(
            static fn (Address $address): string => '' !== $address->getName()
                ? \sprintf('%s <%s>', $address->getName(), $address->getAddress())
                : $address->getAddress(),
            $addresses
        );
    }

    /**
     * @return string[]
     */
    private function extractLinks(?string $textBody, ?string $htmlBody): array
    {
        $links = [];

        if (null !== $textBody) {
            preg_match_all('/https?:\/\/[^\s<>"\']+/i', $textBody, $matches);
            $links = array_merge($links, $matches[0]);
        }

        if (null !== $htmlBody) {
            preg_match_all('/href=["\']+(https?:\/\/[^"\']+)["\']/i', $htmlBody, $matches);
            $links = array_merge($links, $matches[1]);
        }

        return array_values(array_unique($links));
    }

    private function truncateBody(string $body): string
    {
        $unicode = new UnicodeString($body);

        if ($unicode->length() <= self::MAX_BODY_LENGTH) {
            return $body;
        }

        return $unicode->slice(0, self::MAX_BODY_LENGTH)->toString().'...';
    }

    /**
     * @return array<array{filename: string|null, content_type: string|null}>
     */
    private function formatAttachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            // Symfony 8.0+ has dedicated getter methods, older versions need to extract from headers
            if (method_exists($attachment, 'getFilename')) {
                $filename = $attachment->getFilename();
                $contentType = $attachment->getContentType();
            } else {
                // Extract from headers for Symfony 5.4-7.x
                $headers = $attachment->getPreparedHeaders();

                $disposition = $headers->get('Content-Disposition');
                $filename = $disposition && method_exists($disposition, 'getParameter')
                    ? $disposition->getParameter('filename')
                    : null;

                $contentTypeHeader = $headers->get('Content-Type');
                $contentType = $contentTypeHeader && method_exists($contentTypeHeader, 'getBody')
                    ? $contentTypeHeader->getBody()
                    : null;
            }

            $attachments[] = [
                'filename' => $filename,
                'content_type' => $contentType,
            ];
        }

        return $attachments;
    }
}

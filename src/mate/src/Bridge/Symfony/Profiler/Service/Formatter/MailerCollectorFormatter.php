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

/**
 * Formats Mailer collector data for AI consumption.
 *
 * Extracts email message metadata (subject, masked recipients, attachments,
 * transport). Email bodies are omitted and links are stripped of their query
 * string and fragment, since reset/verify/magic-login URLs and the body itself
 * routinely carry one-time tokens and personal data that must not reach the AI.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<MessageDataCollector>
 */
final class MailerCollectorFormatter implements CollectorFormatterInterface
{
    public const OUTPUT_SCHEMA = [
        'title' => 'mailer',
        'type' => 'object',
        'properties' => [
            'message_count' => ['type' => 'integer'],
            'messages' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'description' => 'Email messages carry the keys from subject on. Other message types only carry type.',
                    'properties' => [
                        'transport' => ['type' => 'string'],
                        'is_queued' => ['type' => 'boolean'],
                        'type' => ['type' => 'string', 'description' => 'Class of a message that is not an Email.'],
                        'subject' => ['type' => ['string', 'null']],
                        'from' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Addresses with the local part masked as ***.'],
                        'to' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'cc' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'bcc' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'reply_to' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'has_text_body' => ['type' => 'boolean'],
                        'has_html_body' => ['type' => 'boolean'],
                        'links' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Without query string and fragment, token-like path segments masked.'],
                        'attachments' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'filename' => ['type' => ['string', 'null']],
                                    'content_type' => ['type' => ['string', 'null']],
                                ],
                                'required' => ['filename', 'content_type'],
                            ],
                        ],
                    ],
                    'required' => ['transport', 'is_queued'],
                ],
            ],
        ],
        'required' => ['message_count', 'messages'],
    ];

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
        return [
            'subject' => $email->getSubject(),
            'from' => $this->formatAddresses($email->getFrom()),
            'to' => $this->formatAddresses($email->getTo()),
            'cc' => $this->formatAddresses($email->getCc()),
            'bcc' => $this->formatAddresses($email->getBcc()),
            'reply_to' => $this->formatAddresses($email->getReplyTo()),
            'has_text_body' => null !== $email->getTextBody(),
            'has_html_body' => null !== $email->getHtmlBody(),
            'links' => $this->extractLinks($email->getTextBody(), $email->getHtmlBody()),
            'attachments' => $this->formatAttachments($email),
        ];
    }

    /**
     * Mask recipient PII (local part replaced with `***`, display name dropped).
     *
     * @param Address[] $addresses
     *
     * @return string[]
     */
    private function formatAddresses(array $addresses): array
    {
        return array_map(
            fn (Address $address): string => $this->maskAddress($address->getAddress()),
            $addresses
        );
    }

    private function maskAddress(string $address): string
    {
        $atPosition = strrpos($address, '@');
        if (false === $atPosition) {
            return '***';
        }

        return '***'.substr($address, $atPosition);
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

        $sanitized = [];
        foreach ($links as $link) {
            $clean = $this->stripUrlSecrets($link);
            if (null !== $clean) {
                $sanitized[] = $clean;
            }
        }

        return array_values(array_unique($sanitized));
    }

    /**
     * Keep scheme, host and the descriptive path so the AI sees which endpoint
     * was linked, but drop userinfo, query string and fragment, and mask any
     * high-entropy path segment — reset/verify/magic-login flows put the
     * one-time token in the path (e.g. `/reset-password/reset/{token}`), not
     * only the query string.
     */
    private function stripUrlSecrets(string $url): ?string
    {
        $parts = parse_url($url);
        if (false === $parts || !isset($parts['host'])) {
            return null;
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.$parts['host'].$port.$this->maskPathTokens($parts['path'] ?? '');
    }

    private function maskPathTokens(string $path): string
    {
        $segments = explode('/', $path);
        foreach ($segments as $index => $segment) {
            if ($this->looksLikeToken($segment)) {
                $segments[$index] = '***';
            }
        }

        return implode('/', $segments);
    }

    private function looksLikeToken(string $segment): bool
    {
        // Short segments are ids or descriptive words; keep them.
        if (\strlen($segment) < 16) {
            return false;
        }

        // A descriptive slug — lowercase words separated by single hyphens or
        // underscores, each starting with a letter — is kept so the AI still
        // sees the endpoint. Anything else of length >= 16 (hex/base64 blobs,
        // UUIDs, mixed-case or digit-bearing opaque strings) is a likely token.
        return 1 !== preg_match('/^[a-z][a-z0-9]*(?:[_-][a-z][a-z0-9]*)+$/', $segment);
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

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('gmail_search', 'Search for messages or threads in Gmail')]
#[AsTool('gmail_send_message', 'Send email messages via Gmail', method: 'sendMessage')]
#[AsTool('gmail_get_message', 'Get a specific Gmail message by ID', method: 'getMessage')]
#[AsTool('gmail_get_thread', 'Get a specific Gmail thread by ID', method: 'getThread')]
final readonly class Gmail
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        private array $options = [],
    ) {
    }

    /**
     * Search for messages or threads in Gmail.
     *
     * @param string $query      The Gmail query (e.g., "from:sender", "subject:subject", "is:unread")
     * @param string $resource   Whether to search for threads or messages (default: messages)
     * @param int    $maxResults The maximum number of results to return
     *
     * @return array<int, array{
     *     id: string,
     *     threadId: string,
     *     snippet: string,
     *     subject: string,
     *     sender: string,
     *     date: string,
     *     to: string,
     *     cc: string|null,
     * }>
     */
    public function __invoke(
        #[With(maximum: 500)]
        string $query,
        string $resource = 'messages',
        int $maxResults = 10,
    ): array {
        try {
            $response = $this->httpClient->request('GET', 'https://gmail.googleapis.com/gmail/v1/users/me/messages', [
                'headers' => $this->getHeaders(),
                'query' => [
                    'q' => $query,
                    'maxResults' => $maxResults,
                ],
            ]);

            $data = $response->toArray();
            $messages = $data['messages'] ?? [];

            if (empty($messages)) {
                return [];
            }

            $results = [];
            foreach ($messages as $message) {
                $messageDetails = $this->getMessageDetails($message['id']);
                if ($messageDetails) {
                    $results[] = $messageDetails;
                }
            }

            return $results;
        } catch (\Exception $e) {
            return [
                [
                    'id' => 'error',
                    'threadId' => 'error',
                    'snippet' => 'Error: '.$e->getMessage(),
                    'subject' => 'Error',
                    'sender' => '',
                    'date' => '',
                    'to' => '',
                    'cc' => null,
                ],
            ];
        }
    }

    /**
     * Send email messages via Gmail.
     *
     * @param string|array<int, string> $to      The list of recipients
     * @param string                    $subject The subject of the message
     * @param string                    $message The message content
     * @param string|array<int, string> $cc      The list of CC recipients (optional)
     * @param string|array<int, string> $bcc     The list of BCC recipients (optional)
     */
    public function sendMessage(
        string|array $to,
        string $subject,
        string $message,
        string|array|null $cc = null,
        string|array|null $bcc = null,
    ): string {
        try {
            // Normalize recipients to arrays
            $toArray = \is_array($to) ? $to : [$to];
            $ccArray = $cc ? (\is_array($cc) ? $cc : [$cc]) : [];
            $bccArray = $bcc ? (\is_array($bcc) ? $bcc : [$bcc]) : [];

            // Create email message
            $emailMessage = $this->createEmailMessage($toArray, $subject, $message, $ccArray, $bccArray);

            $response = $this->httpClient->request('POST', 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'headers' => $this->getHeaders(),
                'json' => ['raw' => base64_encode($emailMessage)],
            ]);

            $data = $response->toArray();

            return "Message sent successfully. Message ID: {$data['id']}";
        } catch (\Exception $e) {
            return 'Error sending message: '.$e->getMessage();
        }
    }

    /**
     * Get a specific Gmail message by ID.
     *
     * @param string $messageId The Gmail message ID
     *
     * @return array{
     *     id: string,
     *     threadId: string,
     *     snippet: string,
     *     body: string,
     *     subject: string,
     *     sender: string,
     *     date: string,
     *     to: string,
     *     cc: string|null,
     * }|null
     */
    public function getMessage(string $messageId): ?array
    {
        return $this->getMessageDetails($messageId);
    }

    /**
     * Get a specific Gmail thread by ID.
     *
     * @param string $threadId The Gmail thread ID
     *
     * @return array{
     *     id: string,
     *     snippet: string,
     *     messages: array<int, array{
     *         id: string,
     *         snippet: string,
     *         subject: string,
     *         sender: string,
     *         date: string,
     *     }>,
     * }|null
     */
    public function getThread(string $threadId): ?array
    {
        try {
            $response = $this->httpClient->request('GET', "https://gmail.googleapis.com/gmail/v1/users/me/threads/{$threadId}", [
                'headers' => $this->getHeaders(),
            ]);

            $data = $response->toArray();
            $messages = [];

            foreach ($data['messages'] as $message) {
                $messageDetails = $this->getMessageDetails($message['id']);
                if ($messageDetails) {
                    $messages[] = [
                        'id' => $messageDetails['id'],
                        'snippet' => $messageDetails['snippet'],
                        'subject' => $messageDetails['subject'],
                        'sender' => $messageDetails['sender'],
                        'date' => $messageDetails['date'],
                    ];
                }
            }

            return [
                'id' => $data['id'],
                'snippet' => $data['snippet'],
                'messages' => $messages,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get detailed message information.
     *
     * @return array{
     *     id: string,
     *     threadId: string,
     *     snippet: string,
     *     body: string,
     *     subject: string,
     *     sender: string,
     *     date: string,
     *     to: string,
     *     cc: string|null,
     * }|null
     */
    private function getMessageDetails(string $messageId): ?array
    {
        try {
            $response = $this->httpClient->request('GET', "https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}", [
                'headers' => $this->getHeaders(),
            ]);

            $data = $response->toArray();
            $headers = $this->extractHeaders($data['payload']['headers'] ?? []);

            // Extract message body
            $body = $this->extractMessageBody($data['payload']);

            return [
                'id' => $data['id'],
                'threadId' => $data['threadId'],
                'snippet' => $data['snippet'],
                'body' => $body,
                'subject' => $headers['subject'] ?? '',
                'sender' => $headers['from'] ?? '',
                'date' => $headers['date'] ?? '',
                'to' => $headers['to'] ?? '',
                'cc' => $headers['cc'] ?? null,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract headers from Gmail message.
     *
     * @param array<int, array{name: string, value: string}> $headers
     *
     * @return array<string, string>
     */
    private function extractHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $header) {
            $result[strtolower($header['name'])] = $header['value'];
        }

        return $result;
    }

    /**
     * Extract message body from Gmail message payload.
     */
    private function extractMessageBody(array $payload): string
    {
        // Handle multipart messages
        if (isset($payload['parts'])) {
            foreach ($payload['parts'] as $part) {
                if ('text/plain' === $part['mimeType'] && isset($part['body']['data'])) {
                    return base64_decode(str_replace(['-', '_'], ['+', '/'], $part['body']['data']));
                }
            }
        }

        // Handle simple messages
        if (isset($payload['body']['data'])) {
            return base64_decode(str_replace(['-', '_'], ['+', '/'], $payload['body']['data']));
        }

        return '';
    }

    /**
     * Create email message for sending.
     *
     * @param array<int, string> $to      Recipients
     * @param string             $subject Subject
     * @param string             $message Message content
     * @param array<int, string> $cc      CC recipients
     * @param array<int, string> $bcc     BCC recipients
     */
    private function createEmailMessage(array $to, string $subject, string $message, array $cc, array $bcc): string
    {
        $boundary = uniqid('boundary_');
        $headers = [
            'To: '.implode(', ', $to),
            'Subject: '.$subject,
            'Content-Type: multipart/alternative; boundary="'.$boundary.'"',
        ];

        if (!empty($cc)) {
            $headers[] = 'Cc: '.implode(', ', $cc);
        }

        if (!empty($bcc)) {
            $headers[] = 'Bcc: '.implode(', ', $bcc);
        }

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body .= strip_tags($message)."\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= $message."\r\n\r\n";
        $body .= "--{$boundary}--\r\n";

        return implode("\r\n", $headers)."\r\n\r\n".$body;
    }

    /**
     * Get authentication headers.
     *
     * @return array<string, string>
     */
    private function getHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->accessToken}",
            'Content-Type' => 'application/json',
        ];
    }
}

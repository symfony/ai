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
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('sendgrid_send_email', 'Tool that sends emails via SendGrid')]
#[AsTool('sendgrid_send_template_email', 'Tool that sends template emails via SendGrid', method: 'sendTemplateEmail')]
#[AsTool('sendgrid_get_email_activity', 'Tool that gets SendGrid email activity', method: 'getEmailActivity')]
#[AsTool('sendgrid_create_contact', 'Tool that creates SendGrid contacts', method: 'createContact')]
#[AsTool('sendgrid_get_contacts', 'Tool that gets SendGrid contacts', method: 'getContacts')]
#[AsTool('sendgrid_get_email_stats', 'Tool that gets SendGrid email statistics', method: 'getEmailStats')]
final readonly class SendGrid
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private array $options = [],
    ) {
    }

    /**
     * Send email via SendGrid.
     *
     * @param string                                                             $to          Recipient email address
     * @param string                                                             $from        Sender email address
     * @param string                                                             $fromName    Sender name
     * @param string                                                             $subject     Email subject
     * @param string                                                             $htmlContent HTML email content
     * @param string                                                             $textContent Plain text email content
     * @param array<int, string>                                                 $cc          CC recipients
     * @param array<int, string>                                                 $bcc         BCC recipients
     * @param array<int, array{filename: string, content: string, type: string}> $attachments Email attachments
     */
    public function __invoke(
        string $to,
        string $from,
        string $fromName,
        string $subject,
        string $htmlContent = '',
        string $textContent = '',
        array $cc = [],
        array $bcc = [],
        array $attachments = [],
    ): string {
        try {
            $payload = [
                'personalizations' => [
                    [
                        'to' => [['email' => $to]],
                        'subject' => $subject,
                    ],
                ],
                'from' => [
                    'email' => $from,
                    'name' => $fromName,
                ],
                'content' => [],
            ];

            // Add content
            if ($htmlContent) {
                $payload['content'][] = [
                    'type' => 'text/html',
                    'value' => $htmlContent,
                ];
            }
            if ($textContent) {
                $payload['content'][] = [
                    'type' => 'text/plain',
                    'value' => $textContent,
                ];
            }

            // Add CC recipients
            if (!empty($cc)) {
                $payload['personalizations'][0]['cc'] = array_map(fn ($email) => ['email' => $email], $cc);
            }

            // Add BCC recipients
            if (!empty($bcc)) {
                $payload['personalizations'][0]['bcc'] = array_map(fn ($email) => ['email' => $email], $bcc);
            }

            // Add attachments
            if (!empty($attachments)) {
                $payload['attachments'] = array_map(fn ($attachment) => [
                    'filename' => $attachment['filename'],
                    'content' => base64_encode($attachment['content']),
                    'type' => $attachment['type'],
                ], $attachments);
            }

            $response = $this->httpClient->request('POST', 'https://api.sendgrid.com/v3/mail/send', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            if (202 === $response->getStatusCode()) {
                return 'Email sent successfully';
            } else {
                $data = $response->toArray();

                return 'Error sending email: '.($data['errors'][0]['message'] ?? 'Unknown error');
            }
        } catch (\Exception $e) {
            return 'Error sending email: '.$e->getMessage();
        }
    }

    /**
     * Send template email via SendGrid.
     *
     * @param string               $to                  Recipient email address
     * @param string               $from                Sender email address
     * @param string               $fromName            Sender name
     * @param string               $templateId          SendGrid template ID
     * @param array<string, mixed> $dynamicTemplateData Template data
     */
    public function sendTemplateEmail(
        string $to,
        string $from,
        string $fromName,
        string $templateId,
        array $dynamicTemplateData = [],
    ): string {
        try {
            $payload = [
                'personalizations' => [
                    [
                        'to' => [['email' => $to]],
                        'dynamic_template_data' => $dynamicTemplateData,
                    ],
                ],
                'from' => [
                    'email' => $from,
                    'name' => $fromName,
                ],
                'template_id' => $templateId,
            ];

            $response = $this->httpClient->request('POST', 'https://api.sendgrid.com/v3/mail/send', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            if (202 === $response->getStatusCode()) {
                return 'Template email sent successfully';
            } else {
                $data = $response->toArray();

                return 'Error sending template email: '.($data['errors'][0]['message'] ?? 'Unknown error');
            }
        } catch (\Exception $e) {
            return 'Error sending template email: '.$e->getMessage();
        }
    }

    /**
     * Get SendGrid email activity.
     *
     * @param string $query     Search query
     * @param int    $limit     Number of results
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate   End date (YYYY-MM-DD)
     *
     * @return array<int, array{
     *     email: string,
     *     timestamp: int,
     *     event: string,
     *     sg_event_id: string,
     *     sg_message_id: string,
     *     response: string,
     *     attempt: string,
     *     category: array<int, string>,
     *     type: string,
     *     tls: int,
     *     ip: string,
     *     url: string,
     *     useragent: string,
     *     reason: string,
     *     status: string,
     *     smtp-id: string,
     *     unique_args: array<string, mixed>,
     * }>
     */
    public function getEmailActivity(
        string $query = '',
        int $limit = 100,
        string $startDate = '',
        string $endDate = '',
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 1000),
            ];

            if ($query) {
                $params['query'] = $query;
            }

            $response = $this->httpClient->request('GET', 'https://api.sendgrid.com/v3/messages', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                ],
                'query' => array_merge($params, [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]),
            ]);

            $data = $response->toArray();

            if (!isset($data['messages'])) {
                return [];
            }

            $activities = [];
            foreach ($data['messages'] as $message) {
                $activities[] = [
                    'email' => $message['email'] ?? '',
                    'timestamp' => $message['timestamp'] ?? 0,
                    'event' => $message['event'] ?? '',
                    'sg_event_id' => $message['sg_event_id'] ?? '',
                    'sg_message_id' => $message['sg_message_id'] ?? '',
                    'response' => $message['response'] ?? '',
                    'attempt' => $message['attempt'] ?? '',
                    'category' => $message['category'] ?? [],
                    'type' => $message['type'] ?? '',
                    'tls' => $message['tls'] ?? 0,
                    'ip' => $message['ip'] ?? '',
                    'url' => $message['url'] ?? '',
                    'useragent' => $message['useragent'] ?? '',
                    'reason' => $message['reason'] ?? '',
                    'status' => $message['status'] ?? '',
                    'smtp-id' => $message['smtp-id'] ?? '',
                    'unique_args' => $message['unique_args'] ?? [],
                ];
            }

            return $activities;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a SendGrid contact.
     *
     * @param string               $email        Contact email address
     * @param string               $firstName    Contact first name
     * @param string               $lastName     Contact last name
     * @param array<int, string>   $listIds      List IDs to add contact to
     * @param array<string, mixed> $customFields Custom field data
     */
    public function createContact(
        string $email,
        string $firstName = '',
        string $lastName = '',
        array $listIds = [],
        array $customFields = [],
    ): string {
        try {
            $payload = [
                'list_ids' => $listIds,
                'contacts' => [
                    [
                        'email' => $email,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'custom_fields' => $customFields,
                    ],
                ],
            ];

            $response = $this->httpClient->request('PUT', 'https://api.sendgrid.com/v3/marketing/contacts', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            if (202 === $response->getStatusCode()) {
                return 'Contact created successfully';
            } else {
                $data = $response->toArray();

                return 'Error creating contact: '.($data['errors'][0]['message'] ?? 'Unknown error');
            }
        } catch (\Exception $e) {
            return 'Error creating contact: '.$e->getMessage();
        }
    }

    /**
     * Get SendGrid contacts.
     *
     * @param int    $pageSize Number of contacts per page
     * @param string $query    Search query
     *
     * @return array<int, array{
     *     id: string,
     *     email: string,
     *     first_name: string,
     *     last_name: string,
     *     created_at: string,
     *     updated_at: string,
     *     custom_fields: array<string, mixed>,
     * }>
     */
    public function getContacts(
        int $pageSize = 100,
        string $query = '',
    ): array {
        try {
            $params = [
                'page_size' => min(max($pageSize, 1), 1000),
            ];

            if ($query) {
                $params['query'] = $query;
            }

            $response = $this->httpClient->request('GET', 'https://api.sendgrid.com/v3/marketing/contacts', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                ],
                'query' => $params,
            ]);

            $data = $response->toArray();

            if (!isset($data['result'])) {
                return [];
            }

            $contacts = [];
            foreach ($data['result'] as $contact) {
                $contacts[] = [
                    'id' => $contact['id'],
                    'email' => $contact['email'],
                    'first_name' => $contact['first_name'] ?? '',
                    'last_name' => $contact['last_name'] ?? '',
                    'created_at' => $contact['created_at'],
                    'updated_at' => $contact['updated_at'],
                    'custom_fields' => $contact['custom_fields'] ?? [],
                ];
            }

            return $contacts;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get SendGrid email statistics.
     *
     * @param string $startDate    Start date (YYYY-MM-DD)
     * @param string $endDate      End date (YYYY-MM-DD)
     * @param string $aggregatedBy Aggregation type (day, week, month)
     *
     * @return array<int, array{
     *     date: string,
     *     stats: array{
     *         type: string,
     *         name: string,
     *         delivered: int,
     *         requests: int,
     *         unique_clicks: int,
     *         unique_opens: int,
     *         unique_unsubscribes: int,
     *         bounces: int,
     *         blocks: int,
     *         spam_reports: int,
     *     },
     * }>
     */
    public function getEmailStats(
        string $startDate,
        string $endDate,
        string $aggregatedBy = 'day',
    ): array {
        try {
            $params = [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'aggregated_by' => $aggregatedBy,
            ];

            $response = $this->httpClient->request('GET', 'https://api.sendgrid.com/v3/stats', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                ],
                'query' => $params,
            ]);

            $data = $response->toArray();

            $stats = [];
            foreach ($data as $stat) {
                $stats[] = [
                    'date' => $stat['date'],
                    'stats' => [
                        'type' => $stat['stats'][0]['type'] ?? '',
                        'name' => $stat['stats'][0]['name'] ?? '',
                        'delivered' => $stat['stats'][0]['metrics']['delivered'] ?? 0,
                        'requests' => $stat['stats'][0]['metrics']['requests'] ?? 0,
                        'unique_clicks' => $stat['stats'][0]['metrics']['unique_clicks'] ?? 0,
                        'unique_opens' => $stat['stats'][0]['metrics']['unique_opens'] ?? 0,
                        'unique_unsubscribes' => $stat['stats'][0]['metrics']['unique_unsubscribes'] ?? 0,
                        'bounces' => $stat['stats'][0]['metrics']['bounces'] ?? 0,
                        'blocks' => $stat['stats'][0]['metrics']['blocks'] ?? 0,
                        'spam_reports' => $stat['stats'][0]['metrics']['spam_reports'] ?? 0,
                    ],
                ];
            }

            return $stats;
        } catch (\Exception $e) {
            return [];
        }
    }
}

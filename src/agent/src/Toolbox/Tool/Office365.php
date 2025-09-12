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
#[AsTool('office365_get_user_profile', 'Tool that gets Office 365 user profile')]
#[AsTool('office365_send_email', 'Tool that sends email via Office 365', method: 'sendEmail')]
#[AsTool('office365_get_emails', 'Tool that gets emails from Office 365', method: 'getEmails')]
#[AsTool('office365_get_calendar_events', 'Tool that gets calendar events', method: 'getCalendarEvents')]
#[AsTool('office365_create_calendar_event', 'Tool that creates calendar events', method: 'createCalendarEvent')]
#[AsTool('office365_get_drive_files', 'Tool that gets OneDrive files', method: 'getDriveFiles')]
#[AsTool('office365_upload_file', 'Tool that uploads files to OneDrive', method: 'uploadFile')]
#[AsTool('office365_get_teams', 'Tool that gets Microsoft Teams', method: 'getTeams')]
final readonly class Office365
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $accessToken,
        private string $baseUrl = 'https://graph.microsoft.com/v1.0',
        private array $options = [],
    ) {
    }

    /**
     * Get Office 365 user profile.
     *
     * @param string $userId User ID (empty for current user)
     *
     * @return array{
     *     success: bool,
     *     user: array{
     *         id: string,
     *         displayName: string,
     *         givenName: string,
     *         surname: string,
     *         userPrincipalName: string,
     *         mail: string,
     *         jobTitle: string,
     *         officeLocation: string,
     *         mobilePhone: string,
     *         businessPhones: array<int, string>,
     *         department: string,
     *         companyName: string,
     *         country: string,
     *         city: string,
     *         state: string,
     *         streetAddress: string,
     *         postalCode: string,
     *         preferredLanguage: string,
     *         accountEnabled: bool,
     *         createdDateTime: string,
     *         lastPasswordChangeDateTime: string,
     *         userType: string,
     *         userRoles: array<int, string>,
     *     },
     *     error: string,
     * }
     */
    public function __invoke(string $userId = ''): array
    {
        try {
            $endpoint = $userId ? "/users/{$userId}" : '/me';

            $response = $this->httpClient->request('GET', "{$this->baseUrl}{$endpoint}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'user' => [
                    'id' => $data['id'] ?? '',
                    'displayName' => $data['displayName'] ?? '',
                    'givenName' => $data['givenName'] ?? '',
                    'surname' => $data['surname'] ?? '',
                    'userPrincipalName' => $data['userPrincipalName'] ?? '',
                    'mail' => $data['mail'] ?? '',
                    'jobTitle' => $data['jobTitle'] ?? '',
                    'officeLocation' => $data['officeLocation'] ?? '',
                    'mobilePhone' => $data['mobilePhone'] ?? '',
                    'businessPhones' => $data['businessPhones'] ?? [],
                    'department' => $data['department'] ?? '',
                    'companyName' => $data['companyName'] ?? '',
                    'country' => $data['country'] ?? '',
                    'city' => $data['city'] ?? '',
                    'state' => $data['state'] ?? '',
                    'streetAddress' => $data['streetAddress'] ?? '',
                    'postalCode' => $data['postalCode'] ?? '',
                    'preferredLanguage' => $data['preferredLanguage'] ?? '',
                    'accountEnabled' => $data['accountEnabled'] ?? false,
                    'createdDateTime' => $data['createdDateTime'] ?? '',
                    'lastPasswordChangeDateTime' => $data['lastPasswordChangeDateTime'] ?? '',
                    'userType' => $data['userType'] ?? '',
                    'userRoles' => $data['userRoles'] ?? [],
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'user' => [
                    'id' => '',
                    'displayName' => '',
                    'givenName' => '',
                    'surname' => '',
                    'userPrincipalName' => '',
                    'mail' => '',
                    'jobTitle' => '',
                    'officeLocation' => '',
                    'mobilePhone' => '',
                    'businessPhones' => [],
                    'department' => '',
                    'companyName' => '',
                    'country' => '',
                    'city' => '',
                    'state' => '',
                    'streetAddress' => '',
                    'postalCode' => '',
                    'preferredLanguage' => '',
                    'accountEnabled' => false,
                    'createdDateTime' => '',
                    'lastPasswordChangeDateTime' => '',
                    'userType' => '',
                    'userRoles' => [],
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send email via Office 365.
     *
     * @param string             $to       Recipient email address
     * @param string             $subject  Email subject
     * @param string             $body     Email body
     * @param string             $bodyType Body type (text, html)
     * @param string             $from     Sender email address
     * @param array<int, string> $cc       CC recipients
     * @param array<int, string> $bcc      BCC recipients
     * @param array<int, array{
     *     name: string,
     *     contentType: string,
     *     contentBytes: string,
     * }> $attachments Email attachments
     *
     * @return array{
     *     success: bool,
     *     messageId: string,
     *     sentDateTime: string,
     *     message: string,
     *     error: string,
     * }
     */
    public function sendEmail(
        string $to,
        string $subject,
        string $body,
        string $bodyType = 'html',
        string $from = '',
        array $cc = [],
        array $bcc = [],
        array $attachments = [],
    ): array {
        try {
            $requestData = [
                'message' => [
                    'subject' => $subject,
                    'body' => [
                        'contentType' => $bodyType,
                        'content' => $body,
                    ],
                    'toRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => $to,
                            ],
                        ],
                    ],
                ],
                'saveToSentItems' => true,
            ];

            if ($from) {
                $requestData['message']['from'] = [
                    'emailAddress' => [
                        'address' => $from,
                    ],
                ];
            }

            if (!empty($cc)) {
                $requestData['message']['ccRecipients'] = array_map(fn ($email) => [
                    'emailAddress' => [
                        'address' => $email,
                    ],
                ], $cc);
            }

            if (!empty($bcc)) {
                $requestData['message']['bccRecipients'] = array_map(fn ($email) => [
                    'emailAddress' => [
                        'address' => $email,
                    ],
                ], $bcc);
            }

            if (!empty($attachments)) {
                $requestData['message']['attachments'] = $attachments;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/me/sendMail", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            return [
                'success' => true,
                'messageId' => $response->getHeaders()['x-ms-request-id'][0] ?? '',
                'sentDateTime' => date('c'),
                'message' => 'Email sent successfully',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'messageId' => '',
                'sentDateTime' => '',
                'message' => 'Failed to send email',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get emails from Office 365.
     *
     * @param string $folder  Mail folder (inbox, sentitems, deleteditems, drafts)
     * @param int    $limit   Number of emails
     * @param int    $offset  Offset for pagination
     * @param string $filter  OData filter
     * @param string $orderBy Order by field
     *
     * @return array{
     *     success: bool,
     *     emails: array<int, array{
     *         id: string,
     *         subject: string,
     *         bodyPreview: string,
     *         body: array{
     *             contentType: string,
     *             content: string,
     *         },
     *         from: array{
     *             emailAddress: array{
     *                 name: string,
     *                 address: string,
     *             },
     *         },
     *         toRecipients: array<int, array{
     *             emailAddress: array{
     *                 name: string,
     *                 address: string,
     *             },
     *         }>,
     *         ccRecipients: array<int, array{
     *             emailAddress: array{
     *                 name: string,
     *                 address: string,
     *             },
     *         }>,
     *         bccRecipients: array<int, array{
     *             emailAddress: array{
     *                 name: string,
     *                 address: string,
     *             },
     *         }>,
     *         receivedDateTime: string,
     *         sentDateTime: string,
     *         isRead: bool,
     *         importance: string,
     *         hasAttachments: bool,
     *         attachments: array<int, array{
     *             id: string,
     *             name: string,
     *             contentType: string,
     *             size: int,
     *         }>,
     *     }>,
     *     total: int,
     *     limit: int,
     *     offset: int,
     *     error: string,
     * }
     */
    public function getEmails(
        string $folder = 'inbox',
        int $limit = 20,
        int $offset = 0,
        string $filter = '',
        string $orderBy = 'receivedDateTime desc',
    ): array {
        try {
            $params = [
                '$top' => max(1, min($limit, 999)),
                '$skip' => max(0, $offset),
                '$orderby' => $orderBy,
                '$select' => 'id,subject,bodyPreview,body,from,toRecipients,ccRecipients,bccRecipients,receivedDateTime,sentDateTime,isRead,importance,hasAttachments,attachments',
            ];

            if ($filter) {
                $params['$filter'] = $filter;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/me/mailFolders/{$folder}/messages", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'emails' => array_map(fn ($email) => [
                    'id' => $email['id'] ?? '',
                    'subject' => $email['subject'] ?? '',
                    'bodyPreview' => $email['bodyPreview'] ?? '',
                    'body' => [
                        'contentType' => $email['body']['contentType'] ?? '',
                        'content' => $email['body']['content'] ?? '',
                    ],
                    'from' => [
                        'emailAddress' => [
                            'name' => $email['from']['emailAddress']['name'] ?? '',
                            'address' => $email['from']['emailAddress']['address'] ?? '',
                        ],
                    ],
                    'toRecipients' => array_map(fn ($recipient) => [
                        'emailAddress' => [
                            'name' => $recipient['emailAddress']['name'] ?? '',
                            'address' => $recipient['emailAddress']['address'] ?? '',
                        ],
                    ], $email['toRecipients'] ?? []),
                    'ccRecipients' => array_map(fn ($recipient) => [
                        'emailAddress' => [
                            'name' => $recipient['emailAddress']['name'] ?? '',
                            'address' => $recipient['emailAddress']['address'] ?? '',
                        ],
                    ], $email['ccRecipients'] ?? []),
                    'bccRecipients' => array_map(fn ($recipient) => [
                        'emailAddress' => [
                            'name' => $recipient['emailAddress']['name'] ?? '',
                            'address' => $recipient['emailAddress']['address'] ?? '',
                        ],
                    ], $email['bccRecipients'] ?? []),
                    'receivedDateTime' => $email['receivedDateTime'] ?? '',
                    'sentDateTime' => $email['sentDateTime'] ?? '',
                    'isRead' => $email['isRead'] ?? false,
                    'importance' => $email['importance'] ?? 'normal',
                    'hasAttachments' => $email['hasAttachments'] ?? false,
                    'attachments' => array_map(fn ($attachment) => [
                        'id' => $attachment['id'] ?? '',
                        'name' => $attachment['name'] ?? '',
                        'contentType' => $attachment['contentType'] ?? '',
                        'size' => $attachment['size'] ?? 0,
                    ], $email['attachments'] ?? []),
                ], $data['value'] ?? []),
                'total' => \count($data['value'] ?? []),
                'limit' => $limit,
                'offset' => $offset,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'emails' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get calendar events.
     *
     * @param string $startTime  Start time (ISO 8601 format)
     * @param string $endTime    End time (ISO 8601 format)
     * @param int    $limit      Number of events
     * @param string $calendarId Calendar ID (empty for default)
     *
     * @return array{
     *     success: bool,
     *     events: array<int, array{
     *         id: string,
     *         subject: string,
     *         bodyPreview: string,
     *         start: array{
     *             dateTime: string,
     *             timeZone: string,
     *         },
     *         end: array{
     *             dateTime: string,
     *             timeZone: string,
     *         },
     *         location: array{
     *             displayName: string,
     *             address: array<string, mixed>,
     *         },
     *         attendees: array<int, array{
     *             emailAddress: array{
     *                 name: string,
     *                 address: string,
     *             },
     *             type: string,
     *             status: array{
     *                 response: string,
     *                 time: string,
     *             },
     *         }>,
     *         organizer: array{
     *             emailAddress: array{
     *                 name: string,
     *                 address: string,
     *             },
     *         },
     *         isAllDay: bool,
     *         isCancelled: bool,
     *         isOnlineMeeting: bool,
     *         onlineMeetingProvider: string,
     *         onlineMeetingUrl: string,
     *         recurrence: array<string, mixed>,
     *         reminderMinutesBeforeStart: int,
     *         sensitivity: string,
     *         showAs: string,
     *         importance: string,
     *         createdDateTime: string,
     *         lastModifiedDateTime: string,
     *     }>,
     *     total: int,
     *     error: string,
     * }
     */
    public function getCalendarEvents(
        string $startTime = '',
        string $endTime = '',
        int $limit = 50,
        string $calendarId = '',
    ): array {
        try {
            $params = [
                '$top' => max(1, min($limit, 999)),
                '$orderby' => 'start/dateTime asc',
            ];

            if ($startTime && $endTime) {
                $params['$filter'] = "start/dateTime ge '{$startTime}' and end/dateTime le '{$endTime}'";
            }

            $endpoint = $calendarId ? "/me/calendars/{$calendarId}/events" : '/me/events';

            $response = $this->httpClient->request('GET', "{$this->baseUrl}{$endpoint}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'events' => array_map(fn ($event) => [
                    'id' => $event['id'] ?? '',
                    'subject' => $event['subject'] ?? '',
                    'bodyPreview' => $event['bodyPreview'] ?? '',
                    'start' => [
                        'dateTime' => $event['start']['dateTime'] ?? '',
                        'timeZone' => $event['start']['timeZone'] ?? '',
                    ],
                    'end' => [
                        'dateTime' => $event['end']['dateTime'] ?? '',
                        'timeZone' => $event['end']['timeZone'] ?? '',
                    ],
                    'location' => [
                        'displayName' => $event['location']['displayName'] ?? '',
                        'address' => $event['location']['address'] ?? [],
                    ],
                    'attendees' => array_map(fn ($attendee) => [
                        'emailAddress' => [
                            'name' => $attendee['emailAddress']['name'] ?? '',
                            'address' => $attendee['emailAddress']['address'] ?? '',
                        ],
                        'type' => $attendee['type'] ?? '',
                        'status' => [
                            'response' => $attendee['status']['response'] ?? '',
                            'time' => $attendee['status']['time'] ?? '',
                        ],
                    ], $event['attendees'] ?? []),
                    'organizer' => [
                        'emailAddress' => [
                            'name' => $event['organizer']['emailAddress']['name'] ?? '',
                            'address' => $event['organizer']['emailAddress']['address'] ?? '',
                        ],
                    ],
                    'isAllDay' => $event['isAllDay'] ?? false,
                    'isCancelled' => $event['isCancelled'] ?? false,
                    'isOnlineMeeting' => $event['isOnlineMeeting'] ?? false,
                    'onlineMeetingProvider' => $event['onlineMeetingProvider'] ?? '',
                    'onlineMeetingUrl' => $event['onlineMeetingUrl'] ?? '',
                    'recurrence' => $event['recurrence'] ?? [],
                    'reminderMinutesBeforeStart' => $event['reminderMinutesBeforeStart'] ?? 15,
                    'sensitivity' => $event['sensitivity'] ?? 'normal',
                    'showAs' => $event['showAs'] ?? 'busy',
                    'importance' => $event['importance'] ?? 'normal',
                    'createdDateTime' => $event['createdDateTime'] ?? '',
                    'lastModifiedDateTime' => $event['lastModifiedDateTime'] ?? '',
                ], $data['value'] ?? []),
                'total' => \count($data['value'] ?? []),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'events' => [],
                'total' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create calendar event.
     *
     * @param string             $subject    Event subject
     * @param string             $startTime  Start time (ISO 8601 format)
     * @param string             $endTime    End time (ISO 8601 format)
     * @param string             $body       Event body
     * @param string             $location   Event location
     * @param array<int, string> $attendees  Attendee email addresses
     * @param string             $calendarId Calendar ID (empty for default)
     * @param bool               $isAllDay   Is all day event
     * @param string             $timeZone   Time zone
     *
     * @return array{
     *     success: bool,
     *     event: array{
     *         id: string,
     *         subject: string,
     *         start: array{
     *             dateTime: string,
     *             timeZone: string,
     *         },
     *         end: array{
     *             dateTime: string,
     *             timeZone: string,
     *         },
     *         location: array{
     *             displayName: string,
     *         },
     *         attendees: array<int, array{
     *             emailAddress: array{
     *                 address: string,
     *             },
     *         }>,
     *         isAllDay: bool,
     *         createdDateTime: string,
     *     },
     *     error: string,
     * }
     */
    public function createCalendarEvent(
        string $subject,
        string $startTime,
        string $endTime,
        string $body = '',
        string $location = '',
        array $attendees = [],
        string $calendarId = '',
        bool $isAllDay = false,
        string $timeZone = 'UTC',
    ): array {
        try {
            $requestData = [
                'subject' => $subject,
                'start' => [
                    'dateTime' => $startTime,
                    'timeZone' => $timeZone,
                ],
                'end' => [
                    'dateTime' => $endTime,
                    'timeZone' => $timeZone,
                ],
                'isAllDay' => $isAllDay,
            ];

            if ($body) {
                $requestData['body'] = [
                    'contentType' => 'html',
                    'content' => $body,
                ];
            }

            if ($location) {
                $requestData['location'] = [
                    'displayName' => $location,
                ];
            }

            if (!empty($attendees)) {
                $requestData['attendees'] = array_map(fn ($email) => [
                    'emailAddress' => [
                        'address' => $email,
                    ],
                    'type' => 'required',
                ], $attendees);
            }

            $endpoint = $calendarId ? "/me/calendars/{$calendarId}/events" : '/me/events';

            $response = $this->httpClient->request('POST', "{$this->baseUrl}{$endpoint}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'event' => [
                    'id' => $data['id'] ?? '',
                    'subject' => $data['subject'] ?? $subject,
                    'start' => [
                        'dateTime' => $data['start']['dateTime'] ?? $startTime,
                        'timeZone' => $data['start']['timeZone'] ?? $timeZone,
                    ],
                    'end' => [
                        'dateTime' => $data['end']['dateTime'] ?? $endTime,
                        'timeZone' => $data['end']['timeZone'] ?? $timeZone,
                    ],
                    'location' => [
                        'displayName' => $data['location']['displayName'] ?? $location,
                    ],
                    'attendees' => array_map(fn ($attendee) => [
                        'emailAddress' => [
                            'address' => $attendee['emailAddress']['address'] ?? '',
                        ],
                    ], $data['attendees'] ?? []),
                    'isAllDay' => $data['isAllDay'] ?? $isAllDay,
                    'createdDateTime' => $data['createdDateTime'] ?? date('c'),
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'event' => [
                    'id' => '',
                    'subject' => $subject,
                    'start' => ['dateTime' => $startTime, 'timeZone' => $timeZone],
                    'end' => ['dateTime' => $endTime, 'timeZone' => $timeZone],
                    'location' => ['displayName' => $location],
                    'attendees' => [],
                    'isAllDay' => $isAllDay,
                    'createdDateTime' => '',
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get OneDrive files.
     *
     * @param string $folderPath Folder path (empty for root)
     * @param int    $limit      Number of files
     * @param int    $offset     Offset for pagination
     * @param string $filter     Filter criteria
     *
     * @return array{
     *     success: bool,
     *     files: array<int, array{
     *         id: string,
     *         name: string,
     *         size: int,
     *         webUrl: string,
     *         downloadUrl: string,
     *         file: array{
     *             mimeType: string,
     *             hashes: array<string, string>,
     *         },
     *         folder: array{
     *             childCount: int,
     *         },
     *         createdDateTime: string,
     *         lastModifiedDateTime: string,
     *         createdBy: array{
     *             user: array{
     *                 displayName: string,
     *                 email: string,
     *             },
     *         },
     *         lastModifiedBy: array{
     *             user: array{
     *                 displayName: string,
     *                 email: string,
     *             },
     *         },
     *     }>,
     *     total: int,
     *     limit: int,
     *     offset: int,
     *     error: string,
     * }
     */
    public function getDriveFiles(
        string $folderPath = '',
        int $limit = 50,
        int $offset = 0,
        string $filter = '',
    ): array {
        try {
            $params = [
                '$top' => max(1, min($limit, 999)),
                '$skip' => max(0, $offset),
            ];

            if ($filter) {
                $params['$filter'] = $filter;
            }

            $endpoint = $folderPath ? "/me/drive/root:/{$folderPath}:/children" : '/me/drive/root/children';

            $response = $this->httpClient->request('GET', "{$this->baseUrl}{$endpoint}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'files' => array_map(fn ($file) => [
                    'id' => $file['id'] ?? '',
                    'name' => $file['name'] ?? '',
                    'size' => $file['size'] ?? 0,
                    'webUrl' => $file['webUrl'] ?? '',
                    'downloadUrl' => $file['@microsoft.graph.downloadUrl'] ?? '',
                    'file' => [
                        'mimeType' => $file['file']['mimeType'] ?? '',
                        'hashes' => $file['file']['hashes'] ?? [],
                    ],
                    'folder' => [
                        'childCount' => $file['folder']['childCount'] ?? 0,
                    ],
                    'createdDateTime' => $file['createdDateTime'] ?? '',
                    'lastModifiedDateTime' => $file['lastModifiedDateTime'] ?? '',
                    'createdBy' => [
                        'user' => [
                            'displayName' => $file['createdBy']['user']['displayName'] ?? '',
                            'email' => $file['createdBy']['user']['email'] ?? '',
                        ],
                    ],
                    'lastModifiedBy' => [
                        'user' => [
                            'displayName' => $file['lastModifiedBy']['user']['displayName'] ?? '',
                            'email' => $file['lastModifiedBy']['user']['email'] ?? '',
                        ],
                    ],
                ], $data['value'] ?? []),
                'total' => \count($data['value'] ?? []),
                'limit' => $limit,
                'offset' => $offset,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'files' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload file to OneDrive.
     *
     * @param string $filePath    Local file path
     * @param string $destination Destination path in OneDrive
     * @param bool   $overwrite   Overwrite existing file
     *
     * @return array{
     *     success: bool,
     *     file: array{
     *         id: string,
     *         name: string,
     *         size: int,
     *         webUrl: string,
     *         downloadUrl: string,
     *         createdDateTime: string,
     *         lastModifiedDateTime: string,
     *     },
     *     message: string,
     *     error: string,
     * }
     */
    public function uploadFile(
        string $filePath,
        string $destination,
        bool $overwrite = false,
    ): array {
        try {
            if (!file_exists($filePath)) {
                throw new \InvalidArgumentException("File not found: {$filePath}.");
            }

            $fileContent = file_get_contents($filePath);
            $fileName = basename($filePath);

            $headers = [
                'Authorization' => "Bearer {$this->accessToken}",
                'Content-Type' => mime_content_type($filePath) ?: 'application/octet-stream',
            ];

            if ($overwrite) {
                $headers['Content-Length'] = \strlen($fileContent);
            }

            $response = $this->httpClient->request('PUT', "{$this->baseUrl}/me/drive/root:/{$destination}/{$fileName}:/content", [
                'headers' => $headers,
                'body' => $fileContent,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'file' => [
                    'id' => $data['id'] ?? '',
                    'name' => $data['name'] ?? $fileName,
                    'size' => $data['size'] ?? \strlen($fileContent),
                    'webUrl' => $data['webUrl'] ?? '',
                    'downloadUrl' => $data['@microsoft.graph.downloadUrl'] ?? '',
                    'createdDateTime' => $data['createdDateTime'] ?? date('c'),
                    'lastModifiedDateTime' => $data['lastModifiedDateTime'] ?? date('c'),
                ],
                'message' => 'File uploaded successfully',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'file' => [
                    'id' => '',
                    'name' => basename($filePath),
                    'size' => 0,
                    'webUrl' => '',
                    'downloadUrl' => '',
                    'createdDateTime' => '',
                    'lastModifiedDateTime' => '',
                ],
                'message' => 'Failed to upload file',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Microsoft Teams.
     *
     * @param int    $limit  Number of teams
     * @param int    $offset Offset for pagination
     * @param string $filter Filter criteria
     *
     * @return array{
     *     success: bool,
     *     teams: array<int, array{
     *         id: string,
     *         displayName: string,
     *         description: string,
     *         visibility: string,
     *         createdDateTime: string,
     *         webUrl: string,
     *         isArchived: bool,
     *         memberSettings: array<string, mixed>,
     *         guestSettings: array<string, mixed>,
     *         messagingSettings: array<string, mixed>,
     *         funSettings: array<string, mixed>,
     *         discoverySettings: array<string, mixed>,
     *         summary: array{
     *             ownersCount: int,
     *             membersCount: int,
     *             guestsCount: int,
     *         },
     *     }>,
     *     total: int,
     *     limit: int,
     *     offset: int,
     *     error: string,
     * }
     */
    public function getTeams(
        int $limit = 20,
        int $offset = 0,
        string $filter = '',
    ): array {
        try {
            $params = [
                '$top' => max(1, min($limit, 999)),
                '$skip' => max(0, $offset),
            ];

            if ($filter) {
                $params['$filter'] = $filter;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/me/joinedTeams", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'teams' => array_map(fn ($team) => [
                    'id' => $team['id'] ?? '',
                    'displayName' => $team['displayName'] ?? '',
                    'description' => $team['description'] ?? '',
                    'visibility' => $team['visibility'] ?? '',
                    'createdDateTime' => $team['createdDateTime'] ?? '',
                    'webUrl' => $team['webUrl'] ?? '',
                    'isArchived' => $team['isArchived'] ?? false,
                    'memberSettings' => $team['memberSettings'] ?? [],
                    'guestSettings' => $team['guestSettings'] ?? [],
                    'messagingSettings' => $team['messagingSettings'] ?? [],
                    'funSettings' => $team['funSettings'] ?? [],
                    'discoverySettings' => $team['discoverySettings'] ?? [],
                    'summary' => [
                        'ownersCount' => $team['summary']['ownersCount'] ?? 0,
                        'membersCount' => $team['summary']['membersCount'] ?? 0,
                        'guestsCount' => $team['summary']['guestsCount'] ?? 0,
                    ],
                ], $data['value'] ?? []),
                'total' => \count($data['value'] ?? []),
                'limit' => $limit,
                'offset' => $offset,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'teams' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'error' => $e->getMessage(),
            ];
        }
    }
}

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
#[AsTool('google_calendar_list_events', 'Tool that lists events from Google Calendar')]
#[AsTool('google_calendar_create_event', 'Tool that creates events in Google Calendar', method: 'createEvent')]
#[AsTool('google_calendar_update_event', 'Tool that updates events in Google Calendar', method: 'updateEvent')]
#[AsTool('google_calendar_delete_event', 'Tool that deletes events from Google Calendar', method: 'deleteEvent')]
#[AsTool('google_calendar_list_calendars', 'Tool that lists Google Calendars', method: 'listCalendars')]
final readonly class GoogleCalendar
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        private string $calendarId = 'primary',
        private array $options = [],
    ) {
    }

    /**
     * List events from Google Calendar.
     *
     * @param string $timeMin      Lower bound (exclusive) for an event's end time to filter by
     * @param string $timeMax      Upper bound (exclusive) for an event's start time to filter by
     * @param int    $maxResults   Maximum number of events to return
     * @param string $orderBy      Order of the events returned (startTime, updated)
     * @param bool   $singleEvents Whether to expand recurring events into instances
     *
     * @return array<int, array{
     *     id: string,
     *     summary: string,
     *     description: string,
     *     start: array{dateTime: string, timeZone: string},
     *     end: array{dateTime: string, timeZone: string},
     *     location: string,
     *     attendees: array<int, array{
     *         email: string,
     *         displayName: string,
     *         responseStatus: string,
     *     }>,
     *     creator: array{email: string, displayName: string},
     *     organizer: array{email: string, displayName: string},
     *     htmlLink: string,
     *     status: string,
     *     transparency: string,
     *     visibility: string,
     * }>
     */
    public function __invoke(
        string $timeMin = '',
        string $timeMax = '',
        int $maxResults = 10,
        string $orderBy = 'startTime',
        bool $singleEvents = true,
    ): array {
        try {
            $params = [
                'maxResults' => $maxResults,
                'singleEvents' => $singleEvents,
                'orderBy' => $orderBy,
            ];

            if ($timeMin) {
                $params['timeMin'] = $timeMin;
            }
            if ($timeMax) {
                $params['timeMax'] = $timeMax;
            }

            $response = $this->httpClient->request('GET', "https://www.googleapis.com/calendar/v3/calendars/{$this->calendarId}/events", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['items'])) {
                return [];
            }

            $events = [];
            foreach ($data['items'] as $event) {
                $events[] = [
                    'id' => $event['id'],
                    'summary' => $event['summary'] ?? '',
                    'description' => $event['description'] ?? '',
                    'start' => [
                        'dateTime' => $event['start']['dateTime'] ?? $event['start']['date'] ?? '',
                        'timeZone' => $event['start']['timeZone'] ?? 'UTC',
                    ],
                    'end' => [
                        'dateTime' => $event['end']['dateTime'] ?? $event['end']['date'] ?? '',
                        'timeZone' => $event['end']['timeZone'] ?? 'UTC',
                    ],
                    'location' => $event['location'] ?? '',
                    'attendees' => array_map(fn ($attendee) => [
                        'email' => $attendee['email'],
                        'displayName' => $attendee['displayName'] ?? '',
                        'responseStatus' => $attendee['responseStatus'] ?? 'needsAction',
                    ], $event['attendees'] ?? []),
                    'creator' => [
                        'email' => $event['creator']['email'],
                        'displayName' => $event['creator']['displayName'] ?? '',
                    ],
                    'organizer' => [
                        'email' => $event['organizer']['email'],
                        'displayName' => $event['organizer']['displayName'] ?? '',
                    ],
                    'htmlLink' => $event['htmlLink'],
                    'status' => $event['status'] ?? 'confirmed',
                    'transparency' => $event['transparency'] ?? 'opaque',
                    'visibility' => $event['visibility'] ?? 'default',
                ];
            }

            return $events;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create an event in Google Calendar.
     *
     * @param string             $summary     Event title/summary
     * @param string             $startTime   Event start time (ISO 8601 format)
     * @param string             $endTime     Event end time (ISO 8601 format)
     * @param string             $description Event description
     * @param string             $location    Event location
     * @param array<int, string> $attendees   List of attendee email addresses
     * @param string             $timeZone    Time zone for the event
     *
     * @return array{
     *     id: string,
     *     summary: string,
     *     description: string,
     *     start: array{dateTime: string, timeZone: string},
     *     end: array{dateTime: string, timeZone: string},
     *     location: string,
     *     attendees: array<int, array{email: string, responseStatus: string}>,
     *     htmlLink: string,
     *     status: string,
     * }|string
     */
    public function createEvent(
        string $summary,
        string $startTime,
        string $endTime,
        string $description = '',
        string $location = '',
        array $attendees = [],
        string $timeZone = 'UTC',
    ): array|string {
        try {
            $eventData = [
                'summary' => $summary,
                'description' => $description,
                'location' => $location,
                'start' => [
                    'dateTime' => $startTime,
                    'timeZone' => $timeZone,
                ],
                'end' => [
                    'dateTime' => $endTime,
                    'timeZone' => $timeZone,
                ],
            ];

            if (!empty($attendees)) {
                $eventData['attendees'] = array_map(fn ($email) => [
                    'email' => $email,
                ], $attendees);
            }

            $response = $this->httpClient->request('POST', "https://www.googleapis.com/calendar/v3/calendars/{$this->calendarId}/events", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $eventData,
            ]);

            $data = $response->toArray();

            return [
                'id' => $data['id'],
                'summary' => $data['summary'],
                'description' => $data['description'] ?? '',
                'start' => [
                    'dateTime' => $data['start']['dateTime'],
                    'timeZone' => $data['start']['timeZone'],
                ],
                'end' => [
                    'dateTime' => $data['end']['dateTime'],
                    'timeZone' => $data['end']['timeZone'],
                ],
                'location' => $data['location'] ?? '',
                'attendees' => array_map(fn ($attendee) => [
                    'email' => $attendee['email'],
                    'responseStatus' => $attendee['responseStatus'] ?? 'needsAction',
                ], $data['attendees'] ?? []),
                'htmlLink' => $data['htmlLink'],
                'status' => $data['status'],
            ];
        } catch (\Exception $e) {
            return 'Error creating event: '.$e->getMessage();
        }
    }

    /**
     * Update an existing event in Google Calendar.
     *
     * @param string               $eventId Event ID to update
     * @param array<string, mixed> $updates Fields to update
     *
     * @return array<string, mixed>|string
     */
    public function updateEvent(string $eventId, array $updates): array|string
    {
        try {
            $response = $this->httpClient->request('PUT', "https://www.googleapis.com/calendar/v3/calendars/{$this->calendarId}/events/{$eventId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $updates,
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            return 'Error updating event: '.$e->getMessage();
        }
    }

    /**
     * Delete an event from Google Calendar.
     *
     * @param string $eventId Event ID to delete
     */
    public function deleteEvent(string $eventId): string
    {
        try {
            $response = $this->httpClient->request('DELETE', "https://www.googleapis.com/calendar/v3/calendars/{$this->calendarId}/events/{$eventId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
            ]);

            if (204 === $response->getStatusCode()) {
                return "Event {$eventId} deleted successfully";
            } else {
                return 'Failed to delete event';
            }
        } catch (\Exception $e) {
            return 'Error deleting event: '.$e->getMessage();
        }
    }

    /**
     * List Google Calendars.
     *
     * @return array<int, array{
     *     id: string,
     *     summary: string,
     *     description: string,
     *     timeZone: string,
     *     accessRole: string,
     *     backgroundColor: string,
     *     foregroundColor: string,
     *     selected: bool,
     *     primary: bool,
     * }>
     */
    public function listCalendars(): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://www.googleapis.com/calendar/v3/users/me/calendarList', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['items'])) {
                return [];
            }

            $calendars = [];
            foreach ($data['items'] as $calendar) {
                $calendars[] = [
                    'id' => $calendar['id'],
                    'summary' => $calendar['summary'],
                    'description' => $calendar['description'] ?? '',
                    'timeZone' => $calendar['timeZone'] ?? 'UTC',
                    'accessRole' => $calendar['accessRole'],
                    'backgroundColor' => $calendar['backgroundColor'] ?? '#1a73e8',
                    'foregroundColor' => $calendar['foregroundColor'] ?? '#ffffff',
                    'selected' => $calendar['selected'] ?? false,
                    'primary' => $calendar['primary'] ?? false,
                ];
            }

            return $calendars;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get free/busy information for a time range.
     *
     * @param string             $timeMin     Start time for the query (ISO 8601 format)
     * @param string             $timeMax     End time for the query (ISO 8601 format)
     * @param array<int, string> $calendarIds List of calendar IDs to check
     *
     * @return array{
     *     calendars: array<string, array{
     *         busy: array<int, array{start: string, end: string}>,
     *     }>,
     *     timeMin: string,
     *     timeMax: string,
     * }|string
     */
    public function getFreeBusy(string $timeMin, string $timeMax, array $calendarIds = []): array|string
    {
        try {
            $requestBody = [
                'timeMin' => $timeMin,
                'timeMax' => $timeMax,
                'items' => array_map(fn ($id) => ['id' => $id], $calendarIds ?: [$this->calendarId]),
            ];

            $response = $this->httpClient->request('POST', 'https://www.googleapis.com/calendar/v3/freeBusy', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestBody,
            ]);

            $data = $response->toArray();

            return [
                'calendars' => $data['calendars'],
                'timeMin' => $data['timeMin'],
                'timeMax' => $data['timeMax'],
            ];
        } catch (\Exception $e) {
            return 'Error getting free/busy information: '.$e->getMessage();
        }
    }
}

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
#[AsTool('zoom_create_meeting', 'Tool that creates Zoom meetings')]
#[AsTool('zoom_list_meetings', 'Tool that lists Zoom meetings', method: 'listMeetings')]
#[AsTool('zoom_get_meeting', 'Tool that gets Zoom meeting details', method: 'getMeeting')]
#[AsTool('zoom_update_meeting', 'Tool that updates Zoom meetings', method: 'updateMeeting')]
#[AsTool('zoom_delete_meeting', 'Tool that deletes Zoom meetings', method: 'deleteMeeting')]
#[AsTool('zoom_get_meeting_participants', 'Tool that gets Zoom meeting participants', method: 'getMeetingParticipants')]
#[AsTool('zoom_create_user', 'Tool that creates Zoom users', method: 'createUser')]
final readonly class Zoom
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        private string $apiVersion = 'v2',
        private array $options = [],
    ) {
    }

    /**
     * Create a Zoom meeting.
     *
     * @param string               $topic     Meeting topic
     * @param string               $startTime Meeting start time (ISO 8601 format)
     * @param int                  $duration  Meeting duration in minutes
     * @param string               $type      Meeting type (1=instant, 2=scheduled, 3=recurring_no_fixed_time, 8=recurring_fixed_time)
     * @param string               $timezone  Meeting timezone
     * @param string               $password  Meeting password (optional)
     * @param array<string, mixed> $settings  Meeting settings
     *
     * @return array{
     *     id: int,
     *     topic: string,
     *     type: int,
     *     start_time: string,
     *     duration: int,
     *     timezone: string,
     *     password: string,
     *     join_url: string,
     *     start_url: string,
     *     created_at: string,
     *     settings: array{
     *         host_video: bool,
     *         participant_video: bool,
     *         cn_meeting: bool,
     *         in_meeting: bool,
     *         join_before_host: bool,
     *         jbh_time: int,
     *         mute_upon_entry: bool,
     *         watermark: bool,
     *         use_pmi: bool,
     *         approval_type: int,
     *         audio: string,
     *         auto_recording: string,
     *         enforce_login: bool,
     *         enforce_login_domains: string,
     *         alternative_hosts: string,
     *         close_registration: bool,
     *         show_share_button: bool,
     *         allow_multiple_devices: bool,
     *         registrants_confirmation_email: bool,
     *         waiting_room: bool,
     *         request_permission_to_unmute_participants: bool,
     *         global_dial_in_countries: array<int, string>,
     *         registrants_email_notification: bool,
     *     },
     * }|string
     */
    public function __invoke(
        string $topic,
        string $startTime = '',
        int $duration = 60,
        int $type = 2,
        string $timezone = 'UTC',
        string $password = '',
        array $settings = [],
    ): array|string {
        try {
            $payload = [
                'topic' => $topic,
                'type' => $type,
                'duration' => $duration,
                'timezone' => $timezone,
            ];

            if ($startTime) {
                $payload['start_time'] = $startTime;
            }

            if ($password) {
                $payload['password'] = $password;
            }

            if (!empty($settings)) {
                $payload['settings'] = $settings;
            }

            $response = $this->httpClient->request('POST', "https://api.zoom.us/{$this->apiVersion}/users/me/meetings", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['code'])) {
                return 'Error creating meeting: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'topic' => $data['topic'],
                'type' => $data['type'],
                'start_time' => $data['start_time'],
                'duration' => $data['duration'],
                'timezone' => $data['timezone'],
                'password' => $data['password'] ?? '',
                'join_url' => $data['join_url'],
                'start_url' => $data['start_url'],
                'created_at' => $data['created_at'],
                'settings' => [
                    'host_video' => $data['settings']['host_video'] ?? false,
                    'participant_video' => $data['settings']['participant_video'] ?? false,
                    'cn_meeting' => $data['settings']['cn_meeting'] ?? false,
                    'in_meeting' => $data['settings']['in_meeting'] ?? false,
                    'join_before_host' => $data['settings']['join_before_host'] ?? false,
                    'jbh_time' => $data['settings']['jbh_time'] ?? 0,
                    'mute_upon_entry' => $data['settings']['mute_upon_entry'] ?? false,
                    'watermark' => $data['settings']['watermark'] ?? false,
                    'use_pmi' => $data['settings']['use_pmi'] ?? false,
                    'approval_type' => $data['settings']['approval_type'] ?? 2,
                    'audio' => $data['settings']['audio'] ?? 'both',
                    'auto_recording' => $data['settings']['auto_recording'] ?? 'local',
                    'enforce_login' => $data['settings']['enforce_login'] ?? false,
                    'enforce_login_domains' => $data['settings']['enforce_login_domains'] ?? '',
                    'alternative_hosts' => $data['settings']['alternative_hosts'] ?? '',
                    'close_registration' => $data['settings']['close_registration'] ?? false,
                    'show_share_button' => $data['settings']['show_share_button'] ?? false,
                    'allow_multiple_devices' => $data['settings']['allow_multiple_devices'] ?? false,
                    'registrants_confirmation_email' => $data['settings']['registrants_confirmation_email'] ?? false,
                    'waiting_room' => $data['settings']['waiting_room'] ?? false,
                    'request_permission_to_unmute_participants' => $data['settings']['request_permission_to_unmute_participants'] ?? false,
                    'global_dial_in_countries' => $data['settings']['global_dial_in_countries'] ?? [],
                    'registrants_email_notification' => $data['settings']['registrants_email_notification'] ?? false,
                ],
            ];
        } catch (\Exception $e) {
            return 'Error creating meeting: '.$e->getMessage();
        }
    }

    /**
     * List Zoom meetings.
     *
     * @param string $type       Meeting type filter (live, upcoming, past)
     * @param int    $pageSize   Number of meetings per page
     * @param int    $pageNumber Page number
     *
     * @return array<int, array{
     *     uuid: string,
     *     id: int,
     *     host_id: string,
     *     topic: string,
     *     type: int,
     *     start_time: string,
     *     duration: int,
     *     timezone: string,
     *     created_at: string,
     *     join_url: string,
     * }>
     */
    public function listMeetings(
        string $type = 'upcoming',
        int $pageSize = 30,
        int $pageNumber = 1,
    ): array {
        try {
            $params = [
                'type' => $type,
                'page_size' => min(max($pageSize, 1), 300),
                'page_number' => max($pageNumber, 1),
            ];

            $response = $this->httpClient->request('GET', "https://api.zoom.us/{$this->apiVersion}/users/me/meetings", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => $params,
            ]);

            $data = $response->toArray();

            if (!isset($data['meetings'])) {
                return [];
            }

            $meetings = [];
            foreach ($data['meetings'] as $meeting) {
                $meetings[] = [
                    'uuid' => $meeting['uuid'],
                    'id' => $meeting['id'],
                    'host_id' => $meeting['host_id'],
                    'topic' => $meeting['topic'],
                    'type' => $meeting['type'],
                    'start_time' => $meeting['start_time'],
                    'duration' => $meeting['duration'],
                    'timezone' => $meeting['timezone'],
                    'created_at' => $meeting['created_at'],
                    'join_url' => $meeting['join_url'],
                ];
            }

            return $meetings;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Zoom meeting details.
     *
     * @param string $meetingId Meeting ID or UUID
     *
     * @return array{
     *     id: int,
     *     topic: string,
     *     type: int,
     *     start_time: string,
     *     duration: int,
     *     timezone: string,
     *     password: string,
     *     join_url: string,
     *     start_url: string,
     *     created_at: string,
     *     host_id: string,
     *     settings: array<string, mixed>,
     *     recurrence: array<string, mixed>|null,
     * }|string
     */
    public function getMeeting(string $meetingId): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.zoom.us/{$this->apiVersion}/meetings/{$meetingId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['code'])) {
                return 'Error getting meeting: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'topic' => $data['topic'],
                'type' => $data['type'],
                'start_time' => $data['start_time'],
                'duration' => $data['duration'],
                'timezone' => $data['timezone'],
                'password' => $data['password'] ?? '',
                'join_url' => $data['join_url'],
                'start_url' => $data['start_url'],
                'created_at' => $data['created_at'],
                'host_id' => $data['host_id'],
                'settings' => $data['settings'] ?? [],
                'recurrence' => $data['recurrence'] ?? null,
            ];
        } catch (\Exception $e) {
            return 'Error getting meeting: '.$e->getMessage();
        }
    }

    /**
     * Update a Zoom meeting.
     *
     * @param string               $meetingId Meeting ID or UUID
     * @param array<string, mixed> $updates   Fields to update
     */
    public function updateMeeting(string $meetingId, array $updates): string
    {
        try {
            $response = $this->httpClient->request('PATCH', "https://api.zoom.us/{$this->apiVersion}/meetings/{$meetingId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $updates,
            ]);

            if (204 === $response->getStatusCode()) {
                return "Meeting {$meetingId} updated successfully";
            } else {
                $data = $response->toArray();

                return 'Error updating meeting: '.($data['message'] ?? 'Unknown error');
            }
        } catch (\Exception $e) {
            return 'Error updating meeting: '.$e->getMessage();
        }
    }

    /**
     * Delete a Zoom meeting.
     *
     * @param string $meetingId             Meeting ID or UUID
     * @param string $scheduleFor           Schedule deletion for (delete, cancel)
     * @param bool   $cancelMeetingReminder Cancel meeting reminder
     */
    public function deleteMeeting(
        string $meetingId,
        string $scheduleFor = 'delete',
        bool $cancelMeetingReminder = false,
    ): string {
        try {
            $params = [
                'schedule_for_reminder' => $cancelMeetingReminder,
            ];

            if ('cancel' === $scheduleFor) {
                $params['cancel_meeting_reminder'] = true;
            }

            $response = $this->httpClient->request('DELETE', "https://api.zoom.us/{$this->apiVersion}/meetings/{$meetingId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => $params,
            ]);

            if (204 === $response->getStatusCode()) {
                return "Meeting {$meetingId} deleted successfully";
            } else {
                $data = $response->toArray();

                return 'Error deleting meeting: '.($data['message'] ?? 'Unknown error');
            }
        } catch (\Exception $e) {
            return 'Error deleting meeting: '.$e->getMessage();
        }
    }

    /**
     * Get Zoom meeting participants.
     *
     * @param string $meetingId     Meeting ID or UUID
     * @param int    $pageSize      Number of participants per page
     * @param string $nextPageToken Next page token for pagination
     *
     * @return array{
     *     page_count: int,
     *     page_size: int,
     *     total_records: int,
     *     next_page_token: string,
     *     participants: array<int, array{
     *         id: string,
     *         user_id: string,
     *         name: string,
     *         user_email: string,
     *         join_time: string,
     *         leave_time: string,
     *         duration: int,
     *         failover: bool,
     *         status: string,
     *         customer_key: string,
     *         registrant_id: string,
     *     }>,
     * }|string
     */
    public function getMeetingParticipants(
        string $meetingId,
        int $pageSize = 30,
        string $nextPageToken = '',
    ): array|string {
        try {
            $params = [
                'page_size' => min(max($pageSize, 1), 300),
            ];

            if ($nextPageToken) {
                $params['next_page_token'] = $nextPageToken;
            }

            $response = $this->httpClient->request('GET', "https://api.zoom.us/{$this->apiVersion}/meetings/{$meetingId}/participants", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => $params,
            ]);

            $data = $response->toArray();

            if (isset($data['code'])) {
                return 'Error getting meeting participants: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'page_count' => $data['page_count'],
                'page_size' => $data['page_size'],
                'total_records' => $data['total_records'],
                'next_page_token' => $data['next_page_token'] ?? '',
                'participants' => array_map(fn ($participant) => [
                    'id' => $participant['id'],
                    'user_id' => $participant['user_id'],
                    'name' => $participant['name'],
                    'user_email' => $participant['user_email'],
                    'join_time' => $participant['join_time'],
                    'leave_time' => $participant['leave_time'] ?? '',
                    'duration' => $participant['duration'],
                    'failover' => $participant['failover'] ?? false,
                    'status' => $participant['status'],
                    'customer_key' => $participant['customer_key'] ?? '',
                    'registrant_id' => $participant['registrant_id'] ?? '',
                ], $data['participants']),
            ];
        } catch (\Exception $e) {
            return 'Error getting meeting participants: '.$e->getMessage();
        }
    }

    /**
     * Create a Zoom user.
     *
     * @param string $email     User email address
     * @param string $firstName User first name
     * @param string $lastName  User last name
     * @param string $type      User type (1=basic, 2=licensed, 3=on-prem)
     * @param string $password  User password (optional)
     *
     * @return array{
     *     id: string,
     *     first_name: string,
     *     last_name: string,
     *     display_name: string,
     *     email: string,
     *     type: int,
     *     role_name: string,
     *     pmi: int,
     *     use_pmi: bool,
     *     personal_meeting_url: string,
     *     timezone: string,
     *     verified: int,
     *     dept: string,
     *     created_at: string,
     *     last_login_time: string,
     *     last_client_version: string,
     *     language: string,
     *     phone_country: string,
     *     phone_number: string,
     *     status: string,
     *     jid: string,
     *     job_title: string,
     *     company: string,
     *     location: string,
     * }|string
     */
    public function createUser(
        string $email,
        string $firstName,
        string $lastName,
        int $type = 1,
        string $password = '',
    ): array|string {
        try {
            $payload = [
                'action' => 'create',
                'user_info' => [
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'type' => $type,
                ],
            ];

            if ($password) {
                $payload['user_info']['password'] = $password;
            }

            $response = $this->httpClient->request('POST', "https://api.zoom.us/{$this->apiVersion}/users", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['code'])) {
                return 'Error creating user: '.($data['message'] ?? 'Unknown error');
            }

            $user = $data['user_info'];

            return [
                'id' => $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'display_name' => $user['display_name'],
                'email' => $user['email'],
                'type' => $user['type'],
                'role_name' => $user['role_name'],
                'pmi' => $user['pmi'],
                'use_pmi' => $user['use_pmi'],
                'personal_meeting_url' => $user['personal_meeting_url'],
                'timezone' => $user['timezone'],
                'verified' => $user['verified'],
                'dept' => $user['dept'] ?? '',
                'created_at' => $user['created_at'],
                'last_login_time' => $user['last_login_time'] ?? '',
                'last_client_version' => $user['last_client_version'] ?? '',
                'language' => $user['language'],
                'phone_country' => $user['phone_country'] ?? '',
                'phone_number' => $user['phone_number'] ?? '',
                'status' => $user['status'],
                'jid' => $user['jid'],
                'job_title' => $user['job_title'] ?? '',
                'company' => $user['company'] ?? '',
                'location' => $user['location'] ?? '',
            ];
        } catch (\Exception $e) {
            return 'Error creating user: '.$e->getMessage();
        }
    }
}

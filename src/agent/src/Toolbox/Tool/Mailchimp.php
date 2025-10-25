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
#[AsTool('mailchimp_create_campaign', 'Tool that creates Mailchimp email campaigns')]
#[AsTool('mailchimp_add_subscriber', 'Tool that adds subscribers to Mailchimp lists', method: 'addSubscriber')]
#[AsTool('mailchimp_get_list_members', 'Tool that gets Mailchimp list members', method: 'getListMembers')]
#[AsTool('mailchimp_create_list', 'Tool that creates Mailchimp lists', method: 'createList')]
#[AsTool('mailchimp_send_campaign', 'Tool that sends Mailchimp campaigns', method: 'sendCampaign')]
#[AsTool('mailchimp_get_campaign_stats', 'Tool that gets Mailchimp campaign statistics', method: 'getCampaignStats')]
final readonly class Mailchimp
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private string $dataCenter,
        private array $options = [],
    ) {
    }

    /**
     * Create a Mailchimp email campaign.
     *
     * @param string $type             Campaign type (regular, plaintext, absplit, rss, variate)
     * @param string $subject          Email subject line
     * @param string $fromName         Sender name
     * @param string $fromEmail        Sender email address
     * @param string $listId           List ID to send to
     * @param string $htmlContent      HTML content of the email
     * @param string $plainTextContent Plain text content of the email
     * @param string $title            Campaign title
     *
     * @return array{
     *     id: string,
     *     type: string,
     *     create_time: string,
     *     archive_url: string,
     *     long_archive_url: string,
     *     status: string,
     *     emails_sent: int,
     *     send_time: string,
     *     content_type: string,
     *     recipient_count: int,
     *     settings: array{
     *         subject_line: string,
     *         title: string,
     *         from_name: string,
     *         reply_to: string,
     *         use_conversation: bool,
     *         to_name: string,
     *         folder_id: string,
     *         authenticate: bool,
     *         auto_footer: bool,
     *         inline_css: bool,
     *         auto_tweet: bool,
     *         auto_fb_post: array<int, string>,
     *         fb_comments: bool,
     *         timewarp: bool,
     *         template_id: int,
     *         drag_and_drop: bool,
     *     },
     *     recipients: array{
     *         list_id: string,
     *         list_is_active: bool,
     *         list_name: string,
     *         segment_text: string,
     *         recipient_count: int,
     *     },
     * }|string
     */
    public function __invoke(
        string $type,
        string $subject,
        string $fromName,
        string $fromEmail,
        string $listId,
        string $htmlContent,
        string $plainTextContent = '',
        string $title = '',
    ): array|string {
        try {
            $payload = [
                'type' => $type,
                'settings' => [
                    'subject_line' => $subject,
                    'from_name' => $fromName,
                    'reply_to' => $fromEmail,
                    'title' => $title ?: $subject,
                ],
                'recipients' => [
                    'list_id' => $listId,
                ],
                'content_type' => 'template',
            ];

            $response = $this->httpClient->request('POST', "https://{$this->dataCenter}.api.mailchimp.com/3.0/campaigns", [
                'headers' => [
                    'Authorization' => 'apikey '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['status']) && $data['status'] >= 400) {
                return 'Error creating campaign: '.($data['detail'] ?? 'Unknown error');
            }

            // Set content after creating the campaign
            if ($htmlContent || $plainTextContent) {
                $contentPayload = [];
                if ($htmlContent) {
                    $contentPayload['html'] = $htmlContent;
                }
                if ($plainTextContent) {
                    $contentPayload['plain_text'] = $plainTextContent;
                }

                $this->httpClient->request('PUT', "https://{$this->dataCenter}.api.mailchimp.com/3.0/campaigns/{$data['id']}/content", [
                    'headers' => [
                        'Authorization' => 'apikey '.$this->apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $contentPayload,
                ]);
            }

            return [
                'id' => $data['id'],
                'type' => $data['type'],
                'create_time' => $data['create_time'],
                'archive_url' => $data['archive_url'],
                'long_archive_url' => $data['long_archive_url'],
                'status' => $data['status'],
                'emails_sent' => $data['emails_sent'] ?? 0,
                'send_time' => $data['send_time'] ?? '',
                'content_type' => $data['content_type'],
                'recipient_count' => $data['recipient_count'] ?? 0,
                'settings' => [
                    'subject_line' => $data['settings']['subject_line'],
                    'title' => $data['settings']['title'],
                    'from_name' => $data['settings']['from_name'],
                    'reply_to' => $data['settings']['reply_to'],
                    'use_conversation' => $data['settings']['use_conversation'] ?? false,
                    'to_name' => $data['settings']['to_name'] ?? '',
                    'folder_id' => $data['settings']['folder_id'] ?? '',
                    'authenticate' => $data['settings']['authenticate'] ?? false,
                    'auto_footer' => $data['settings']['auto_footer'] ?? false,
                    'inline_css' => $data['settings']['inline_css'] ?? false,
                    'auto_tweet' => $data['settings']['auto_tweet'] ?? false,
                    'auto_fb_post' => $data['settings']['auto_fb_post'] ?? [],
                    'fb_comments' => $data['settings']['fb_comments'] ?? false,
                    'timewarp' => $data['settings']['timewarp'] ?? false,
                    'template_id' => $data['settings']['template_id'] ?? 0,
                    'drag_and_drop' => $data['settings']['drag_and_drop'] ?? false,
                ],
                'recipients' => [
                    'list_id' => $data['recipients']['list_id'],
                    'list_is_active' => $data['recipients']['list_is_active'] ?? false,
                    'list_name' => $data['recipients']['list_name'] ?? '',
                    'segment_text' => $data['recipients']['segment_text'] ?? '',
                    'recipient_count' => $data['recipients']['recipient_count'] ?? 0,
                ],
            ];
        } catch (\Exception $e) {
            return 'Error creating campaign: '.$e->getMessage();
        }
    }

    /**
     * Add a subscriber to a Mailchimp list.
     *
     * @param string               $listId      List ID to add subscriber to
     * @param string               $email       Subscriber email address
     * @param string               $firstName   Subscriber first name
     * @param string               $lastName    Subscriber last name
     * @param string               $status      Subscription status (subscribed, unsubscribed, cleaned, pending)
     * @param array<string, mixed> $mergeFields Optional merge fields
     *
     * @return array{
     *     id: string,
     *     email_address: string,
     *     unique_email_id: string,
     *     contact_id: string,
     *     full_name: string,
     *     web_id: int,
     *     email_type: string,
     *     status: string,
     *     unsubscribe_reason: string,
     *     consents_to_one_to_one_messaging: bool,
     *     merge_fields: array<string, mixed>,
     *     interests: array<string, mixed>,
     *     stats: array{
     *         avg_open_rate: float,
     *         avg_click_rate: float,
     *     },
     *     ip_signup: string,
     *     timestamp_signup: string,
     *     ip_opt: string,
     *     timestamp_opt: string,
     *     member_rating: int,
     *     last_changed: string,
     *     language: string,
     *     vip: bool,
     *     email_client: string,
     *     location: array{
     *         latitude: float,
     *         longitude: float,
     *         gmtoff: int,
     *         dstoff: int,
     *         country_code: string,
     *         timezone: string,
     *         region: string,
     *     },
     *     marketing_permissions: array<int, array{
     *         marketing_permission_id: string,
     *         text: string,
     *         enabled: bool,
     *     }>,
     *     last_note: array{
     *         note_id: int,
     *         created_at: string,
     *         created_by: string,
     *         note: string,
     *     },
     *     source: string,
     *     tags_count: int,
     *     tags: array<int, array{id: int, name: string}>,
     *     list_id: string,
     *     _links: array<int, array{rel: string, href: string, method: string}>,
     * }|string
     */
    public function addSubscriber(
        string $listId,
        string $email,
        string $firstName = '',
        string $lastName = '',
        string $status = 'subscribed',
        array $mergeFields = [],
    ): array|string {
        try {
            $payload = [
                'email_address' => $email,
                'status' => $status,
            ];

            if ($firstName || $lastName) {
                $payload['merge_fields'] = array_merge($mergeFields, [
                    'FNAME' => $firstName,
                    'LNAME' => $lastName,
                ]);
            } elseif (!empty($mergeFields)) {
                $payload['merge_fields'] = $mergeFields;
            }

            $response = $this->httpClient->request('POST', "https://{$this->dataCenter}.api.mailchimp.com/3.0/lists/{$listId}/members", [
                'headers' => [
                    'Authorization' => 'apikey '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['status']) && $data['status'] >= 400) {
                return 'Error adding subscriber: '.($data['detail'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'email_address' => $data['email_address'],
                'unique_email_id' => $data['unique_email_id'],
                'contact_id' => $data['contact_id'] ?? '',
                'full_name' => $data['full_name'] ?? '',
                'web_id' => $data['web_id'],
                'email_type' => $data['email_type'] ?? 'html',
                'status' => $data['status'],
                'unsubscribe_reason' => $data['unsubscribe_reason'] ?? '',
                'consents_to_one_to_one_messaging' => $data['consents_to_one_to_one_messaging'] ?? false,
                'merge_fields' => $data['merge_fields'] ?? [],
                'interests' => $data['interests'] ?? [],
                'stats' => [
                    'avg_open_rate' => $data['stats']['avg_open_rate'] ?? 0.0,
                    'avg_click_rate' => $data['stats']['avg_click_rate'] ?? 0.0,
                ],
                'ip_signup' => $data['ip_signup'] ?? '',
                'timestamp_signup' => $data['timestamp_signup'] ?? '',
                'ip_opt' => $data['ip_opt'] ?? '',
                'timestamp_opt' => $data['timestamp_opt'] ?? '',
                'member_rating' => $data['member_rating'] ?? 0,
                'last_changed' => $data['last_changed'],
                'language' => $data['language'] ?? '',
                'vip' => $data['vip'] ?? false,
                'email_client' => $data['email_client'] ?? '',
                'location' => [
                    'latitude' => $data['location']['latitude'] ?? 0.0,
                    'longitude' => $data['location']['longitude'] ?? 0.0,
                    'gmtoff' => $data['location']['gmtoff'] ?? 0,
                    'dstoff' => $data['location']['dstoff'] ?? 0,
                    'country_code' => $data['location']['country_code'] ?? '',
                    'timezone' => $data['location']['timezone'] ?? '',
                    'region' => $data['location']['region'] ?? '',
                ],
                'marketing_permissions' => $data['marketing_permissions'] ?? [],
                'last_note' => [
                    'note_id' => $data['last_note']['note_id'] ?? 0,
                    'created_at' => $data['last_note']['created_at'] ?? '',
                    'created_by' => $data['last_note']['created_by'] ?? '',
                    'note' => $data['last_note']['note'] ?? '',
                ],
                'source' => $data['source'] ?? '',
                'tags_count' => $data['tags_count'] ?? 0,
                'tags' => $data['tags'] ?? [],
                'list_id' => $data['list_id'],
                '_links' => $data['_links'] ?? [],
            ];
        } catch (\Exception $e) {
            return 'Error adding subscriber: '.$e->getMessage();
        }
    }

    /**
     * Get Mailchimp list members.
     *
     * @param string $listId List ID to get members from
     * @param int    $count  Number of members to retrieve
     * @param string $status Filter by status (subscribed, unsubscribed, cleaned, pending, transactional)
     * @param int    $offset Number of members to skip
     *
     * @return array<int, array{
     *     id: string,
     *     email_address: string,
     *     unique_email_id: string,
     *     contact_id: string,
     *     full_name: string,
     *     web_id: int,
     *     email_type: string,
     *     status: string,
     *     merge_fields: array<string, mixed>,
     *     interests: array<string, mixed>,
     *     stats: array{
     *         avg_open_rate: float,
     *         avg_click_rate: float,
     *     },
     *     ip_signup: string,
     *     timestamp_signup: string,
     *     ip_opt: string,
     *     timestamp_opt: string,
     *     member_rating: int,
     *     last_changed: string,
     *     language: string,
     *     vip: bool,
     *     email_client: string,
     * }>
     */
    public function getListMembers(
        string $listId,
        int $count = 10,
        string $status = '',
        int $offset = 0,
    ): array {
        try {
            $params = [
                'count' => min(max($count, 1), 1000),
                'offset' => $offset,
            ];

            if ($status) {
                $params['status'] = $status;
            }

            $response = $this->httpClient->request('GET', "https://{$this->dataCenter}.api.mailchimp.com/3.0/lists/{$listId}/members", [
                'headers' => [
                    'Authorization' => 'apikey '.$this->apiKey,
                ],
                'query' => $params,
            ]);

            $data = $response->toArray();

            if (!isset($data['members'])) {
                return [];
            }

            $members = [];
            foreach ($data['members'] as $member) {
                $members[] = [
                    'id' => $member['id'],
                    'email_address' => $member['email_address'],
                    'unique_email_id' => $member['unique_email_id'],
                    'contact_id' => $member['contact_id'] ?? '',
                    'full_name' => $member['full_name'] ?? '',
                    'web_id' => $member['web_id'],
                    'email_type' => $member['email_type'] ?? 'html',
                    'status' => $member['status'],
                    'merge_fields' => $member['merge_fields'] ?? [],
                    'interests' => $member['interests'] ?? [],
                    'stats' => [
                        'avg_open_rate' => $member['stats']['avg_open_rate'] ?? 0.0,
                        'avg_click_rate' => $member['stats']['avg_click_rate'] ?? 0.0,
                    ],
                    'ip_signup' => $member['ip_signup'] ?? '',
                    'timestamp_signup' => $member['timestamp_signup'] ?? '',
                    'ip_opt' => $member['ip_opt'] ?? '',
                    'timestamp_opt' => $member['timestamp_opt'] ?? '',
                    'member_rating' => $member['member_rating'] ?? 0,
                    'last_changed' => $member['last_changed'],
                    'language' => $member['language'] ?? '',
                    'vip' => $member['vip'] ?? false,
                    'email_client' => $member['email_client'] ?? '',
                ];
            }

            return $members;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a Mailchimp list.
     *
     * @param string $name      List name
     * @param string $company   Company name
     * @param string $address1  Street address
     * @param string $city      City
     * @param string $state     State/province
     * @param string $zip       ZIP/postal code
     * @param string $country   Country code
     * @param string $fromName  From name for campaigns
     * @param string $fromEmail From email for campaigns
     * @param string $subject   Default subject line
     * @param string $language  Language code
     *
     * @return array{
     *     id: string,
     *     web_id: int,
     *     name: string,
     *     contact: array{
     *         company: string,
     *         address1: string,
     *         city: string,
     *         state: string,
     *         zip: string,
     *         country: string,
     *     },
     *     permission_reminder: string,
     *     use_archive_bar: bool,
     *     campaign_defaults: array{
     *         from_name: string,
     *         from_email: string,
     *         subject: string,
     *         language: string,
     *     },
     *     notify_on_subscribe: string,
     *     notify_on_unsubscribe: string,
     *     date_created: string,
     *     list_rating: int,
     *     email_type_option: bool,
     *     subscribe_url_short: string,
     *     subscribe_url_long: string,
     *     beamer_address: string,
     *     visibility: string,
     *     double_optin: bool,
     *     has_welcome: bool,
     *     marketing_permissions: bool,
     *     modules: array<int, string>,
     *     stats: array{
     *         member_count: int,
     *         total_contacts: int,
     *         unsubscribe_count: int,
     *         cleaned_count: int,
     *         member_count_since_send: int,
     *         unsubscribe_count_since_send: int,
     *         cleaned_count_since_send: int,
     *         campaign_count: int,
     *         campaign_last_sent: string,
     *         merge_field_count: int,
     *         avg_sub_rate: float,
     *         avg_unsub_rate: float,
     *         target_sub_rate: float,
     *         open_rate: float,
     *         click_rate: float,
     *         last_sub_date: string,
     *         last_unsub_date: string,
     *     },
     * }|string
     */
    public function createList(
        string $name,
        string $company,
        string $address1,
        string $city,
        string $state,
        string $zip,
        string $country,
        string $fromName,
        string $fromEmail,
        string $subject,
        string $language = 'en',
    ): array|string {
        try {
            $payload = [
                'name' => $name,
                'contact' => [
                    'company' => $company,
                    'address1' => $address1,
                    'city' => $city,
                    'state' => $state,
                    'zip' => $zip,
                    'country' => $country,
                ],
                'permission_reminder' => 'You are receiving this email because you opted in via our website.',
                'campaign_defaults' => [
                    'from_name' => $fromName,
                    'from_email' => $fromEmail,
                    'subject' => $subject,
                    'language' => $language,
                ],
                'email_type_option' => true,
            ];

            $response = $this->httpClient->request('POST', "https://{$this->dataCenter}.api.mailchimp.com/3.0/lists", [
                'headers' => [
                    'Authorization' => 'apikey '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['status']) && $data['status'] >= 400) {
                return 'Error creating list: '.($data['detail'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'web_id' => $data['web_id'],
                'name' => $data['name'],
                'contact' => [
                    'company' => $data['contact']['company'],
                    'address1' => $data['contact']['address1'],
                    'city' => $data['contact']['city'],
                    'state' => $data['contact']['state'],
                    'zip' => $data['contact']['zip'],
                    'country' => $data['contact']['country'],
                ],
                'permission_reminder' => $data['permission_reminder'],
                'use_archive_bar' => $data['use_archive_bar'] ?? false,
                'campaign_defaults' => [
                    'from_name' => $data['campaign_defaults']['from_name'],
                    'from_email' => $data['campaign_defaults']['from_email'],
                    'subject' => $data['campaign_defaults']['subject'],
                    'language' => $data['campaign_defaults']['language'],
                ],
                'notify_on_subscribe' => $data['notify_on_subscribe'] ?? '',
                'notify_on_unsubscribe' => $data['notify_on_unsubscribe'] ?? '',
                'date_created' => $data['date_created'],
                'list_rating' => $data['list_rating'] ?? 0,
                'email_type_option' => $data['email_type_option'],
                'subscribe_url_short' => $data['subscribe_url_short'],
                'subscribe_url_long' => $data['subscribe_url_long'],
                'beamer_address' => $data['beamer_address'] ?? '',
                'visibility' => $data['visibility'] ?? 'pub',
                'double_optin' => $data['double_optin'] ?? false,
                'has_welcome' => $data['has_welcome'] ?? false,
                'marketing_permissions' => $data['marketing_permissions'] ?? false,
                'modules' => $data['modules'] ?? [],
                'stats' => [
                    'member_count' => $data['stats']['member_count'] ?? 0,
                    'total_contacts' => $data['stats']['total_contacts'] ?? 0,
                    'unsubscribe_count' => $data['stats']['unsubscribe_count'] ?? 0,
                    'cleaned_count' => $data['stats']['cleaned_count'] ?? 0,
                    'member_count_since_send' => $data['stats']['member_count_since_send'] ?? 0,
                    'unsubscribe_count_since_send' => $data['stats']['unsubscribe_count_since_send'] ?? 0,
                    'cleaned_count_since_send' => $data['stats']['cleaned_count_since_send'] ?? 0,
                    'campaign_count' => $data['stats']['campaign_count'] ?? 0,
                    'campaign_last_sent' => $data['stats']['campaign_last_sent'] ?? '',
                    'merge_field_count' => $data['stats']['merge_field_count'] ?? 0,
                    'avg_sub_rate' => $data['stats']['avg_sub_rate'] ?? 0.0,
                    'avg_unsub_rate' => $data['stats']['avg_unsub_rate'] ?? 0.0,
                    'target_sub_rate' => $data['stats']['target_sub_rate'] ?? 0.0,
                    'open_rate' => $data['stats']['open_rate'] ?? 0.0,
                    'click_rate' => $data['stats']['click_rate'] ?? 0.0,
                    'last_sub_date' => $data['stats']['last_sub_date'] ?? '',
                    'last_unsub_date' => $data['stats']['last_unsub_date'] ?? '',
                ],
            ];
        } catch (\Exception $e) {
            return 'Error creating list: '.$e->getMessage();
        }
    }

    /**
     * Send a Mailchimp campaign.
     *
     * @param string $campaignId Campaign ID to send
     */
    public function sendCampaign(string $campaignId): string
    {
        try {
            $response = $this->httpClient->request('POST', "https://{$this->dataCenter}.api.mailchimp.com/3.0/campaigns/{$campaignId}/actions/send", [
                'headers' => [
                    'Authorization' => 'apikey '.$this->apiKey,
                ],
            ]);

            if (204 === $response->getStatusCode()) {
                return "Campaign {$campaignId} sent successfully";
            } else {
                $data = $response->toArray();

                return 'Error sending campaign: '.($data['detail'] ?? 'Unknown error');
            }
        } catch (\Exception $e) {
            return 'Error sending campaign: '.$e->getMessage();
        }
    }

    /**
     * Get Mailchimp campaign statistics.
     *
     * @param string $campaignId Campaign ID to get stats for
     *
     * @return array{
     *     opens: array{
     *         opens_total: int,
     *         unique_opens: int,
     *         open_rate: float,
     *         last_opened: string,
     *     },
     *     clicks: array{
     *         clicks_total: int,
     *         unique_clicks: int,
     *         unique_subscriber_clicks: int,
     *         click_rate: float,
     *         last_clicked: string,
     *     },
     *     facebook_likes: array{
     *         recipient_likes: int,
     *         unique_likes: int,
     *         facebook_likes: int,
     *     },
     *     industry_stats: array{
     *         type: string,
     *         open_rate: float,
     *         click_rate: float,
     *         bounce_rate: float,
     *         unopen_rate: float,
     *         unsub_rate: float,
     *         abuse_rate: float,
     *     },
     *     list_stats: array{
     *         sub_rate: float,
     *         unsub_rate: float,
     *         open_rate: float,
     *         click_rate: float,
     *     },
     *     abuse_reports: int,
     *     unsubscribed: int,
     *     delivered_count: int,
     * }|string
     */
    public function getCampaignStats(string $campaignId): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://{$this->dataCenter}.api.mailchimp.com/3.0/campaigns/{$campaignId}/reports", [
                'headers' => [
                    'Authorization' => 'apikey '.$this->apiKey,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['status']) && $data['status'] >= 400) {
                return 'Error getting campaign stats: '.($data['detail'] ?? 'Unknown error');
            }

            return [
                'opens' => [
                    'opens_total' => $data['opens']['opens_total'] ?? 0,
                    'unique_opens' => $data['opens']['unique_opens'] ?? 0,
                    'open_rate' => $data['opens']['open_rate'] ?? 0.0,
                    'last_opened' => $data['opens']['last_opened'] ?? '',
                ],
                'clicks' => [
                    'clicks_total' => $data['clicks']['clicks_total'] ?? 0,
                    'unique_clicks' => $data['clicks']['unique_clicks'] ?? 0,
                    'unique_subscriber_clicks' => $data['clicks']['unique_subscriber_clicks'] ?? 0,
                    'click_rate' => $data['clicks']['click_rate'] ?? 0.0,
                    'last_clicked' => $data['clicks']['last_clicked'] ?? '',
                ],
                'facebook_likes' => [
                    'recipient_likes' => $data['facebook_likes']['recipient_likes'] ?? 0,
                    'unique_likes' => $data['facebook_likes']['unique_likes'] ?? 0,
                    'facebook_likes' => $data['facebook_likes']['facebook_likes'] ?? 0,
                ],
                'industry_stats' => [
                    'type' => $data['industry_stats']['type'] ?? '',
                    'open_rate' => $data['industry_stats']['open_rate'] ?? 0.0,
                    'click_rate' => $data['industry_stats']['click_rate'] ?? 0.0,
                    'bounce_rate' => $data['industry_stats']['bounce_rate'] ?? 0.0,
                    'unopen_rate' => $data['industry_stats']['unopen_rate'] ?? 0.0,
                    'unsub_rate' => $data['industry_stats']['unsub_rate'] ?? 0.0,
                    'abuse_rate' => $data['industry_stats']['abuse_rate'] ?? 0.0,
                ],
                'list_stats' => [
                    'sub_rate' => $data['list_stats']['sub_rate'] ?? 0.0,
                    'unsub_rate' => $data['list_stats']['unsub_rate'] ?? 0.0,
                    'open_rate' => $data['list_stats']['open_rate'] ?? 0.0,
                    'click_rate' => $data['list_stats']['click_rate'] ?? 0.0,
                ],
                'abuse_reports' => $data['abuse_reports'] ?? 0,
                'unsubscribed' => $data['unsubscribed'] ?? 0,
                'delivered_count' => $data['delivered_count'] ?? 0,
            ];
        } catch (\Exception $e) {
            return 'Error getting campaign stats: '.$e->getMessage();
        }
    }
}

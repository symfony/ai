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
#[AsTool('twilio_send_sms', 'Tool that sends SMS messages via Twilio')]
#[AsTool('twilio_send_whatsapp', 'Tool that sends WhatsApp messages via Twilio', method: 'sendWhatsApp')]
#[AsTool('twilio_make_call', 'Tool that makes voice calls via Twilio', method: 'makeCall')]
#[AsTool('twilio_get_messages', 'Tool that retrieves SMS messages from Twilio', method: 'getMessages')]
#[AsTool('twilio_get_phone_numbers', 'Tool that lists Twilio phone numbers', method: 'getPhoneNumbers')]
#[AsTool('twilio_send_verification', 'Tool that sends verification codes via Twilio', method: 'sendVerification')]
final readonly class TwilioSms
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accountSid,
        #[\SensitiveParameter] private string $authToken,
        private string $fromNumber = '',
        private array $options = [],
    ) {
    }

    /**
     * Send SMS message via Twilio.
     *
     * @param string $to             Recipient phone number (with country code, no +)
     * @param string $message        SMS message content
     * @param string $from           Sender phone number (optional, uses default if not provided)
     * @param string $statusCallback Optional status callback URL
     *
     * @return array{
     *     sid: string,
     *     account_sid: string,
     *     to: string,
     *     from: string,
     *     body: string,
     *     status: string,
     *     date_created: string,
     *     date_sent: string|null,
     *     date_updated: string,
     *     direction: string,
     *     price: string,
     *     price_unit: string,
     *     uri: string,
     * }|string
     */
    public function __invoke(
        string $to,
        #[With(maximum: 1600)]
        string $message,
        string $from = '',
        string $statusCallback = '',
    ): array|string {
        try {
            $fromNumber = $from ?: $this->fromNumber;

            if (!$fromNumber) {
                return 'Error: No sender phone number provided';
            }

            $payload = [
                'To' => $to,
                'From' => $fromNumber,
                'Body' => $message,
            ];

            if ($statusCallback) {
                $payload['StatusCallback'] = $statusCallback;
            }

            $response = $this->httpClient->request('POST', "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
                'auth_basic' => [$this->accountSid, $this->authToken],
                'body' => http_build_query($payload),
            ]);

            $data = $response->toArray();

            if (isset($data['error_code'])) {
                return 'Error sending SMS: '.($data['error_message'] ?? 'Unknown error');
            }

            return [
                'sid' => $data['sid'],
                'account_sid' => $data['account_sid'],
                'to' => $data['to'],
                'from' => $data['from'],
                'body' => $data['body'],
                'status' => $data['status'],
                'date_created' => $data['date_created'],
                'date_sent' => $data['date_sent'] ?? null,
                'date_updated' => $data['date_updated'],
                'direction' => $data['direction'],
                'price' => $data['price'],
                'price_unit' => $data['price_unit'],
                'uri' => $data['uri'],
            ];
        } catch (\Exception $e) {
            return 'Error sending SMS: '.$e->getMessage();
        }
    }

    /**
     * Send WhatsApp message via Twilio.
     *
     * @param string $to      Recipient WhatsApp number (with country code, no +)
     * @param string $message WhatsApp message content
     * @param string $from    Sender WhatsApp number (optional, uses default if not provided)
     *
     * @return array{
     *     sid: string,
     *     account_sid: string,
     *     to: string,
     *     from: string,
     *     body: string,
     *     status: string,
     *     date_created: string,
     *     date_sent: string|null,
     *     date_updated: string,
     *     direction: string,
     *     price: string,
     *     price_unit: string,
     * }|string
     */
    public function sendWhatsApp(
        string $to,
        #[With(maximum: 4096)]
        string $message,
        string $from = '',
    ): array|string {
        try {
            $fromNumber = $from ?: $this->fromNumber;

            if (!$fromNumber) {
                return 'Error: No sender WhatsApp number provided';
            }

            // Format WhatsApp numbers
            $toWhatsApp = 'whatsapp:+'.ltrim($to, '+');
            $fromWhatsApp = 'whatsapp:+'.ltrim($fromNumber, '+');

            $payload = [
                'To' => $toWhatsApp,
                'From' => $fromWhatsApp,
                'Body' => $message,
            ];

            $response = $this->httpClient->request('POST', "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
                'auth_basic' => [$this->accountSid, $this->authToken],
                'body' => http_build_query($payload),
            ]);

            $data = $response->toArray();

            if (isset($data['error_code'])) {
                return 'Error sending WhatsApp message: '.($data['error_message'] ?? 'Unknown error');
            }

            return [
                'sid' => $data['sid'],
                'account_sid' => $data['account_sid'],
                'to' => $data['to'],
                'from' => $data['from'],
                'body' => $data['body'],
                'status' => $data['status'],
                'date_created' => $data['date_created'],
                'date_sent' => $data['date_sent'] ?? null,
                'date_updated' => $data['date_updated'],
                'direction' => $data['direction'],
                'price' => $data['price'],
                'price_unit' => $data['price_unit'],
            ];
        } catch (\Exception $e) {
            return 'Error sending WhatsApp message: '.$e->getMessage();
        }
    }

    /**
     * Make a voice call via Twilio.
     *
     * @param string $to       Recipient phone number
     * @param string $twimlUrl TwiML URL for call instructions
     * @param string $from     Sender phone number (optional, uses default if not provided)
     * @param string $record   Whether to record the call (do-not-record, record-from-ringing, record-from-answer)
     *
     * @return array{
     *     sid: string,
     *     account_sid: string,
     *     to: string,
     *     from: string,
     *     status: string,
     *     start_time: string|null,
     *     end_time: string|null,
     *     duration: string|null,
     *     price: string,
     *     price_unit: string,
     *     direction: string,
     *     answered_by: string|null,
     *     uri: string,
     * }|string
     */
    public function makeCall(
        string $to,
        string $twimlUrl,
        string $from = '',
        string $record = 'do-not-record',
    ): array|string {
        try {
            $fromNumber = $from ?: $this->fromNumber;

            if (!$fromNumber) {
                return 'Error: No sender phone number provided';
            }

            $payload = [
                'To' => $to,
                'From' => $fromNumber,
                'Url' => $twimlUrl,
                'Record' => $record,
            ];

            $response = $this->httpClient->request('POST', "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Calls.json", [
                'auth_basic' => [$this->accountSid, $this->authToken],
                'body' => http_build_query($payload),
            ]);

            $data = $response->toArray();

            if (isset($data['error_code'])) {
                return 'Error making call: '.($data['error_message'] ?? 'Unknown error');
            }

            return [
                'sid' => $data['sid'],
                'account_sid' => $data['account_sid'],
                'to' => $data['to'],
                'from' => $data['from'],
                'status' => $data['status'],
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'duration' => $data['duration'] ?? null,
                'price' => $data['price'],
                'price_unit' => $data['price_unit'],
                'direction' => $data['direction'],
                'answered_by' => $data['answered_by'] ?? null,
                'uri' => $data['uri'],
            ];
        } catch (\Exception $e) {
            return 'Error making call: '.$e->getMessage();
        }
    }

    /**
     * Get SMS messages from Twilio.
     *
     * @param int    $limit  Maximum number of messages to retrieve
     * @param string $to     Filter by recipient number
     * @param string $from   Filter by sender number
     * @param string $status Filter by message status
     *
     * @return array<int, array{
     *     sid: string,
     *     account_sid: string,
     *     to: string,
     *     from: string,
     *     body: string,
     *     status: string,
     *     date_created: string,
     *     date_sent: string|null,
     *     date_updated: string,
     *     direction: string,
     *     price: string,
     *     price_unit: string,
     * }>
     */
    public function getMessages(
        int $limit = 20,
        string $to = '',
        string $from = '',
        string $status = '',
    ): array {
        try {
            $params = [
                'PageSize' => min(max($limit, 1), 1000),
            ];

            if ($to) {
                $params['To'] = $to;
            }
            if ($from) {
                $params['From'] = $from;
            }
            if ($status) {
                $params['MessageStatus'] = $status;
            }

            $response = $this->httpClient->request('GET', "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
                'auth_basic' => [$this->accountSid, $this->authToken],
                'query' => $params,
            ]);

            $data = $response->toArray();

            if (!isset($data['messages'])) {
                return [];
            }

            $messages = [];
            foreach ($data['messages'] as $message) {
                $messages[] = [
                    'sid' => $message['sid'],
                    'account_sid' => $message['account_sid'],
                    'to' => $message['to'],
                    'from' => $message['from'],
                    'body' => $message['body'],
                    'status' => $message['status'],
                    'date_created' => $message['date_created'],
                    'date_sent' => $message['date_sent'] ?? null,
                    'date_updated' => $message['date_updated'],
                    'direction' => $message['direction'],
                    'price' => $message['price'],
                    'price_unit' => $message['price_unit'],
                ];
            }

            return $messages;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Twilio phone numbers.
     *
     * @param int $limit Maximum number of phone numbers to retrieve
     *
     * @return array<int, array{
     *     sid: string,
     *     account_sid: string,
     *     friendly_name: string,
     *     phone_number: string,
     *     voice_url: string|null,
     *     voice_method: string,
     *     voice_fallback_url: string|null,
     *     voice_fallback_method: string,
     *     sms_url: string|null,
     *     sms_method: string,
     *     sms_fallback_url: string|null,
     *     sms_fallback_method: string,
     *     status_callback: string|null,
     *     status_callback_method: string,
     *     capabilities: array{voice: bool, sms: bool, mms: bool, fax: bool},
     *     date_created: string,
     *     date_updated: string,
     * }>
     */
    public function getPhoneNumbers(int $limit = 20): array
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/IncomingPhoneNumbers.json", [
                'auth_basic' => [$this->accountSid, $this->authToken],
                'query' => [
                    'PageSize' => min(max($limit, 1), 1000),
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['incoming_phone_numbers'])) {
                return [];
            }

            $phoneNumbers = [];
            foreach ($data['incoming_phone_numbers'] as $number) {
                $phoneNumbers[] = [
                    'sid' => $number['sid'],
                    'account_sid' => $number['account_sid'],
                    'friendly_name' => $number['friendly_name'],
                    'phone_number' => $number['phone_number'],
                    'voice_url' => $number['voice_url'] ?? null,
                    'voice_method' => $number['voice_method'],
                    'voice_fallback_url' => $number['voice_fallback_url'] ?? null,
                    'voice_fallback_method' => $number['voice_fallback_method'],
                    'sms_url' => $number['sms_url'] ?? null,
                    'sms_method' => $number['sms_method'],
                    'sms_fallback_url' => $number['sms_fallback_url'] ?? null,
                    'sms_fallback_method' => $number['sms_fallback_method'],
                    'status_callback' => $number['status_callback'] ?? null,
                    'status_callback_method' => $number['status_callback_method'],
                    'capabilities' => [
                        'voice' => $number['capabilities']['voice'],
                        'sms' => $number['capabilities']['sms'],
                        'mms' => $number['capabilities']['mms'],
                        'fax' => $number['capabilities']['fax'],
                    ],
                    'date_created' => $number['date_created'],
                    'date_updated' => $number['date_updated'],
                ];
            }

            return $phoneNumbers;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Send verification code via Twilio.
     *
     * @param string $to      Recipient phone number
     * @param string $channel Verification channel (sms, call, email, whatsapp)
     * @param string $locale  Language locale (en, es, fr, etc.)
     *
     * @return array{
     *     sid: string,
     *     service_sid: string,
     *     account_sid: string,
     *     to: string,
     *     channel: string,
     *     status: string,
     *     valid: bool,
     *     date_created: string,
     *     date_updated: string,
     *     lookup: array{carrier: array{error_code: string|null, name: string, mobile_country_code: string, mobile_network_code: string, type: string}},
     *     amount: string|null,
     *     payee: string,
     *     send_code_attempts: array<int, array{attempt_sid: string, channel: string, time: string}>,
     * }|string
     */
    public function sendVerification(
        string $to,
        string $channel = 'sms',
        string $locale = 'en',
    ): array|string {
        try {
            $payload = [
                'To' => $to,
                'Channel' => $channel,
                'Locale' => $locale,
            ];

            $response = $this->httpClient->request('POST', "https://verify.twilio.com/v2/Services/{$this->getVerifyServiceSid()}/Verifications", [
                'auth_basic' => [$this->accountSid, $this->authToken],
                'body' => http_build_query($payload),
            ]);

            $data = $response->toArray();

            if (isset($data['error_code'])) {
                return 'Error sending verification: '.($data['error_message'] ?? 'Unknown error');
            }

            return [
                'sid' => $data['sid'],
                'service_sid' => $data['service_sid'],
                'account_sid' => $data['account_sid'],
                'to' => $data['to'],
                'channel' => $data['channel'],
                'status' => $data['status'],
                'valid' => $data['valid'],
                'date_created' => $data['date_created'],
                'date_updated' => $data['date_updated'],
                'lookup' => [
                    'carrier' => [
                        'error_code' => $data['lookup']['carrier']['error_code'] ?? null,
                        'name' => $data['lookup']['carrier']['name'],
                        'mobile_country_code' => $data['lookup']['carrier']['mobile_country_code'],
                        'mobile_network_code' => $data['lookup']['carrier']['mobile_network_code'],
                        'type' => $data['lookup']['carrier']['type'],
                    ],
                ],
                'amount' => $data['amount'] ?? null,
                'payee' => $data['payee'],
                'send_code_attempts' => $data['send_code_attempts'] ?? [],
            ];
        } catch (\Exception $e) {
            return 'Error sending verification: '.$e->getMessage();
        }
    }

    /**
     * Get Twilio Verify service SID (placeholder - in real implementation, you'd configure this).
     */
    private function getVerifyServiceSid(): string
    {
        // In a real implementation, you would store this as a configuration value
        // or retrieve it from your application settings
        return 'VA'.str_repeat('X', 32); // Placeholder format
    }
}

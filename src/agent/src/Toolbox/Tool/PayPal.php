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
#[AsTool('paypal_create_order', 'Tool that creates PayPal orders')]
#[AsTool('paypal_capture_order', 'Tool that captures PayPal orders', method: 'captureOrder')]
#[AsTool('paypal_create_payment', 'Tool that creates PayPal payments', method: 'createPayment')]
#[AsTool('paypal_execute_payment', 'Tool that executes PayPal payments', method: 'executePayment')]
#[AsTool('paypal_refund_payment', 'Tool that refunds PayPal payments', method: 'refundPayment')]
#[AsTool('paypal_get_payment_details', 'Tool that gets PayPal payment details', method: 'getPaymentDetails')]
final readonly class PayPal
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $clientId,
        #[\SensitiveParameter] private string $clientSecret,
        private string $environment = 'sandbox',
        private array $options = [],
    ) {
    }

    /**
     * Create a PayPal order.
     *
     * @param string $intent Payment intent (CAPTURE, AUTHORIZE)
     * @param array<int, array{
     *     name: string,
     *     description: string,
     *     quantity: int,
     *     unit_amount: array{currency_code: string, value: string},
     *     category: string,
     * }> $items Order items
     * @param array{
     *     currency_code: string,
     *     value: string,
     *     breakdown: array{
     *         item_total: array{currency_code: string, value: string},
     *         tax_total: array{currency_code: string, value: string}|null,
     *         shipping: array{currency_code: string, value: string}|null,
     *         handling: array{currency_code: string, value: string}|null,
     *         shipping_discount: array{currency_code: string, value: string}|null,
     *         discount: array{currency_code: string, value: string}|null,
     *     },
     * } $amount Order amount
     * @param string $currencyCode Currency code (e.g., 'USD', 'EUR')
     * @param string $description  Order description
     *
     * @return array{
     *     id: string,
     *     status: string,
     *     links: array<int, array{
     *         href: string,
     *         rel: string,
     *         method: string,
     *     }>,
     *     create_time: string,
     *     update_time: string,
     * }|string
     */
    public function __invoke(
        string $intent,
        array $items,
        array $amount,
        string $currencyCode = 'USD',
        string $description = '',
    ): array|string {
        try {
            $accessToken = $this->getAccessToken();
            if (\is_string($accessToken)) {
                return $accessToken; // Error getting access token
            }

            $baseUrl = 'sandbox' === $this->environment
                ? 'https://api.sandbox.paypal.com'
                : 'https://api.paypal.com';

            $payload = [
                'intent' => $intent,
                'purchase_units' => [
                    [
                        'amount' => $amount,
                        'description' => $description,
                        'items' => $items,
                    ],
                ],
                'application_context' => [
                    'return_url' => $this->options['return_url'] ?? 'https://example.com/return',
                    'cancel_url' => $this->options['cancel_url'] ?? 'https://example.com/cancel',
                ],
            ];

            $response = $this->httpClient->request('POST', "{$baseUrl}/v2/checkout/orders", [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type' => 'application/json',
                    'PayPal-Request-Id' => uniqid('pp-'),
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating order: '.($data['error_description'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'status' => $data['status'],
                'links' => $data['links'],
                'create_time' => $data['create_time'],
                'update_time' => $data['update_time'],
            ];
        } catch (\Exception $e) {
            return 'Error creating order: '.$e->getMessage();
        }
    }

    /**
     * Capture a PayPal order.
     *
     * @param string $orderId PayPal order ID
     *
     * @return array{
     *     id: string,
     *     status: string,
     *     purchase_units: array<int, array{
     *         reference_id: string,
     *         amount: array{currency_code: string, value: string},
     *         payee: array{email_address: string, merchant_id: string},
     *         payments: array{
     *             captures: array<int, array{
     *                 id: string,
     *                 status: string,
     *                 amount: array{currency_code: string, value: string},
     *                 final_capture: bool,
     *                 seller_protection: array{status: string, dispute_categories: array<int, string>},
     *                 seller_receivable_breakdown: array{
     *                     gross_amount: array{currency_code: string, value: string},
     *                     paypal_fee: array{currency_code: string, value: string},
     *                     net_amount: array{currency_code: string, value: string},
     *                 },
     *                 create_time: string,
     *                 update_time: string,
     *             }>,
     *         },
     *     }>,
     *     payer: array{
     *         name: array{given_name: string, surname: string},
     *         email_address: string,
     *         payer_id: string,
     *     },
     *     links: array<int, array{href: string, rel: string, method: string}>,
     * }|string
     */
    public function captureOrder(string $orderId): array|string
    {
        try {
            $accessToken = $this->getAccessToken();
            if (\is_string($accessToken)) {
                return $accessToken; // Error getting access token
            }

            $baseUrl = 'sandbox' === $this->environment
                ? 'https://api.sandbox.paypal.com'
                : 'https://api.paypal.com';

            $response = $this->httpClient->request('POST', "{$baseUrl}/v2/checkout/orders/{$orderId}/capture", [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type' => 'application/json',
                    'PayPal-Request-Id' => uniqid('pp-'),
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error capturing order: '.($data['error_description'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'status' => $data['status'],
                'purchase_units' => $data['purchase_units'],
                'payer' => $data['payer'] ?? [],
                'links' => $data['links'] ?? [],
            ];
        } catch (\Exception $e) {
            return 'Error capturing order: '.$e->getMessage();
        }
    }

    /**
     * Create a PayPal payment (Legacy API).
     *
     * @param string $intent       Payment intent (sale, authorize, order)
     * @param string $currencyCode Currency code
     * @param string $total        Total amount
     * @param string $description  Payment description
     * @param string $returnUrl    Return URL
     * @param string $cancelUrl    Cancel URL
     *
     * @return array{
     *     id: string,
     *     state: string,
     *     intent: string,
     *     payer: array{
     *         payment_method: string,
     *         payer_info: array{
     *             shipping_address: array{
     *                 line1: string,
     *                 city: string,
     *                 state: string,
     *                 postal_code: string,
     *                 country_code: string,
     *             },
     *         },
     *     },
     *     transactions: array<int, array{
     *         amount: array{total: string, currency: string, details: array<string, string>},
     *         description: string,
     *         item_list: array{
     *             items: array<int, array{
     *                 name: string,
     *                 sku: string,
     *                 price: string,
     *                 currency: string,
     *                 quantity: int,
     *             }>,
     *         },
     *     }>,
     *     links: array<int, array{href: string, rel: string, method: string}>,
     *     create_time: string,
     *     update_time: string,
     * }|string
     */
    public function createPayment(
        string $intent,
        string $currencyCode,
        string $total,
        string $description,
        string $returnUrl,
        string $cancelUrl,
    ): array|string {
        try {
            $accessToken = $this->getAccessToken();
            if (\is_string($accessToken)) {
                return $accessToken; // Error getting access token
            }

            $baseUrl = 'sandbox' === $this->environment
                ? 'https://api.sandbox.paypal.com'
                : 'https://api.paypal.com';

            $payload = [
                'intent' => $intent,
                'redirect_urls' => [
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ],
                'payer' => [
                    'payment_method' => 'paypal',
                ],
                'transactions' => [
                    [
                        'amount' => [
                            'total' => $total,
                            'currency' => $currencyCode,
                        ],
                        'description' => $description,
                    ],
                ],
            ];

            $response = $this->httpClient->request('POST', "{$baseUrl}/v1/payments/payment", [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating payment: '.($data['error_description'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'state' => $data['state'],
                'intent' => $data['intent'],
                'payer' => $data['payer'],
                'transactions' => $data['transactions'],
                'links' => $data['links'],
                'create_time' => $data['create_time'],
                'update_time' => $data['update_time'],
            ];
        } catch (\Exception $e) {
            return 'Error creating payment: '.$e->getMessage();
        }
    }

    /**
     * Execute a PayPal payment.
     *
     * @param string $paymentId PayPal payment ID
     * @param string $payerId   Payer ID from approval
     *
     * @return array{
     *     id: string,
     *     state: string,
     *     intent: string,
     *     payer: array{
     *         payment_method: string,
     *         status: string,
     *         payer_info: array{
     *             email: string,
     *             first_name: string,
     *             last_name: string,
     *             payer_id: string,
     *             shipping_address: array{
     *                 line1: string,
     *                 city: string,
     *                 state: string,
     *                 postal_code: string,
     *                 country_code: string,
     *             },
     *         },
     *     },
     *     transactions: array<int, array{
     *         amount: array{total: string, currency: string, details: array<string, string>},
     *         related_resources: array<int, array{
     *             sale: array{
     *                 id: string,
     *                 state: string,
     *                 amount: array{total: string, currency: string},
     *                 payment_mode: string,
     *                 protection_eligibility: string,
     *                 protection_eligibility_type: string,
     *                 transaction_fee: array{value: string, currency: string},
     *                 parent_payment: string,
     *                 create_time: string,
     *                 update_time: string,
     *                 links: array<int, array{href: string, rel: string, method: string}>,
     *             },
     *         }>,
     *     }>,
     *     create_time: string,
     *     update_time: string,
     *     links: array<int, array{href: string, rel: string, method: string}>,
     * }|string
     */
    public function executePayment(string $paymentId, string $payerId): array|string
    {
        try {
            $accessToken = $this->getAccessToken();
            if (\is_string($accessToken)) {
                return $accessToken; // Error getting access token
            }

            $baseUrl = 'sandbox' === $this->environment
                ? 'https://api.sandbox.paypal.com'
                : 'https://api.paypal.com';

            $payload = [
                'payer_id' => $payerId,
            ];

            $response = $this->httpClient->request('POST', "{$baseUrl}/v1/payments/payment/{$paymentId}/execute", [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error executing payment: '.($data['error_description'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'state' => $data['state'],
                'intent' => $data['intent'],
                'payer' => $data['payer'],
                'transactions' => $data['transactions'],
                'create_time' => $data['create_time'],
                'update_time' => $data['update_time'],
                'links' => $data['links'],
            ];
        } catch (\Exception $e) {
            return 'Error executing payment: '.$e->getMessage();
        }
    }

    /**
     * Refund a PayPal payment.
     *
     * @param string $captureId    Capture ID to refund
     * @param string $amount       Refund amount
     * @param string $currencyCode Currency code
     * @param string $reason       Refund reason
     *
     * @return array{
     *     id: string,
     *     amount: array{total: string, currency: string},
     *     state: string,
     *     reason_code: string,
     *     parent_payment: string,
     *     create_time: string,
     *     update_time: string,
     *     links: array<int, array{href: string, rel: string, method: string}>,
     * }|string
     */
    public function refundPayment(
        string $captureId,
        string $amount,
        string $currencyCode,
        string $reason = '',
    ): array|string {
        try {
            $accessToken = $this->getAccessToken();
            if (\is_string($accessToken)) {
                return $accessToken; // Error getting access token
            }

            $baseUrl = 'sandbox' === $this->environment
                ? 'https://api.sandbox.paypal.com'
                : 'https://api.paypal.com';

            $payload = [
                'amount' => [
                    'total' => $amount,
                    'currency' => $currencyCode,
                ],
            ];

            if ($reason) {
                $payload['reason'] = $reason;
            }

            $response = $this->httpClient->request('POST', "{$baseUrl}/v1/payments/capture/{$captureId}/refund", [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error refunding payment: '.($data['error_description'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'amount' => $data['amount'],
                'state' => $data['state'],
                'reason_code' => $data['reason_code'] ?? '',
                'parent_payment' => $data['parent_payment'],
                'create_time' => $data['create_time'],
                'update_time' => $data['update_time'],
                'links' => $data['links'],
            ];
        } catch (\Exception $e) {
            return 'Error refunding payment: '.$e->getMessage();
        }
    }

    /**
     * Get PayPal payment details.
     *
     * @param string $paymentId PayPal payment ID
     *
     * @return array{
     *     id: string,
     *     state: string,
     *     intent: string,
     *     payer: array{
     *         payment_method: string,
     *         status: string,
     *         payer_info: array{
     *             email: string,
     *             first_name: string,
     *             last_name: string,
     *             payer_id: string,
     *         },
     *     },
     *     transactions: array<int, array{
     *         amount: array{total: string, currency: string, details: array<string, string>},
     *         description: string,
     *         related_resources: array<int, array<string, mixed>>,
     *     }>,
     *     create_time: string,
     *     update_time: string,
     *     links: array<int, array{href: string, rel: string, method: string}>,
     * }|string
     */
    public function getPaymentDetails(string $paymentId): array|string
    {
        try {
            $accessToken = $this->getAccessToken();
            if (\is_string($accessToken)) {
                return $accessToken; // Error getting access token
            }

            $baseUrl = 'sandbox' === $this->environment
                ? 'https://api.sandbox.paypal.com'
                : 'https://api.paypal.com';

            $response = $this->httpClient->request('GET', "{$baseUrl}/v1/payments/payment/{$paymentId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting payment details: '.($data['error_description'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'state' => $data['state'],
                'intent' => $data['intent'],
                'payer' => $data['payer'],
                'transactions' => $data['transactions'],
                'create_time' => $data['create_time'],
                'update_time' => $data['update_time'],
                'links' => $data['links'],
            ];
        } catch (\Exception $e) {
            return 'Error getting payment details: '.$e->getMessage();
        }
    }

    /**
     * Get PayPal access token.
     *
     * @return string|array{access_token: string, token_type: string, expires_in: int}
     */
    private function getAccessToken(): string|array
    {
        try {
            $baseUrl = 'sandbox' === $this->environment
                ? 'https://api.sandbox.paypal.com'
                : 'https://api.paypal.com';

            $response = $this->httpClient->request('POST', "{$baseUrl}/v1/oauth2/token", [
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en_US',
                ],
                'auth_basic' => [$this->clientId, $this->clientSecret],
                'body' => 'grant_type=client_credentials',
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting access token: '.($data['error_description'] ?? 'Unknown error');
            }

            return $data;
        } catch (\Exception $e) {
            return 'Error getting access token: '.$e->getMessage();
        }
    }
}

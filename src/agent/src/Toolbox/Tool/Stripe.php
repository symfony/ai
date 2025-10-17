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
#[AsTool('stripe_create_payment_intent', 'Tool that creates Stripe payment intents')]
#[AsTool('stripe_create_customer', 'Tool that creates Stripe customers', method: 'createCustomer')]
#[AsTool('stripe_create_subscription', 'Tool that creates Stripe subscriptions', method: 'createSubscription')]
#[AsTool('stripe_list_payments', 'Tool that lists Stripe payments', method: 'listPayments')]
#[AsTool('stripe_refund_payment', 'Tool that refunds Stripe payments', method: 'refundPayment')]
#[AsTool('stripe_create_product', 'Tool that creates Stripe products', method: 'createProduct')]
#[AsTool('stripe_create_price', 'Tool that creates Stripe prices', method: 'createPrice')]
final readonly class Stripe
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $secretKey,
        private array $options = [],
    ) {
    }

    /**
     * Create a Stripe payment intent.
     *
     * @param int                  $amount      Amount in cents
     * @param string               $currency    Currency code (e.g., 'usd', 'eur')
     * @param string               $customerId  Optional customer ID
     * @param array<string, mixed> $metadata    Optional metadata
     * @param string               $description Optional description
     *
     * @return array{
     *     id: string,
     *     amount: int,
     *     currency: string,
     *     status: string,
     *     client_secret: string,
     *     customer: string|null,
     *     description: string|null,
     *     metadata: array<string, mixed>,
     *     created: int,
     * }|string
     */
    public function __invoke(
        int $amount,
        string $currency = 'usd',
        string $customerId = '',
        array $metadata = [],
        string $description = '',
    ): array|string {
        try {
            $payload = [
                'amount' => $amount,
                'currency' => strtolower($currency),
            ];

            if ($customerId) {
                $payload['customer'] = $customerId;
            }

            if (!empty($metadata)) {
                $payload['metadata'] = $metadata;
            }

            if ($description) {
                $payload['description'] = $description;
            }

            $response = $this->httpClient->request('POST', 'https://api.stripe.com/v1/payment_intents', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->secretKey,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query($payload),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating payment intent: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'status' => $data['status'],
                'client_secret' => $data['client_secret'],
                'customer' => $data['customer'] ?? null,
                'description' => $data['description'] ?? null,
                'metadata' => $data['metadata'] ?? [],
                'created' => $data['created'],
            ];
        } catch (\Exception $e) {
            return 'Error creating payment intent: '.$e->getMessage();
        }
    }

    /**
     * Create a Stripe customer.
     *
     * @param string               $email       Customer email
     * @param string               $name        Customer name
     * @param string               $description Optional description
     * @param array<string, mixed> $metadata    Optional metadata
     *
     * @return array{
     *     id: string,
     *     email: string,
     *     name: string|null,
     *     description: string|null,
     *     metadata: array<string, mixed>,
     *     created: int,
     *     livemode: bool,
     * }|string
     */
    public function createCustomer(
        string $email,
        string $name = '',
        string $description = '',
        array $metadata = [],
    ): array|string {
        try {
            $payload = [
                'email' => $email,
            ];

            if ($name) {
                $payload['name'] = $name;
            }

            if ($description) {
                $payload['description'] = $description;
            }

            if (!empty($metadata)) {
                $payload['metadata'] = $metadata;
            }

            $response = $this->httpClient->request('POST', 'https://api.stripe.com/v1/customers', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->secretKey,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query($payload),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating customer: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'email' => $data['email'],
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'metadata' => $data['metadata'] ?? [],
                'created' => $data['created'],
                'livemode' => $data['livemode'],
            ];
        } catch (\Exception $e) {
            return 'Error creating customer: '.$e->getMessage();
        }
    }

    /**
     * Create a Stripe subscription.
     *
     * @param string               $customerId      Customer ID
     * @param array<int, string>   $priceIds        Array of price IDs
     * @param string               $paymentBehavior Payment behavior (default_incomplete, allow_incomplete, error_if_incomplete)
     * @param array<string, mixed> $metadata        Optional metadata
     *
     * @return array{
     *     id: string,
     *     status: string,
     *     customer: string,
     *     current_period_start: int,
     *     current_period_end: int,
     *     created: int,
     *     metadata: array<string, mixed>,
     *     latest_invoice: string|null,
     * }|string
     */
    public function createSubscription(
        string $customerId,
        array $priceIds,
        string $paymentBehavior = 'default_incomplete',
        array $metadata = [],
    ): array|string {
        try {
            $payload = [
                'customer' => $customerId,
                'payment_behavior' => $paymentBehavior,
                'items' => array_map(fn ($priceId) => ['price' => $priceId], $priceIds),
            ];

            if (!empty($metadata)) {
                $payload['metadata'] = $metadata;
            }

            $response = $this->httpClient->request('POST', 'https://api.stripe.com/v1/subscriptions', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->secretKey,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query($payload),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating subscription: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'status' => $data['status'],
                'customer' => $data['customer'],
                'current_period_start' => $data['current_period_start'],
                'current_period_end' => $data['current_period_end'],
                'created' => $data['created'],
                'metadata' => $data['metadata'] ?? [],
                'latest_invoice' => $data['latest_invoice'] ?? null,
            ];
        } catch (\Exception $e) {
            return 'Error creating subscription: '.$e->getMessage();
        }
    }

    /**
     * List Stripe payments.
     *
     * @param int    $limit    Maximum number of payments to retrieve
     * @param string $customer Optional customer ID filter
     * @param string $status   Optional status filter
     *
     * @return array<int, array{
     *     id: string,
     *     amount: int,
     *     currency: string,
     *     status: string,
     *     customer: string|null,
     *     description: string|null,
     *     metadata: array<string, mixed>,
     *     created: int,
     *     payment_method: string|null,
     * }>
     */
    public function listPayments(
        int $limit = 10,
        string $customer = '',
        string $status = '',
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 100),
            ];

            if ($customer) {
                $params['customer'] = $customer;
            }

            if ($status) {
                $params['status'] = $status;
            }

            $response = $this->httpClient->request('GET', 'https://api.stripe.com/v1/payment_intents', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->secretKey,
                ],
                'query' => $params,
            ]);

            $data = $response->toArray();

            if (!isset($data['data'])) {
                return [];
            }

            $payments = [];
            foreach ($data['data'] as $payment) {
                $payments[] = [
                    'id' => $payment['id'],
                    'amount' => $payment['amount'],
                    'currency' => $payment['currency'],
                    'status' => $payment['status'],
                    'customer' => $payment['customer'] ?? null,
                    'description' => $payment['description'] ?? null,
                    'metadata' => $payment['metadata'] ?? [],
                    'created' => $payment['created'],
                    'payment_method' => $payment['payment_method'] ?? null,
                ];
            }

            return $payments;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Refund a Stripe payment.
     *
     * @param string               $paymentIntentId Payment intent ID to refund
     * @param int                  $amount          Refund amount in cents (optional, full refund if not specified)
     * @param string               $reason          Refund reason (duplicate, fraudulent, requested_by_customer)
     * @param array<string, mixed> $metadata        Optional metadata
     *
     * @return array{
     *     id: string,
     *     amount: int,
     *     currency: string,
     *     status: string,
     *     payment_intent: string,
     *     reason: string|null,
     *     metadata: array<string, mixed>,
     *     created: int,
     * }|string
     */
    public function refundPayment(
        string $paymentIntentId,
        int $amount = 0,
        string $reason = 'requested_by_customer',
        array $metadata = [],
    ): array|string {
        try {
            $payload = [
                'payment_intent' => $paymentIntentId,
                'reason' => $reason,
            ];

            if ($amount > 0) {
                $payload['amount'] = $amount;
            }

            if (!empty($metadata)) {
                $payload['metadata'] = $metadata;
            }

            $response = $this->httpClient->request('POST', 'https://api.stripe.com/v1/refunds', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->secretKey,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query($payload),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error refunding payment: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'status' => $data['status'],
                'payment_intent' => $data['payment_intent'],
                'reason' => $data['reason'] ?? null,
                'metadata' => $data['metadata'] ?? [],
                'created' => $data['created'],
            ];
        } catch (\Exception $e) {
            return 'Error refunding payment: '.$e->getMessage();
        }
    }

    /**
     * Create a Stripe product.
     *
     * @param string               $name        Product name
     * @param string               $description Product description
     * @param array<string, mixed> $metadata    Optional metadata
     * @param bool                 $active      Whether the product is active
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     description: string|null,
     *     active: bool,
     *     metadata: array<string, mixed>,
     *     created: int,
     *     updated: int,
     * }|string
     */
    public function createProduct(
        string $name,
        string $description = '',
        array $metadata = [],
        bool $active = true,
    ): array|string {
        try {
            $payload = [
                'name' => $name,
                'active' => $active,
            ];

            if ($description) {
                $payload['description'] = $description;
            }

            if (!empty($metadata)) {
                $payload['metadata'] = $metadata;
            }

            $response = $this->httpClient->request('POST', 'https://api.stripe.com/v1/products', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->secretKey,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query($payload),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating product: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'active' => $data['active'],
                'metadata' => $data['metadata'] ?? [],
                'created' => $data['created'],
                'updated' => $data['updated'],
            ];
        } catch (\Exception $e) {
            return 'Error creating product: '.$e->getMessage();
        }
    }

    /**
     * Create a Stripe price.
     *
     * @param string               $productId  Product ID
     * @param int                  $unitAmount Unit amount in cents
     * @param string               $currency   Currency code
     * @param string               $recurring  Recurring interval (day, week, month, year) or empty for one-time
     * @param array<string, mixed> $metadata   Optional metadata
     *
     * @return array{
     *     id: string,
     *     product: string,
     *     unit_amount: int,
     *     currency: string,
     *     recurring: array{interval: string, interval_count: int}|null,
     *     active: bool,
     *     metadata: array<string, mixed>,
     *     created: int,
     *     type: string,
     * }|string
     */
    public function createPrice(
        string $productId,
        int $unitAmount,
        string $currency = 'usd',
        string $recurring = '',
        array $metadata = [],
    ): array|string {
        try {
            $payload = [
                'product' => $productId,
                'unit_amount' => $unitAmount,
                'currency' => strtolower($currency),
            ];

            if ($recurring) {
                $payload['recurring'] = [
                    'interval' => $recurring,
                ];
            }

            if (!empty($metadata)) {
                $payload['metadata'] = $metadata;
            }

            $response = $this->httpClient->request('POST', 'https://api.stripe.com/v1/prices', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->secretKey,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query($payload),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating price: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'product' => $data['product'],
                'unit_amount' => $data['unit_amount'],
                'currency' => $data['currency'],
                'recurring' => $data['recurring'] ?? null,
                'active' => $data['active'],
                'metadata' => $data['metadata'] ?? [],
                'created' => $data['created'],
                'type' => $data['type'],
            ];
        } catch (\Exception $e) {
            return 'Error creating price: '.$e->getMessage();
        }
    }

    /**
     * Get Stripe customer information.
     *
     * @param string $customerId Customer ID
     *
     * @return array{
     *     id: string,
     *     email: string,
     *     name: string|null,
     *     description: string|null,
     *     balance: int,
     *     currency: string|null,
     *     created: int,
     *     livemode: bool,
     *     metadata: array<string, mixed>,
     *     subscriptions: array{data: array<int, array<string, mixed>>},
     * }|string
     */
    public function getCustomer(string $customerId): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.stripe.com/v1/customers/{$customerId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->secretKey,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting customer: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'email' => $data['email'],
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'balance' => $data['balance'],
                'currency' => $data['currency'] ?? null,
                'created' => $data['created'],
                'livemode' => $data['livemode'],
                'metadata' => $data['metadata'] ?? [],
                'subscriptions' => $data['subscriptions'] ?? ['data' => []],
            ];
        } catch (\Exception $e) {
            return 'Error getting customer: '.$e->getMessage();
        }
    }
}

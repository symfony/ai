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
#[AsTool('shopify_get_products', 'Tool that gets Shopify products')]
#[AsTool('shopify_create_product', 'Tool that creates Shopify products', method: 'createProduct')]
#[AsTool('shopify_get_orders', 'Tool that gets Shopify orders', method: 'getOrders')]
#[AsTool('shopify_create_order', 'Tool that creates Shopify orders', method: 'createOrder')]
#[AsTool('shopify_get_customers', 'Tool that gets Shopify customers', method: 'getCustomers')]
#[AsTool('shopify_create_customer', 'Tool that creates Shopify customers', method: 'createCustomer')]
#[AsTool('shopify_update_inventory', 'Tool that updates Shopify inventory', method: 'updateInventory')]
final readonly class Shopify
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $shopDomain,
        #[\SensitiveParameter] private string $accessToken,
        private array $options = [],
    ) {
    }

    /**
     * Get Shopify products.
     *
     * @param int    $limit       Number of products to retrieve (1-250)
     * @param string $ids         Comma-separated list of product IDs
     * @param string $title       Filter by product title
     * @param string $vendor      Filter by vendor
     * @param string $productType Filter by product type
     * @param string $status      Filter by status (active, archived, draft)
     *
     * @return array<int, array{
     *     id: int,
     *     title: string,
     *     body_html: string,
     *     vendor: string,
     *     product_type: string,
     *     created_at: string,
     *     updated_at: string,
     *     published_at: string,
     *     template_suffix: string,
     *     status: string,
     *     published_scope: string,
     *     tags: string,
     *     admin_graphql_api_id: string,
     *     variants: array<int, array{
     *         id: int,
     *         product_id: int,
     *         title: string,
     *         price: string,
     *         sku: string,
     *         position: int,
     *         inventory_policy: string,
     *         compare_at_price: string,
     *         fulfillment_service: string,
     *         inventory_management: string,
     *         option1: string,
     *         option2: string,
     *         option3: string,
     *         created_at: string,
     *         updated_at: string,
     *         taxable: bool,
     *         barcode: string,
     *         grams: int,
     *         image_id: int,
     *         weight: float,
     *         weight_unit: string,
     *         inventory_item_id: int,
     *         inventory_quantity: int,
     *         old_inventory_quantity: int,
     *         requires_shipping: bool,
     *         admin_graphql_api_id: string,
     *     }>,
     *     options: array<int, array{
     *         id: int,
     *         product_id: int,
     *         name: string,
     *         position: int,
     *         values: array<int, string>,
     *     }>,
     *     images: array<int, array{
     *         id: int,
     *         product_id: int,
     *         position: int,
     *         created_at: string,
     *         updated_at: string,
     *         alt: string,
     *         width: int,
     *         height: int,
     *         src: string,
     *         variant_ids: array<int, int>,
     *         admin_graphql_api_id: string,
     *     }>,
     *     image: array{
     *         id: int,
     *         product_id: int,
     *         position: int,
     *         created_at: string,
     *         updated_at: string,
     *         alt: string,
     *         width: int,
     *         height: int,
     *         src: string,
     *         variant_ids: array<int, int>,
     *         admin_graphql_api_id: string,
     *     }|null,
     * }>
     */
    public function __invoke(
        int $limit = 50,
        string $ids = '',
        string $title = '',
        string $vendor = '',
        string $productType = '',
        string $status = 'active',
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 250),
            ];

            if ($ids) {
                $params['ids'] = $ids;
            }
            if ($title) {
                $params['title'] = $title;
            }
            if ($vendor) {
                $params['vendor'] = $vendor;
            }
            if ($productType) {
                $params['product_type'] = $productType;
            }
            if ($status) {
                $params['status'] = $status;
            }

            $response = $this->httpClient->request('GET', "https://{$this->shopDomain}.myshopify.com/admin/api/2023-10/products.json", [
                'headers' => [
                    'X-Shopify-Access-Token' => $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['products'])) {
                return [];
            }

            $products = [];
            foreach ($data['products'] as $product) {
                $products[] = [
                    'id' => $product['id'],
                    'title' => $product['title'],
                    'body_html' => $product['body_html'] ?? '',
                    'vendor' => $product['vendor'],
                    'product_type' => $product['product_type'],
                    'created_at' => $product['created_at'],
                    'updated_at' => $product['updated_at'],
                    'published_at' => $product['published_at'] ?? '',
                    'template_suffix' => $product['template_suffix'] ?? '',
                    'status' => $product['status'],
                    'published_scope' => $product['published_scope'],
                    'tags' => $product['tags'] ?? '',
                    'admin_graphql_api_id' => $product['admin_graphql_api_id'],
                    'variants' => array_map(fn ($variant) => [
                        'id' => $variant['id'],
                        'product_id' => $variant['product_id'],
                        'title' => $variant['title'],
                        'price' => $variant['price'],
                        'sku' => $variant['sku'] ?? '',
                        'position' => $variant['position'],
                        'inventory_policy' => $variant['inventory_policy'],
                        'compare_at_price' => $variant['compare_at_price'] ?? '',
                        'fulfillment_service' => $variant['fulfillment_service'],
                        'inventory_management' => $variant['inventory_management'],
                        'option1' => $variant['option1'] ?? '',
                        'option2' => $variant['option2'] ?? '',
                        'option3' => $variant['option3'] ?? '',
                        'created_at' => $variant['created_at'],
                        'updated_at' => $variant['updated_at'],
                        'taxable' => $variant['taxable'],
                        'barcode' => $variant['barcode'] ?? '',
                        'grams' => $variant['grams'],
                        'image_id' => $variant['image_id'] ?? null,
                        'weight' => $variant['weight'],
                        'weight_unit' => $variant['weight_unit'],
                        'inventory_item_id' => $variant['inventory_item_id'],
                        'inventory_quantity' => $variant['inventory_quantity'] ?? 0,
                        'old_inventory_quantity' => $variant['old_inventory_quantity'] ?? 0,
                        'requires_shipping' => $variant['requires_shipping'],
                        'admin_graphql_api_id' => $variant['admin_graphql_api_id'],
                    ], $product['variants']),
                    'options' => array_map(fn ($option) => [
                        'id' => $option['id'],
                        'product_id' => $option['product_id'],
                        'name' => $option['name'],
                        'position' => $option['position'],
                        'values' => $option['values'],
                    ], $product['options']),
                    'images' => array_map(fn ($image) => [
                        'id' => $image['id'],
                        'product_id' => $image['product_id'],
                        'position' => $image['position'],
                        'created_at' => $image['created_at'],
                        'updated_at' => $image['updated_at'],
                        'alt' => $image['alt'] ?? '',
                        'width' => $image['width'],
                        'height' => $image['height'],
                        'src' => $image['src'],
                        'variant_ids' => $image['variant_ids'],
                        'admin_graphql_api_id' => $image['admin_graphql_api_id'],
                    ], $product['images']),
                    'image' => $product['image'] ? [
                        'id' => $product['image']['id'],
                        'product_id' => $product['image']['product_id'],
                        'position' => $product['image']['position'],
                        'created_at' => $product['image']['created_at'],
                        'updated_at' => $product['image']['updated_at'],
                        'alt' => $product['image']['alt'] ?? '',
                        'width' => $product['image']['width'],
                        'height' => $product['image']['height'],
                        'src' => $product['image']['src'],
                        'variant_ids' => $product['image']['variant_ids'],
                        'admin_graphql_api_id' => $product['image']['admin_graphql_api_id'],
                    ] : null,
                ];
            }

            return $products;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a Shopify product.
     *
     * @param string                                                                                $title       Product title
     * @param string                                                                                $bodyHtml    Product description HTML
     * @param string                                                                                $vendor      Product vendor
     * @param string                                                                                $productType Product type
     * @param string                                                                                $tags        Product tags (comma-separated)
     * @param array<int, array{title: string, price: string, sku: string, inventory_quantity: int}> $variants    Product variants
     * @param array<int, array{src: string, alt: string}>                                           $images      Product images
     *
     * @return array{
     *     id: int,
     *     title: string,
     *     body_html: string,
     *     vendor: string,
     *     product_type: string,
     *     created_at: string,
     *     updated_at: string,
     *     published_at: string,
     *     status: string,
     *     published_scope: string,
     *     tags: string,
     *     admin_graphql_api_id: string,
     *     variants: array<int, array<string, mixed>>,
     *     options: array<int, array<string, mixed>>,
     *     images: array<int, array<string, mixed>>,
     * }|string
     */
    public function createProduct(
        string $title,
        string $bodyHtml = '',
        string $vendor = '',
        string $productType = '',
        string $tags = '',
        array $variants = [],
        array $images = [],
    ): array|string {
        try {
            $payload = [
                'product' => [
                    'title' => $title,
                    'body_html' => $bodyHtml,
                    'vendor' => $vendor,
                    'product_type' => $productType,
                    'tags' => $tags,
                ],
            ];

            if (!empty($variants)) {
                $payload['product']['variants'] = array_map(fn ($variant) => [
                    'title' => $variant['title'],
                    'price' => $variant['price'],
                    'sku' => $variant['sku'] ?? '',
                    'inventory_quantity' => $variant['inventory_quantity'] ?? 0,
                ], $variants);
            }

            if (!empty($images)) {
                $payload['product']['images'] = array_map(fn ($image) => [
                    'src' => $image['src'],
                    'alt' => $image['alt'] ?? '',
                ], $images);
            }

            $response = $this->httpClient->request('POST', "https://{$this->shopDomain}.myshopify.com/admin/api/2023-10/products.json", [
                'headers' => [
                    'X-Shopify-Access-Token' => $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error creating product: '.implode(', ', $data['errors']);
            }

            $product = $data['product'];

            return [
                'id' => $product['id'],
                'title' => $product['title'],
                'body_html' => $product['body_html'] ?? '',
                'vendor' => $product['vendor'],
                'product_type' => $product['product_type'],
                'created_at' => $product['created_at'],
                'updated_at' => $product['updated_at'],
                'published_at' => $product['published_at'] ?? '',
                'status' => $product['status'],
                'published_scope' => $product['published_scope'],
                'tags' => $product['tags'] ?? '',
                'admin_graphql_api_id' => $product['admin_graphql_api_id'],
                'variants' => $product['variants'],
                'options' => $product['options'],
                'images' => $product['images'],
            ];
        } catch (\Exception $e) {
            return 'Error creating product: '.$e->getMessage();
        }
    }

    /**
     * Get Shopify orders.
     *
     * @param int    $limit             Number of orders to retrieve (1-250)
     * @param string $status            Filter by status (open, closed, cancelled, any)
     * @param string $financialStatus   Filter by financial status (authorized, pending, paid, partially_paid, refunded, voided, partially_refunded, unpaid, any)
     * @param string $fulfillmentStatus Filter by fulfillment status (fulfilled, null, partial, restocked, any)
     * @param string $createdAtMin      Filter orders created after this date
     * @param string $createdAtMax      Filter orders created before this date
     *
     * @return array<int, array{
     *     id: int,
     *     admin_graphql_api_id: string,
     *     app_id: int,
     *     browser_ip: string,
     *     buyer_accepts_marketing: bool,
     *     cancel_reason: string,
     *     cancelled_at: string,
     *     cart_token: string,
     *     checkout_id: int,
     *     checkout_token: string,
     *     closed_at: string,
     *     confirmed: bool,
     *     created_at: string,
     *     currency: string,
     *     current_subtotal_price: string,
     *     current_subtotal_price_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}},
     *     current_total_discounts: string,
     *     current_total_discounts_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}},
     *     current_total_duties_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}}|null,
     *     current_total_price: string,
     *     current_total_price_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}},
     *     current_total_tax: string,
     *     current_total_tax_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}},
     *     customer_locale: string,
     *     device_id: int,
     *     email: string,
     *     estimated_taxes: bool,
     *     financial_status: string,
     *     fulfillment_status: string,
     *     gateway: string,
     *     landing_site: string,
     *     landing_site_ref: string,
     *     location_id: int,
     *     name: string,
     *     note: string,
     *     number: int,
     *     order_number: int,
     *     order_status_url: string,
     *     original_total_duties_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}}|null,
     *     phone: string,
     *     presentment_currency: string,
     *     processed_at: string,
     *     reference: string,
     *     referring_site: string,
     *     source_identifier: string,
     *     source_name: string,
     *     source_url: string,
     *     subtotal_price: string,
     *     subtotal_price_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}},
     *     tags: string,
     *     tax_lines: array<int, array{title: string, price: string, rate: float, price_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}}, channel_liable: bool|null}>,
     *     taxes_included: bool,
     *     test: bool,
     *     token: string,
     *     total_discounts: string,
     *     total_discounts_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}},
     *     total_line_items_price: string,
     *     total_line_items_price_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}},
     *     total_outstanding: string,
     *     total_price: string,
     *     total_price_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}},
     *     total_price_usd: string,
     *     total_shipping_price_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}},
     *     total_tax: string,
     *     total_tax_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}},
     *     total_tip_received: string,
     *     total_weight: int,
     *     updated_at: string,
     *     user_id: int,
     *     billing_address: array<string, mixed>|null,
     *     customer: array{
     *         id: int,
     *         email: string,
     *         accepts_marketing: bool,
     *         created_at: string,
     *         updated_at: string,
     *         first_name: string,
     *         last_name: string,
     *         orders_count: int,
     *         state: string,
     *         total_spent: string,
     *         last_order_id: int,
     *         note: string,
     *         verified_email: bool,
     *         multipass_identifier: string,
     *         tax_exempt: bool,
     *         phone: string,
     *         tags: string,
     *         last_order_name: string,
     *         currency: string,
     *         accepts_marketing_updated_at: string,
     *         marketing_opt_in_level: string,
     *         tax_exemptions: array<int, string>,
     *         admin_graphql_api_id: string,
     *         default_address: array<string, mixed>,
     *     },
     *     discount_applications: array<int, array<string, mixed>>,
     *     fulfillments: array<int, array<string, mixed>>,
     *     line_items: array<int, array{
     *         id: int,
     *         admin_graphql_api_id: string,
     *         fulfillable_quantity: int,
     *         fulfillment_service: string,
     *         fulfillment_status: string,
     *         gift_card: bool,
     *         grams: int,
     *         name: string,
     *         origin_location: array{id: int, country_code: string, province_code: string, name: string, address1: string, address2: string, city: string, zip: string},
     *         price: string,
     *         price_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}},
     *         product_exists: bool,
     *         product_id: int,
     *         properties: array<int, array{name: string, value: string}>,
     *         quantity: int,
     *         requires_shipping: bool,
     *         sku: string,
     *         taxable: bool,
     *         title: string,
     *         total_discount: string,
     *         total_discount_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}},
     *         variant_id: int,
     *         variant_inventory_management: string,
     *         variant_title: string,
     *         vendor: string,
     *         tax_lines: array<int, array{title: string, price: string, rate: float, price_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}}, channel_liable: bool|null}>,
     *         duties: array<int, array<string, mixed>>,
     *         discount_allocations: array<int, array<string, mixed>>,
     *     }>,
     *     payment_terms: array<string, mixed>|null,
     *     refunds: array<int, array<string, mixed>>,
     *     shipping_address: array<string, mixed>|null,
     *     shipping_lines: array<int, array{
     *         id: int,
     *         title: string,
     *         price: string,
     *         code: string,
     *         source: string,
     *         phone: string,
     *         requested_fulfillment_service_id: string,
     *         delivery_category: string,
     *         carrier_identifier: string,
     *         discounted_price: string,
     *         price_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}},
     *         discounted_price_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}},
     *         discount_allocations: array<int, array<string, mixed>>,
     *         tax_lines: array<int, array{title: string, price: string, rate: float, price_set: array{shop_money: array{amount: string, currency_code: string}, presentment_money: array{amount: string, currency_code: string}}, channel_liable: bool|null}>,
     *     }>,
     * }>
     */
    public function getOrders(
        int $limit = 50,
        string $status = '',
        string $financialStatus = '',
        string $fulfillmentStatus = '',
        string $createdAtMin = '',
        string $createdAtMax = '',
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 250),
            ];

            if ($status) {
                $params['status'] = $status;
            }
            if ($financialStatus) {
                $params['financial_status'] = $financialStatus;
            }
            if ($fulfillmentStatus) {
                $params['fulfillment_status'] = $fulfillmentStatus;
            }
            if ($createdAtMin) {
                $params['created_at_min'] = $createdAtMin;
            }
            if ($createdAtMax) {
                $params['created_at_max'] = $createdAtMax;
            }

            $response = $this->httpClient->request('GET', "https://{$this->shopDomain}.myshopify.com/admin/api/2023-10/orders.json", [
                'headers' => [
                    'X-Shopify-Access-Token' => $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['orders'])) {
                return [];
            }

            // Return simplified order structure due to length constraints
            $orders = [];
            foreach ($data['orders'] as $order) {
                $orders[] = [
                    'id' => $order['id'],
                    'name' => $order['name'],
                    'email' => $order['email'] ?? '',
                    'created_at' => $order['created_at'],
                    'updated_at' => $order['updated_at'],
                    'total_price' => $order['total_price'],
                    'subtotal_price' => $order['subtotal_price'],
                    'total_tax' => $order['total_tax'],
                    'currency' => $order['currency'],
                    'financial_status' => $order['financial_status'],
                    'fulfillment_status' => $order['fulfillment_status'] ?? '',
                    'billing_address' => $order['billing_address'] ?? null,
                    'shipping_address' => $order['shipping_address'] ?? null,
                    'customer' => $order['customer'] ?? null,
                    'line_items' => array_map(fn ($item) => [
                        'id' => $item['id'],
                        'name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'total_discount' => $item['total_discount'] ?? '0.00',
                        'vendor' => $item['vendor'] ?? '',
                        'product_id' => $item['product_id'] ?? null,
                        'variant_id' => $item['variant_id'] ?? null,
                    ], $order['line_items']),
                ];
            }

            return $orders;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a Shopify order.
     *
     * @param array<int, array{title: string, price: string, quantity: int, variant_id: int}> $lineItems       Order line items
     * @param string                                                                          $email           Customer email
     * @param array<string, mixed>                                                            $billingAddress  Billing address
     * @param array<string, mixed>                                                            $shippingAddress Shipping address
     * @param string                                                                          $financialStatus Financial status
     * @param string                                                                          $currency        Currency code
     * @param string                                                                          $note            Order note
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     created_at: string,
     *     updated_at: string,
     *     total_price: string,
     *     subtotal_price: string,
     *     total_tax: string,
     *     currency: string,
     *     financial_status: string,
     *     fulfillment_status: string,
     *     line_items: array<int, array<string, mixed>>,
     * }|string
     */
    public function createOrder(
        array $lineItems,
        string $email,
        array $billingAddress = [],
        array $shippingAddress = [],
        string $financialStatus = 'pending',
        string $currency = 'USD',
        string $note = '',
    ): array|string {
        try {
            $payload = [
                'order' => [
                    'line_items' => array_map(fn ($item) => [
                        'title' => $item['title'],
                        'price' => $item['price'],
                        'quantity' => $item['quantity'],
                        'variant_id' => $item['variant_id'] ?? null,
                    ], $lineItems),
                    'email' => $email,
                    'financial_status' => $financialStatus,
                    'currency' => $currency,
                ],
            ];

            if ($note) {
                $payload['order']['note'] = $note;
            }

            if (!empty($billingAddress)) {
                $payload['order']['billing_address'] = $billingAddress;
            }

            if (!empty($shippingAddress)) {
                $payload['order']['shipping_address'] = $shippingAddress;
            }

            $response = $this->httpClient->request('POST', "https://{$this->shopDomain}.myshopify.com/admin/api/2023-10/orders.json", [
                'headers' => [
                    'X-Shopify-Access-Token' => $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error creating order: '.implode(', ', $data['errors']);
            }

            $order = $data['order'];

            return [
                'id' => $order['id'],
                'name' => $order['name'],
                'email' => $order['email'],
                'created_at' => $order['created_at'],
                'updated_at' => $order['updated_at'],
                'total_price' => $order['total_price'],
                'subtotal_price' => $order['subtotal_price'],
                'total_tax' => $order['total_tax'],
                'currency' => $order['currency'],
                'financial_status' => $order['financial_status'],
                'fulfillment_status' => $order['fulfillment_status'] ?? '',
                'line_items' => $order['line_items'],
            ];
        } catch (\Exception $e) {
            return 'Error creating order: '.$e->getMessage();
        }
    }

    /**
     * Get Shopify customers.
     *
     * @param int    $limit Number of customers to retrieve (1-250)
     * @param string $ids   Comma-separated list of customer IDs
     * @param string $query Search query
     *
     * @return array<int, array{
     *     id: int,
     *     email: string,
     *     accepts_marketing: bool,
     *     created_at: string,
     *     updated_at: string,
     *     first_name: string,
     *     last_name: string,
     *     orders_count: int,
     *     state: string,
     *     total_spent: string,
     *     last_order_id: int,
     *     note: string,
     *     verified_email: bool,
     *     multipass_identifier: string,
     *     tax_exempt: bool,
     *     phone: string,
     *     tags: string,
     *     last_order_name: string,
     *     currency: string,
     *     accepts_marketing_updated_at: string,
     *     marketing_opt_in_level: string,
     *     tax_exemptions: array<int, string>,
     *     admin_graphql_api_id: string,
     *     default_address: array<string, mixed>,
     * }>
     */
    public function getCustomers(
        int $limit = 50,
        string $ids = '',
        string $query = '',
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 250),
            ];

            if ($ids) {
                $params['ids'] = $ids;
            }
            if ($query) {
                $params['query'] = $query;
            }

            $response = $this->httpClient->request('GET', "https://{$this->shopDomain}.myshopify.com/admin/api/2023-10/customers.json", [
                'headers' => [
                    'X-Shopify-Access-Token' => $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['customers'])) {
                return [];
            }

            $customers = [];
            foreach ($data['customers'] as $customer) {
                $customers[] = [
                    'id' => $customer['id'],
                    'email' => $customer['email'],
                    'accepts_marketing' => $customer['accepts_marketing'],
                    'created_at' => $customer['created_at'],
                    'updated_at' => $customer['updated_at'],
                    'first_name' => $customer['first_name'] ?? '',
                    'last_name' => $customer['last_name'] ?? '',
                    'orders_count' => $customer['orders_count'],
                    'state' => $customer['state'],
                    'total_spent' => $customer['total_spent'],
                    'last_order_id' => $customer['last_order_id'] ?? null,
                    'note' => $customer['note'] ?? '',
                    'verified_email' => $customer['verified_email'],
                    'multipass_identifier' => $customer['multipass_identifier'] ?? '',
                    'tax_exempt' => $customer['tax_exempt'],
                    'phone' => $customer['phone'] ?? '',
                    'tags' => $customer['tags'] ?? '',
                    'last_order_name' => $customer['last_order_name'] ?? '',
                    'currency' => $customer['currency'],
                    'accepts_marketing_updated_at' => $customer['accepts_marketing_updated_at'] ?? '',
                    'marketing_opt_in_level' => $customer['marketing_opt_in_level'] ?? '',
                    'tax_exemptions' => $customer['tax_exemptions'] ?? [],
                    'admin_graphql_api_id' => $customer['admin_graphql_api_id'],
                    'default_address' => $customer['default_address'] ?? [],
                ];
            }

            return $customers;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a Shopify customer.
     *
     * @param string               $email            Customer email
     * @param string               $firstName        Customer first name
     * @param string               $lastName         Customer last name
     * @param string               $phone            Customer phone number
     * @param bool                 $acceptsMarketing Whether customer accepts marketing
     * @param string               $note             Customer note
     * @param array<string, mixed> $address          Customer default address
     *
     * @return array{
     *     id: int,
     *     email: string,
     *     accepts_marketing: bool,
     *     created_at: string,
     *     updated_at: string,
     *     first_name: string,
     *     last_name: string,
     *     orders_count: int,
     *     state: string,
     *     total_spent: string,
     *     note: string,
     *     verified_email: bool,
     *     phone: string,
     *     currency: string,
     *     admin_graphql_api_id: string,
     * }|string
     */
    public function createCustomer(
        string $email,
        string $firstName = '',
        string $lastName = '',
        string $phone = '',
        bool $acceptsMarketing = false,
        string $note = '',
        array $address = [],
    ): array|string {
        try {
            $payload = [
                'customer' => [
                    'email' => $email,
                    'accepts_marketing' => $acceptsMarketing,
                ],
            ];

            if ($firstName) {
                $payload['customer']['first_name'] = $firstName;
            }
            if ($lastName) {
                $payload['customer']['last_name'] = $lastName;
            }
            if ($phone) {
                $payload['customer']['phone'] = $phone;
            }
            if ($note) {
                $payload['customer']['note'] = $note;
            }
            if (!empty($address)) {
                $payload['customer']['address'] = $address;
            }

            $response = $this->httpClient->request('POST', "https://{$this->shopDomain}.myshopify.com/admin/api/2023-10/customers.json", [
                'headers' => [
                    'X-Shopify-Access-Token' => $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error creating customer: '.implode(', ', $data['errors']);
            }

            $customer = $data['customer'];

            return [
                'id' => $customer['id'],
                'email' => $customer['email'],
                'accepts_marketing' => $customer['accepts_marketing'],
                'created_at' => $customer['created_at'],
                'updated_at' => $customer['updated_at'],
                'first_name' => $customer['first_name'] ?? '',
                'last_name' => $customer['last_name'] ?? '',
                'orders_count' => $customer['orders_count'] ?? 0,
                'state' => $customer['state'],
                'total_spent' => $customer['total_spent'],
                'note' => $customer['note'] ?? '',
                'verified_email' => $customer['verified_email'],
                'phone' => $customer['phone'] ?? '',
                'currency' => $customer['currency'],
                'admin_graphql_api_id' => $customer['admin_graphql_api_id'],
            ];
        } catch (\Exception $e) {
            return 'Error creating customer: '.$e->getMessage();
        }
    }

    /**
     * Update Shopify inventory.
     *
     * @param int $inventoryItemId Inventory item ID
     * @param int $available       Available quantity
     * @param int $locationId      Location ID
     *
     * @return array{
     *     inventory_item_id: int,
     *     location_id: int,
     *     available: int,
     *     updated_at: string,
     * }|string
     */
    public function updateInventory(
        int $inventoryItemId,
        int $available,
        int $locationId,
    ): array|string {
        try {
            $payload = [
                'location_id' => $locationId,
                'inventory_item_id' => $inventoryItemId,
                'available' => $available,
            ];

            $response = $this->httpClient->request('POST', "https://{$this->shopDomain}.myshopify.com/admin/api/2023-10/inventory_levels/set.json", [
                'headers' => [
                    'X-Shopify-Access-Token' => $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error updating inventory: '.implode(', ', $data['errors']);
            }

            $inventoryLevel = $data['inventory_level'];

            return [
                'inventory_item_id' => $inventoryLevel['inventory_item_id'],
                'location_id' => $inventoryLevel['location_id'],
                'available' => $inventoryLevel['available'],
                'updated_at' => $inventoryLevel['updated_at'],
            ];
        } catch (\Exception $e) {
            return 'Error updating inventory: '.$e->getMessage();
        }
    }
}

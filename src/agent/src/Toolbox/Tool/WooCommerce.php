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
#[AsTool('woocommerce_get_products', 'Tool that gets WooCommerce products')]
#[AsTool('woocommerce_create_product', 'Tool that creates WooCommerce products', method: 'createProduct')]
#[AsTool('woocommerce_get_orders', 'Tool that gets WooCommerce orders', method: 'getOrders')]
#[AsTool('woocommerce_create_order', 'Tool that creates WooCommerce orders', method: 'createOrder')]
#[AsTool('woocommerce_get_customers', 'Tool that gets WooCommerce customers', method: 'getCustomers')]
#[AsTool('woocommerce_create_customer', 'Tool that creates WooCommerce customers', method: 'createCustomer')]
final readonly class WooCommerce
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $consumerKey,
        #[\SensitiveParameter] private string $consumerSecret,
        private string $storeUrl,
        private string $apiVersion = 'wc/v3',
        private array $options = [],
    ) {
    }

    /**
     * Get WooCommerce products.
     *
     * @param int    $perPage     Number of products per page
     * @param int    $page        Page number
     * @param string $search      Search term
     * @param string $category    Category slug
     * @param string $status      Product status (draft, pending, private, publish)
     * @param string $stockStatus Stock status (instock, outofstock, onbackorder)
     * @param string $orderBy     Order by field (date, id, include, title, slug, price, popularity, rating)
     * @param string $order       Order direction (asc, desc)
     *
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     permalink: string,
     *     date_created: string,
     *     date_modified: string,
     *     type: string,
     *     status: string,
     *     featured: bool,
     *     catalog_visibility: string,
     *     description: string,
     *     short_description: string,
     *     sku: string,
     *     price: string,
     *     regular_price: string,
     *     sale_price: string,
     *     date_on_sale_from: string|null,
     *     date_on_sale_to: string|null,
     *     on_sale: bool,
     *     purchasable: bool,
     *     total_sales: int,
     *     virtual: bool,
     *     downloadable: bool,
     *     downloads: array<int, mixed>,
     *     download_limit: int,
     *     download_expiry: int,
     *     external_url: string,
     *     button_text: string,
     *     tax_status: string,
     *     tax_class: string,
     *     manage_stock: bool,
     *     stock_quantity: int|null,
     *     stock_status: string,
     *     backorders: string,
     *     backorders_allowed: bool,
     *     backordered: bool,
     *     sold_individually: bool,
     *     weight: string,
     *     dimensions: array{length: string, width: string, height: string},
     *     shipping_required: bool,
     *     shipping_taxable: bool,
     *     shipping_class: string,
     *     shipping_class_id: int,
     *     reviews_allowed: bool,
     *     average_rating: string,
     *     rating_count: int,
     *     related_ids: array<int, int>,
     *     upsell_ids: array<int, int>,
     *     cross_sell_ids: array<int, int>,
     *     parent_id: int,
     *     purchase_note: string,
     *     categories: array<int, array{id: int, name: string, slug: string}>,
     *     tags: array<int, array{id: int, name: string, slug: string}>,
     *     images: array<int, array{id: int, src: string, name: string, alt: string}>,
     *     attributes: array<int, array{id: int, name: string, position: int, visible: bool, variation: bool, options: array<int, string>}>,
     *     default_attributes: array<int, array{id: int, name: string, option: string}>,
     *     variations: array<int, int>,
     *     grouped_products: array<int, int>,
     *     menu_order: int,
     *     meta_data: array<int, array{id: int, key: string, value: mixed}>,
     *     _links: array{self: array<int, array{href: string}>, collection: array<int, array{href: string}>},
     * }>
     */
    public function __invoke(
        int $perPage = 10,
        int $page = 1,
        string $search = '',
        string $category = '',
        string $status = '',
        string $stockStatus = '',
        string $orderBy = 'date',
        string $order = 'desc',
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
                'orderby' => $orderBy,
                'order' => $order,
            ];

            if ($search) {
                $params['search'] = $search;
            }
            if ($category) {
                $params['category'] = $category;
            }
            if ($status) {
                $params['status'] = $status;
            }
            if ($stockStatus) {
                $params['stock_status'] = $stockStatus;
            }

            $response = $this->httpClient->request('GET', $this->buildUrl('products'), [
                'auth_basic' => [$this->consumerKey, $this->consumerSecret],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['code'])) {
                return [];
            }

            return array_map(fn ($product) => [
                'id' => $product['id'],
                'name' => $product['name'],
                'slug' => $product['slug'],
                'permalink' => $product['permalink'],
                'date_created' => $product['date_created'],
                'date_modified' => $product['date_modified'],
                'type' => $product['type'],
                'status' => $product['status'],
                'featured' => $product['featured'],
                'catalog_visibility' => $product['catalog_visibility'],
                'description' => $product['description'],
                'short_description' => $product['short_description'],
                'sku' => $product['sku'],
                'price' => $product['price'],
                'regular_price' => $product['regular_price'],
                'sale_price' => $product['sale_price'],
                'date_on_sale_from' => $product['date_on_sale_from'],
                'date_on_sale_to' => $product['date_on_sale_to'],
                'on_sale' => $product['on_sale'],
                'purchasable' => $product['purchasable'],
                'total_sales' => $product['total_sales'],
                'virtual' => $product['virtual'],
                'downloadable' => $product['downloadable'],
                'downloads' => $product['downloads'],
                'download_limit' => $product['download_limit'],
                'download_expiry' => $product['download_expiry'],
                'external_url' => $product['external_url'],
                'button_text' => $product['button_text'],
                'tax_status' => $product['tax_status'],
                'tax_class' => $product['tax_class'],
                'manage_stock' => $product['manage_stock'],
                'stock_quantity' => $product['stock_quantity'],
                'stock_status' => $product['stock_status'],
                'backorders' => $product['backorders'],
                'backorders_allowed' => $product['backorders_allowed'],
                'backordered' => $product['backordered'],
                'sold_individually' => $product['sold_individually'],
                'weight' => $product['weight'],
                'dimensions' => $product['dimensions'],
                'shipping_required' => $product['shipping_required'],
                'shipping_taxable' => $product['shipping_taxable'],
                'shipping_class' => $product['shipping_class'],
                'shipping_class_id' => $product['shipping_class_id'],
                'reviews_allowed' => $product['reviews_allowed'],
                'average_rating' => $product['average_rating'],
                'rating_count' => $product['rating_count'],
                'related_ids' => $product['related_ids'],
                'upsell_ids' => $product['upsell_ids'],
                'cross_sell_ids' => $product['cross_sell_ids'],
                'parent_id' => $product['parent_id'],
                'purchase_note' => $product['purchase_note'],
                'categories' => array_map(fn ($cat) => [
                    'id' => $cat['id'],
                    'name' => $cat['name'],
                    'slug' => $cat['slug'],
                ], $product['categories']),
                'tags' => array_map(fn ($tag) => [
                    'id' => $tag['id'],
                    'name' => $tag['name'],
                    'slug' => $tag['slug'],
                ], $product['tags']),
                'images' => array_map(fn ($img) => [
                    'id' => $img['id'],
                    'src' => $img['src'],
                    'name' => $img['name'],
                    'alt' => $img['alt'],
                ], $product['images']),
                'attributes' => array_map(fn ($attr) => [
                    'id' => $attr['id'],
                    'name' => $attr['name'],
                    'position' => $attr['position'],
                    'visible' => $attr['visible'],
                    'variation' => $attr['variation'],
                    'options' => $attr['options'],
                ], $product['attributes']),
                'default_attributes' => array_map(fn ($attr) => [
                    'id' => $attr['id'],
                    'name' => $attr['name'],
                    'option' => $attr['option'],
                ], $product['default_attributes']),
                'variations' => $product['variations'],
                'grouped_products' => $product['grouped_products'],
                'menu_order' => $product['menu_order'],
                'meta_data' => array_map(fn ($meta) => [
                    'id' => $meta['id'],
                    'key' => $meta['key'],
                    'value' => $meta['value'],
                ], $product['meta_data']),
                '_links' => $product['_links'],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a WooCommerce product.
     *
     * @param string                                                    $name             Product name
     * @param string                                                    $type             Product type (simple, grouped, external, variable)
     * @param string                                                    $regularPrice     Regular price
     * @param string                                                    $description      Product description
     * @param string                                                    $shortDescription Short description
     * @param string                                                    $sku              Product SKU
     * @param string                                                    $salePrice        Sale price
     * @param bool                                                      $manageStock      Whether to manage stock
     * @param int                                                       $stockQuantity    Stock quantity
     * @param string                                                    $stockStatus      Stock status
     * @param bool                                                      $virtual          Whether product is virtual
     * @param bool                                                      $downloadable     Whether product is downloadable
     * @param array<int, array{id: int}>                                $categories       Product categories
     * @param array<int, array{src: string, name: string, alt: string}> $images           Product images
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     permalink: string,
     *     date_created: string,
     *     date_modified: string,
     *     type: string,
     *     status: string,
     *     featured: bool,
     *     catalog_visibility: string,
     *     description: string,
     *     short_description: string,
     *     sku: string,
     *     price: string,
     *     regular_price: string,
     *     sale_price: string,
     *     on_sale: bool,
     *     purchasable: bool,
     *     total_sales: int,
     *     virtual: bool,
     *     downloadable: bool,
     *     stock_quantity: int|null,
     *     stock_status: string,
     *     categories: array<int, array{id: int, name: string, slug: string}>,
     *     images: array<int, array{id: int, src: string, name: string, alt: string}>,
     * }|string
     */
    public function createProduct(
        string $name,
        string $type = 'simple',
        string $regularPrice = '',
        string $description = '',
        string $shortDescription = '',
        string $sku = '',
        string $salePrice = '',
        bool $manageStock = false,
        int $stockQuantity = 0,
        string $stockStatus = 'instock',
        bool $virtual = false,
        bool $downloadable = false,
        array $categories = [],
        array $images = [],
    ): array|string {
        try {
            $payload = [
                'name' => $name,
                'type' => $type,
                'status' => 'publish',
                'featured' => false,
                'catalog_visibility' => 'visible',
                'description' => $description,
                'short_description' => $shortDescription,
                'sku' => $sku,
                'regular_price' => $regularPrice,
                'sale_price' => $salePrice,
                'virtual' => $virtual,
                'downloadable' => $downloadable,
                'manage_stock' => $manageStock,
                'stock_quantity' => $stockQuantity,
                'stock_status' => $stockStatus,
                'backorders' => 'no',
                'sold_individually' => false,
                'weight' => '',
                'dimensions' => ['length' => '', 'width' => '', 'height' => ''],
                'shipping_required' => !$virtual,
                'shipping_taxable' => true,
                'reviews_allowed' => true,
                'categories' => $categories,
                'images' => $images,
                'attributes' => [],
                'default_attributes' => [],
                'variations' => [],
                'grouped_products' => [],
            ];

            $response = $this->httpClient->request('POST', $this->buildUrl('products'), [
                'auth_basic' => [$this->consumerKey, $this->consumerSecret],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['code'])) {
                return 'Error creating product: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'slug' => $data['slug'],
                'permalink' => $data['permalink'],
                'date_created' => $data['date_created'],
                'date_modified' => $data['date_modified'],
                'type' => $data['type'],
                'status' => $data['status'],
                'featured' => $data['featured'],
                'catalog_visibility' => $data['catalog_visibility'],
                'description' => $data['description'],
                'short_description' => $data['short_description'],
                'sku' => $data['sku'],
                'price' => $data['price'],
                'regular_price' => $data['regular_price'],
                'sale_price' => $data['sale_price'],
                'on_sale' => $data['on_sale'],
                'purchasable' => $data['purchasable'],
                'total_sales' => $data['total_sales'],
                'virtual' => $data['virtual'],
                'downloadable' => $data['downloadable'],
                'stock_quantity' => $data['stock_quantity'],
                'stock_status' => $data['stock_status'],
                'categories' => array_map(fn ($cat) => [
                    'id' => $cat['id'],
                    'name' => $cat['name'],
                    'slug' => $cat['slug'],
                ], $data['categories']),
                'images' => array_map(fn ($img) => [
                    'id' => $img['id'],
                    'src' => $img['src'],
                    'name' => $img['name'],
                    'alt' => $img['alt'],
                ], $data['images']),
            ];
        } catch (\Exception $e) {
            return 'Error creating product: '.$e->getMessage();
        }
    }

    /**
     * Get WooCommerce orders.
     *
     * @param int    $perPage  Number of orders per page
     * @param int    $page     Page number
     * @param string $status   Order status
     * @param string $customer Customer ID
     * @param string $product  Product ID
     * @param string $orderBy  Order by field
     * @param string $order    Order direction
     *
     * @return array<int, array{
     *     id: int,
     *     parent_id: int,
     *     status: string,
     *     currency: string,
     *     date_created: string,
     *     date_modified: string,
     *     discount_total: string,
     *     discount_tax: string,
     *     shipping_total: string,
     *     shipping_tax: string,
     *     cart_tax: string,
     *     total: string,
     *     total_tax: string,
     *     customer_id: int,
     *     order_key: string,
     *     billing: array{
     *         first_name: string,
     *         last_name: string,
     *         company: string,
     *         address_1: string,
     *         address_2: string,
     *         city: string,
     *         state: string,
     *         postcode: string,
     *         country: string,
     *         email: string,
     *         phone: string,
     *     },
     *     shipping: array{
     *         first_name: string,
     *         last_name: string,
     *         company: string,
     *         address_1: string,
     *         address_2: string,
     *         city: string,
     *         state: string,
     *         postcode: string,
     *         country: string,
     *     },
     *     payment_method: string,
     *     payment_method_title: string,
     *     transaction_id: string,
     *     customer_ip_address: string,
     *     customer_user_agent: string,
     *     created_via: string,
     *     customer_note: string,
     *     date_completed: string|null,
     *     date_paid: string|null,
     *     cart_hash: string,
     *     number: string,
     *     meta_data: array<int, array{id: int, key: string, value: mixed}>,
     *     line_items: array<int, array{
     *         id: int,
     *         name: string,
     *         product_id: int,
     *         variation_id: int,
     *         quantity: int,
     *         tax_class: string,
     *         subtotal: string,
     *         subtotal_tax: string,
     *         total: string,
     *         total_tax: string,
     *         taxes: array<int, mixed>,
     *         meta_data: array<int, mixed>,
     *         sku: string,
     *         price: float,
     *         image: array{id: int, src: string, name: string, alt: string}|null,
     *     }>,
     *     tax_lines: array<int, mixed>,
     *     shipping_lines: array<int, mixed>,
     *     fee_lines: array<int, mixed>,
     *     coupon_lines: array<int, mixed>,
     *     refunds: array<int, mixed>,
     *     payment_url: string,
     *     is_editable: bool,
     *     needs_payment: bool,
     *     needs_processing: bool,
     *     date_created_gmt: string,
     *     date_modified_gmt: string,
     *     date_completed_gmt: string|null,
     *     date_paid_gmt: string|null,
     *     currency_symbol: string,
     *     _links: array{self: array<int, array{href: string}>, collection: array<int, array{href: string}>},
     * }>
     */
    public function getOrders(
        int $perPage = 10,
        int $page = 1,
        string $status = '',
        string $customer = '',
        string $product = '',
        string $orderBy = 'date',
        string $order = 'desc',
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
                'orderby' => $orderBy,
                'order' => $order,
            ];

            if ($status) {
                $params['status'] = $status;
            }
            if ($customer) {
                $params['customer'] = $customer;
            }
            if ($product) {
                $params['product'] = $product;
            }

            $response = $this->httpClient->request('GET', $this->buildUrl('orders'), [
                'auth_basic' => [$this->consumerKey, $this->consumerSecret],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['code'])) {
                return [];
            }

            return array_map(fn ($order) => [
                'id' => $order['id'],
                'parent_id' => $order['parent_id'],
                'status' => $order['status'],
                'currency' => $order['currency'],
                'date_created' => $order['date_created'],
                'date_modified' => $order['date_modified'],
                'discount_total' => $order['discount_total'],
                'discount_tax' => $order['discount_tax'],
                'shipping_total' => $order['shipping_total'],
                'shipping_tax' => $order['shipping_tax'],
                'cart_tax' => $order['cart_tax'],
                'total' => $order['total'],
                'total_tax' => $order['total_tax'],
                'customer_id' => $order['customer_id'],
                'order_key' => $order['order_key'],
                'billing' => $order['billing'],
                'shipping' => $order['shipping'],
                'payment_method' => $order['payment_method'],
                'payment_method_title' => $order['payment_method_title'],
                'transaction_id' => $order['transaction_id'],
                'customer_ip_address' => $order['customer_ip_address'],
                'customer_user_agent' => $order['customer_user_agent'],
                'created_via' => $order['created_via'],
                'customer_note' => $order['customer_note'],
                'date_completed' => $order['date_completed'],
                'date_paid' => $order['date_paid'],
                'cart_hash' => $order['cart_hash'],
                'number' => $order['number'],
                'meta_data' => array_map(fn ($meta) => [
                    'id' => $meta['id'],
                    'key' => $meta['key'],
                    'value' => $meta['value'],
                ], $order['meta_data']),
                'line_items' => array_map(fn ($item) => [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'product_id' => $item['product_id'],
                    'variation_id' => $item['variation_id'],
                    'quantity' => $item['quantity'],
                    'tax_class' => $item['tax_class'],
                    'subtotal' => $item['subtotal'],
                    'subtotal_tax' => $item['subtotal_tax'],
                    'total' => $item['total'],
                    'total_tax' => $item['total_tax'],
                    'taxes' => $item['taxes'],
                    'meta_data' => $item['meta_data'],
                    'sku' => $item['sku'],
                    'price' => $item['price'],
                    'image' => $item['image'],
                ], $order['line_items']),
                'tax_lines' => $order['tax_lines'],
                'shipping_lines' => $order['shipping_lines'],
                'fee_lines' => $order['fee_lines'],
                'coupon_lines' => $order['coupon_lines'],
                'refunds' => $order['refunds'],
                'payment_url' => $order['payment_url'],
                'is_editable' => $order['is_editable'],
                'needs_payment' => $order['needs_payment'],
                'needs_processing' => $order['needs_processing'],
                'date_created_gmt' => $order['date_created_gmt'],
                'date_modified_gmt' => $order['date_modified_gmt'],
                'date_completed_gmt' => $order['date_completed_gmt'],
                'date_paid_gmt' => $order['date_paid_gmt'],
                'currency_symbol' => $order['currency_symbol'],
                '_links' => $order['_links'],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a WooCommerce order.
     *
     * @param array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone: string,
     *     company: string,
     *     address_1: string,
     *     address_2: string,
     *     city: string,
     *     state: string,
     *     postcode: string,
     *     country: string,
     * } $billing Billing address
     * @param array{
     *     first_name: string,
     *     last_name: string,
     *     company: string,
     *     address_1: string,
     *     address_2: string,
     *     city: string,
     *     state: string,
     *     postcode: string,
     *     country: string,
     * } $shipping Shipping address
     * @param array<int, array{
     *     product_id: int,
     *     quantity: int,
     *     variation_id?: int,
     * }> $lineItems Order line items
     * @param string $paymentMethod Payment method
     * @param string $status        Order status
     * @param string $currency      Currency code
     *
     * @return array{
     *     id: int,
     *     parent_id: int,
     *     status: string,
     *     currency: string,
     *     date_created: string,
     *     date_modified: string,
     *     total: string,
     *     customer_id: int,
     *     order_key: string,
     *     billing: array<string, string>,
     *     shipping: array<string, string>,
     *     payment_method: string,
     *     payment_method_title: string,
     *     line_items: array<int, array<string, mixed>>,
     * }|string
     */
    public function createOrder(
        array $billing,
        array $shipping,
        array $lineItems,
        string $paymentMethod = 'bacs',
        string $status = 'pending',
        string $currency = 'USD',
    ): array|string {
        try {
            $payload = [
                'payment_method' => $paymentMethod,
                'payment_method_title' => ucfirst($paymentMethod),
                'set_paid' => false,
                'billing' => $billing,
                'shipping' => $shipping,
                'line_items' => $lineItems,
                'shipping_lines' => [],
                'fee_lines' => [],
                'coupon_lines' => [],
                'status' => $status,
                'currency' => $currency,
            ];

            $response = $this->httpClient->request('POST', $this->buildUrl('orders'), [
                'auth_basic' => [$this->consumerKey, $this->consumerSecret],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['code'])) {
                return 'Error creating order: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'parent_id' => $data['parent_id'],
                'status' => $data['status'],
                'currency' => $data['currency'],
                'date_created' => $data['date_created'],
                'date_modified' => $data['date_modified'],
                'total' => $data['total'],
                'customer_id' => $data['customer_id'],
                'order_key' => $data['order_key'],
                'billing' => $data['billing'],
                'shipping' => $data['shipping'],
                'payment_method' => $data['payment_method'],
                'payment_method_title' => $data['payment_method_title'],
                'line_items' => $data['line_items'],
            ];
        } catch (\Exception $e) {
            return 'Error creating order: '.$e->getMessage();
        }
    }

    /**
     * Get WooCommerce customers.
     *
     * @param int    $perPage Number of customers per page
     * @param int    $page    Page number
     * @param string $search  Search term
     * @param string $email   Customer email
     * @param string $role    Customer role
     * @param string $orderBy Order by field
     * @param string $order   Order direction
     *
     * @return array<int, array{
     *     id: int,
     *     date_created: string,
     *     date_created_gmt: string,
     *     date_modified: string,
     *     date_modified_gmt: string,
     *     email: string,
     *     first_name: string,
     *     last_name: string,
     *     role: string,
     *     username: string,
     *     billing: array<string, string>,
     *     shipping: array<string, string>,
     *     is_paying_customer: bool,
     *     avatar_url: string,
     *     meta_data: array<int, array{id: int, key: string, value: mixed}>,
     *     _links: array{self: array<int, array{href: string}>, collection: array<int, array{href: string}>},
     * }>
     */
    public function getCustomers(
        int $perPage = 10,
        int $page = 1,
        string $search = '',
        string $email = '',
        string $role = '',
        string $orderBy = 'registered_date',
        string $order = 'desc',
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
                'orderby' => $orderBy,
                'order' => $order,
            ];

            if ($search) {
                $params['search'] = $search;
            }
            if ($email) {
                $params['email'] = $email;
            }
            if ($role) {
                $params['role'] = $role;
            }

            $response = $this->httpClient->request('GET', $this->buildUrl('customers'), [
                'auth_basic' => [$this->consumerKey, $this->consumerSecret],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['code'])) {
                return [];
            }

            return array_map(fn ($customer) => [
                'id' => $customer['id'],
                'date_created' => $customer['date_created'],
                'date_created_gmt' => $customer['date_created_gmt'],
                'date_modified' => $customer['date_modified'],
                'date_modified_gmt' => $customer['date_modified_gmt'],
                'email' => $customer['email'],
                'first_name' => $customer['first_name'],
                'last_name' => $customer['last_name'],
                'role' => $customer['role'],
                'username' => $customer['username'],
                'billing' => $customer['billing'],
                'shipping' => $customer['shipping'],
                'is_paying_customer' => $customer['is_paying_customer'],
                'avatar_url' => $customer['avatar_url'],
                'meta_data' => array_map(fn ($meta) => [
                    'id' => $meta['id'],
                    'key' => $meta['key'],
                    'value' => $meta['value'],
                ], $customer['meta_data']),
                '_links' => $customer['_links'],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a WooCommerce customer.
     *
     * @param string $email     Customer email
     * @param string $firstName First name
     * @param string $lastName  Last name
     * @param string $username  Username
     * @param string $password  Password
     * @param array{
     *     first_name: string,
     *     last_name: string,
     *     company: string,
     *     address_1: string,
     *     address_2: string,
     *     city: string,
     *     state: string,
     *     postcode: string,
     *     country: string,
     *     email: string,
     *     phone: string,
     * } $billing Billing address
     * @param array{
     *     first_name: string,
     *     last_name: string,
     *     company: string,
     *     address_1: string,
     *     address_2: string,
     *     city: string,
     *     state: string,
     *     postcode: string,
     *     country: string,
     * } $shipping Shipping address
     *
     * @return array{
     *     id: int,
     *     date_created: string,
     *     date_created_gmt: string,
     *     date_modified: string,
     *     date_modified_gmt: string,
     *     email: string,
     *     first_name: string,
     *     last_name: string,
     *     role: string,
     *     username: string,
     *     billing: array<string, string>,
     *     shipping: array<string, string>,
     *     is_paying_customer: bool,
     *     avatar_url: string,
     * }|string
     */
    public function createCustomer(
        string $email,
        string $firstName,
        string $lastName,
        string $username,
        string $password,
        array $billing = [],
        array $shipping = [],
    ): array|string {
        try {
            $payload = [
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'username' => $username,
                'password' => $password,
                'billing' => $billing,
                'shipping' => $shipping,
            ];

            $response = $this->httpClient->request('POST', $this->buildUrl('customers'), [
                'auth_basic' => [$this->consumerKey, $this->consumerSecret],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['code'])) {
                return 'Error creating customer: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'date_created' => $data['date_created'],
                'date_created_gmt' => $data['date_created_gmt'],
                'date_modified' => $data['date_modified'],
                'date_modified_gmt' => $data['date_modified_gmt'],
                'email' => $data['email'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'role' => $data['role'],
                'username' => $data['username'],
                'billing' => $data['billing'],
                'shipping' => $data['shipping'],
                'is_paying_customer' => $data['is_paying_customer'],
                'avatar_url' => $data['avatar_url'],
            ];
        } catch (\Exception $e) {
            return 'Error creating customer: '.$e->getMessage();
        }
    }

    /**
     * Build API URL.
     */
    private function buildUrl(string $endpoint): string
    {
        $baseUrl = rtrim($this->storeUrl, '/');

        return "{$baseUrl}/wp-json/wc/{$this->apiVersion}/{$endpoint}";
    }
}

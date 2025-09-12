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
#[AsTool('multion_navigate', 'Tool that navigates web pages using Multion')]
#[AsTool('multion_search', 'Tool that searches on web pages', method: 'search')]
#[AsTool('multion_extract', 'Tool that extracts data from web pages', method: 'extract')]
#[AsTool('multion_interact', 'Tool that interacts with web elements', method: 'interact')]
#[AsTool('multion_screenshot', 'Tool that takes screenshots', method: 'screenshot')]
#[AsTool('multion_form_fill', 'Tool that fills forms', method: 'formFill')]
#[AsTool('multion_click', 'Tool that clicks elements', method: 'click')]
#[AsTool('multion_type', 'Tool that types text', method: 'type')]
final readonly class Multion
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.multion.com/v1',
        private array $options = [],
    ) {
    }

    /**
     * Navigate web pages using Multion.
     *
     * @param string               $url     URL to navigate to
     * @param array<string, mixed> $options Navigation options
     * @param array<string, mixed> $context Navigation context
     *
     * @return array{
     *     success: bool,
     *     navigation: array{
     *         url: string,
     *         final_url: string,
     *         title: string,
     *         status_code: int,
     *         load_time: float,
     *         page_content: array{
     *             html: string,
     *             text: string,
     *             links: array<int, array{
     *                 text: string,
     *                 url: string,
     *             }>,
     *             images: array<int, array{
     *                 src: string,
     *                 alt: string,
     *             }>,
     *             forms: array<int, array{
     *                 action: string,
     *                 method: string,
     *                 fields: array<int, array{
     *                     name: string,
     *                     type: string,
     *                     required: bool,
     *                 }>,
     *             }>,
     *         },
     *         metadata: array{
     *             viewport: array{
     *                 width: int,
     *                 height: int,
     *             },
     *             user_agent: string,
     *             cookies: array<int, array{
     *                 name: string,
     *                 value: string,
     *                 domain: string,
     *             }>,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $url,
        array $options = [],
        array $context = [],
    ): array {
        try {
            $requestData = [
                'url' => $url,
                'options' => $options,
                'context' => $context,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/navigate", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $navigation = $responseData['navigation'] ?? [];

            return [
                'success' => true,
                'navigation' => [
                    'url' => $url,
                    'final_url' => $navigation['final_url'] ?? $url,
                    'title' => $navigation['title'] ?? '',
                    'status_code' => $navigation['status_code'] ?? 200,
                    'load_time' => $navigation['load_time'] ?? 0.0,
                    'page_content' => [
                        'html' => $navigation['page_content']['html'] ?? '',
                        'text' => $navigation['page_content']['text'] ?? '',
                        'links' => array_map(fn ($link) => [
                            'text' => $link['text'] ?? '',
                            'url' => $link['url'] ?? '',
                        ], $navigation['page_content']['links'] ?? []),
                        'images' => array_map(fn ($image) => [
                            'src' => $image['src'] ?? '',
                            'alt' => $image['alt'] ?? '',
                        ], $navigation['page_content']['images'] ?? []),
                        'forms' => array_map(fn ($form) => [
                            'action' => $form['action'] ?? '',
                            'method' => $form['method'] ?? 'GET',
                            'fields' => array_map(fn ($field) => [
                                'name' => $field['name'] ?? '',
                                'type' => $field['type'] ?? 'text',
                                'required' => $field['required'] ?? false,
                            ], $form['fields'] ?? []),
                        ], $navigation['page_content']['forms'] ?? []),
                    ],
                    'metadata' => [
                        'viewport' => [
                            'width' => $navigation['metadata']['viewport']['width'] ?? 1920,
                            'height' => $navigation['metadata']['viewport']['height'] ?? 1080,
                        ],
                        'user_agent' => $navigation['metadata']['user_agent'] ?? '',
                        'cookies' => array_map(fn ($cookie) => [
                            'name' => $cookie['name'] ?? '',
                            'value' => $cookie['value'] ?? '',
                            'domain' => $cookie['domain'] ?? '',
                        ], $navigation['metadata']['cookies'] ?? []),
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'navigation' => [
                    'url' => $url,
                    'final_url' => $url,
                    'title' => '',
                    'status_code' => 0,
                    'load_time' => 0.0,
                    'page_content' => [
                        'html' => '',
                        'text' => '',
                        'links' => [],
                        'images' => [],
                        'forms' => [],
                    ],
                    'metadata' => [
                        'viewport' => [
                            'width' => 1920,
                            'height' => 1080,
                        ],
                        'user_agent' => '',
                        'cookies' => [],
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search on web pages.
     *
     * @param string               $query        Search query
     * @param string               $searchEngine Search engine to use
     * @param array<string, mixed> $options      Search options
     *
     * @return array{
     *     success: bool,
     *     search_results: array{
     *         query: string,
     *         search_engine: string,
     *         results: array<int, array{
     *             title: string,
     *             url: string,
     *             description: string,
     *             position: int,
     *             domain: string,
     *         }>,
     *         total_results: int,
     *         search_time: float,
     *         related_searches: array<int, string>,
     *         filters: array<string, array<int, string>>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function search(
        string $query,
        string $searchEngine = 'google',
        array $options = [],
    ): array {
        try {
            $requestData = [
                'query' => $query,
                'search_engine' => $searchEngine,
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/search", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $searchResults = $responseData['search_results'] ?? [];

            return [
                'success' => true,
                'search_results' => [
                    'query' => $query,
                    'search_engine' => $searchEngine,
                    'results' => array_map(fn ($result, $index) => [
                        'title' => $result['title'] ?? '',
                        'url' => $result['url'] ?? '',
                        'description' => $result['description'] ?? '',
                        'position' => $result['position'] ?? $index + 1,
                        'domain' => parse_url($result['url'] ?? '', \PHP_URL_HOST) ?: '',
                    ], $searchResults['results'] ?? []),
                    'total_results' => $searchResults['total_results'] ?? 0,
                    'search_time' => $searchResults['search_time'] ?? 0.0,
                    'related_searches' => $searchResults['related_searches'] ?? [],
                    'filters' => $searchResults['filters'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'search_results' => [
                    'query' => $query,
                    'search_engine' => $searchEngine,
                    'results' => [],
                    'total_results' => 0,
                    'search_time' => 0.0,
                    'related_searches' => [],
                    'filters' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract data from web pages.
     *
     * @param string               $url             URL to extract data from
     * @param array<string, mixed> $extractionRules Extraction rules
     * @param array<string, mixed> $options         Extraction options
     *
     * @return array{
     *     success: bool,
     *     extraction: array{
     *         url: string,
     *         extracted_data: array<string, mixed>,
     *         extraction_rules: array<string, mixed>,
     *         elements_found: array<int, array{
     *             selector: string,
     *             value: string,
     *             confidence: float,
     *         }>,
     *         metadata: array{
     *             extraction_time: float,
     *             page_size: int,
     *             elements_count: int,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function extract(
        string $url,
        array $extractionRules = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'url' => $url,
                'extraction_rules' => $extractionRules,
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/extract", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $extraction = $responseData['extraction'] ?? [];

            return [
                'success' => true,
                'extraction' => [
                    'url' => $url,
                    'extracted_data' => $extraction['extracted_data'] ?? [],
                    'extraction_rules' => $extractionRules,
                    'elements_found' => array_map(fn ($element) => [
                        'selector' => $element['selector'] ?? '',
                        'value' => $element['value'] ?? '',
                        'confidence' => $element['confidence'] ?? 0.0,
                    ], $extraction['elements_found'] ?? []),
                    'metadata' => [
                        'extraction_time' => $extraction['metadata']['extraction_time'] ?? 0.0,
                        'page_size' => $extraction['metadata']['page_size'] ?? 0,
                        'elements_count' => $extraction['metadata']['elements_count'] ?? 0,
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'extraction' => [
                    'url' => $url,
                    'extracted_data' => [],
                    'extraction_rules' => $extractionRules,
                    'elements_found' => [],
                    'metadata' => [
                        'extraction_time' => 0.0,
                        'page_size' => 0,
                        'elements_count' => 0,
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Interact with web elements.
     *
     * @param string               $action  Action to perform
     * @param array<string, mixed> $element Element selector or identifier
     * @param array<string, mixed> $data    Action data
     *
     * @return array{
     *     success: bool,
     *     interaction: array{
     *         action: string,
     *         element: array<string, mixed>,
     *         data: array<string, mixed>,
     *         result: array{
     *             success: bool,
     *             message: string,
     *             page_changed: bool,
     *             new_url: string,
     *         },
     *         screenshot_url: string,
     *         execution_time: float,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function interact(
        string $action,
        array $element,
        array $data = [],
    ): array {
        try {
            $requestData = [
                'action' => $action,
                'element' => $element,
                'data' => $data,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/interact", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $interaction = $responseData['interaction'] ?? [];

            return [
                'success' => true,
                'interaction' => [
                    'action' => $action,
                    'element' => $element,
                    'data' => $data,
                    'result' => [
                        'success' => $interaction['result']['success'] ?? false,
                        'message' => $interaction['result']['message'] ?? '',
                        'page_changed' => $interaction['result']['page_changed'] ?? false,
                        'new_url' => $interaction['result']['new_url'] ?? '',
                    ],
                    'screenshot_url' => $interaction['screenshot_url'] ?? '',
                    'execution_time' => $interaction['execution_time'] ?? 0.0,
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'interaction' => [
                    'action' => $action,
                    'element' => $element,
                    'data' => $data,
                    'result' => [
                        'success' => false,
                        'message' => '',
                        'page_changed' => false,
                        'new_url' => '',
                    ],
                    'screenshot_url' => '',
                    'execution_time' => 0.0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Take screenshots.
     *
     * @param string               $url     URL to screenshot (optional)
     * @param array<string, mixed> $options Screenshot options
     *
     * @return array{
     *     success: bool,
     *     screenshot: array{
     *         url: string,
     *         screenshot_url: string,
     *         dimensions: array{
     *             width: int,
     *             height: int,
     *         },
     *         format: string,
     *         size_bytes: int,
     *         metadata: array{
     *             timestamp: string,
     *             viewport: array{
     *                 width: int,
     *                 height: int,
     *             },
     *             full_page: bool,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function screenshot(
        string $url = '',
        array $options = [],
    ): array {
        try {
            $requestData = [
                'url' => $url,
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/screenshot", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $screenshot = $responseData['screenshot'] ?? [];

            return [
                'success' => true,
                'screenshot' => [
                    'url' => $url,
                    'screenshot_url' => $screenshot['screenshot_url'] ?? '',
                    'dimensions' => [
                        'width' => $screenshot['dimensions']['width'] ?? 1920,
                        'height' => $screenshot['dimensions']['height'] ?? 1080,
                    ],
                    'format' => $screenshot['format'] ?? 'png',
                    'size_bytes' => $screenshot['size_bytes'] ?? 0,
                    'metadata' => [
                        'timestamp' => $screenshot['metadata']['timestamp'] ?? date('c'),
                        'viewport' => [
                            'width' => $screenshot['metadata']['viewport']['width'] ?? 1920,
                            'height' => $screenshot['metadata']['viewport']['height'] ?? 1080,
                        ],
                        'full_page' => $screenshot['metadata']['full_page'] ?? false,
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'screenshot' => [
                    'url' => $url,
                    'screenshot_url' => '',
                    'dimensions' => [
                        'width' => 1920,
                        'height' => 1080,
                    ],
                    'format' => 'png',
                    'size_bytes' => 0,
                    'metadata' => [
                        'timestamp' => date('c'),
                        'viewport' => [
                            'width' => 1920,
                            'height' => 1080,
                        ],
                        'full_page' => false,
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fill forms.
     *
     * @param array<string, mixed> $formData     Form data to fill
     * @param string               $formSelector Form selector
     * @param array<string, mixed> $options      Form filling options
     *
     * @return array{
     *     success: bool,
     *     form_filling: array{
     *         form_selector: string,
     *         form_data: array<string, mixed>,
     *         fields_filled: array<int, array{
     *             field_name: string,
     *             value: string,
     *             success: bool,
     *         }>,
     *         result: array{
     *             success: bool,
     *             message: string,
     *             form_submitted: bool,
     *             redirect_url: string,
     *         },
     *         screenshot_url: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function formFill(
        array $formData,
        string $formSelector = '',
        array $options = [],
    ): array {
        try {
            $requestData = [
                'form_data' => $formData,
                'form_selector' => $formSelector,
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/form-fill", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $formFilling = $responseData['form_filling'] ?? [];

            return [
                'success' => true,
                'form_filling' => [
                    'form_selector' => $formSelector,
                    'form_data' => $formData,
                    'fields_filled' => array_map(fn ($field) => [
                        'field_name' => $field['field_name'] ?? '',
                        'value' => $field['value'] ?? '',
                        'success' => $field['success'] ?? false,
                    ], $formFilling['fields_filled'] ?? []),
                    'result' => [
                        'success' => $formFilling['result']['success'] ?? false,
                        'message' => $formFilling['result']['message'] ?? '',
                        'form_submitted' => $formFilling['result']['form_submitted'] ?? false,
                        'redirect_url' => $formFilling['result']['redirect_url'] ?? '',
                    ],
                    'screenshot_url' => $formFilling['screenshot_url'] ?? '',
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'form_filling' => [
                    'form_selector' => $formSelector,
                    'form_data' => $formData,
                    'fields_filled' => [],
                    'result' => [
                        'success' => false,
                        'message' => '',
                        'form_submitted' => false,
                        'redirect_url' => '',
                    ],
                    'screenshot_url' => '',
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Click elements.
     *
     * @param array<string, mixed> $element Element to click
     * @param array<string, mixed> $options Click options
     *
     * @return array{
     *     success: bool,
     *     click: array{
     *         element: array<string, mixed>,
     *         result: array{
     *             success: bool,
     *             message: string,
     *             page_changed: bool,
     *             new_url: string,
     *         },
     *         screenshot_url: string,
     *         execution_time: float,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function click(
        array $element,
        array $options = [],
    ): array {
        try {
            $requestData = [
                'element' => $element,
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/click", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $click = $responseData['click'] ?? [];

            return [
                'success' => true,
                'click' => [
                    'element' => $element,
                    'result' => [
                        'success' => $click['result']['success'] ?? false,
                        'message' => $click['result']['message'] ?? '',
                        'page_changed' => $click['result']['page_changed'] ?? false,
                        'new_url' => $click['result']['new_url'] ?? '',
                    ],
                    'screenshot_url' => $click['screenshot_url'] ?? '',
                    'execution_time' => $click['execution_time'] ?? 0.0,
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'click' => [
                    'element' => $element,
                    'result' => [
                        'success' => false,
                        'message' => '',
                        'page_changed' => false,
                        'new_url' => '',
                    ],
                    'screenshot_url' => '',
                    'execution_time' => 0.0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Type text.
     *
     * @param string               $text    Text to type
     * @param array<string, mixed> $element Target element
     * @param array<string, mixed> $options Typing options
     *
     * @return array{
     *     success: bool,
     *     typing: array{
     *         text: string,
     *         element: array<string, mixed>,
     *         result: array{
     *             success: bool,
     *             message: string,
     *             characters_typed: int,
     *         },
     *         screenshot_url: string,
     *         execution_time: float,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function type(
        string $text,
        array $element,
        array $options = [],
    ): array {
        try {
            $requestData = [
                'text' => $text,
                'element' => $element,
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/type", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $typing = $responseData['typing'] ?? [];

            return [
                'success' => true,
                'typing' => [
                    'text' => $text,
                    'element' => $element,
                    'result' => [
                        'success' => $typing['result']['success'] ?? false,
                        'message' => $typing['result']['message'] ?? '',
                        'characters_typed' => $typing['result']['characters_typed'] ?? 0,
                    ],
                    'screenshot_url' => $typing['screenshot_url'] ?? '',
                    'execution_time' => $typing['execution_time'] ?? 0.0,
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'typing' => [
                    'text' => $text,
                    'element' => $element,
                    'result' => [
                        'success' => false,
                        'message' => '',
                        'characters_typed' => 0,
                    ],
                    'screenshot_url' => '',
                    'execution_time' => 0.0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}

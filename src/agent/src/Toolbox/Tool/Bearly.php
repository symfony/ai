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
#[AsTool('bearly_analyze_url', 'Tool that analyzes URLs using Bearly AI')]
#[AsTool('bearly_analyze_text', 'Tool that analyzes text content', method: 'analyzeText')]
#[AsTool('bearly_analyze_image', 'Tool that analyzes images', method: 'analyzeImage')]
#[AsTool('bearly_analyze_document', 'Tool that analyzes documents', method: 'analyzeDocument')]
#[AsTool('bearly_summarize_content', 'Tool that summarizes content', method: 'summarizeContent')]
#[AsTool('bearly_extract_keywords', 'Tool that extracts keywords', method: 'extractKeywords')]
#[AsTool('bearly_get_insights', 'Tool that gets content insights', method: 'getInsights')]
#[AsTool('bearly_translate_content', 'Tool that translates content', method: 'translateContent')]
final readonly class Bearly
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.bearly.ai/v1',
        private array $options = [],
    ) {
    }

    /**
     * Analyze URL using Bearly AI.
     *
     * @param string               $url           URL to analyze
     * @param array<string, mixed> $analysisTypes Types of analysis to perform
     * @param string               $language      Language for analysis
     * @param bool                 $includeImages Include image analysis
     * @param bool                 $includeLinks  Include link analysis
     *
     * @return array{
     *     success: bool,
     *     analysis: array{
     *         url: string,
     *         title: string,
     *         description: string,
     *         content: string,
     *         wordCount: int,
     *         readingTime: int,
     *         language: string,
     *         sentiment: array{
     *             score: float,
     *             label: string,
     *             confidence: float,
     *         },
     *         topics: array<int, string>,
     *         keywords: array<int, string>,
     *         entities: array<int, array{
     *             text: string,
     *             type: string,
     *             confidence: float,
     *         }>,
     *         summary: string,
     *         keyPoints: array<int, string>,
     *         images: array<int, array{
     *             url: string,
     *             alt: string,
     *             caption: string,
     *         }>,
     *         links: array<int, array{
     *             url: string,
     *             text: string,
     *             type: string,
     *         }>,
     *         metadata: array<string, mixed>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $url,
        array $analysisTypes = ['content', 'sentiment', 'topics', 'entities', 'summary'],
        string $language = 'en',
        bool $includeImages = true,
        bool $includeLinks = true,
    ): array {
        try {
            $requestData = [
                'url' => $url,
                'analysis_types' => $analysisTypes,
                'language' => $language,
                'include_images' => $includeImages,
                'include_links' => $includeLinks,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/analyze/url", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $analysis = $data['analysis'] ?? [];

            return [
                'success' => true,
                'analysis' => [
                    'url' => $analysis['url'] ?? $url,
                    'title' => $analysis['title'] ?? '',
                    'description' => $analysis['description'] ?? '',
                    'content' => $analysis['content'] ?? '',
                    'wordCount' => $analysis['word_count'] ?? 0,
                    'readingTime' => $analysis['reading_time'] ?? 0,
                    'language' => $analysis['language'] ?? $language,
                    'sentiment' => [
                        'score' => $analysis['sentiment']['score'] ?? 0.0,
                        'label' => $analysis['sentiment']['label'] ?? 'neutral',
                        'confidence' => $analysis['sentiment']['confidence'] ?? 0.0,
                    ],
                    'topics' => $analysis['topics'] ?? [],
                    'keywords' => $analysis['keywords'] ?? [],
                    'entities' => array_map(fn ($entity) => [
                        'text' => $entity['text'] ?? '',
                        'type' => $entity['type'] ?? '',
                        'confidence' => $entity['confidence'] ?? 0.0,
                    ], $analysis['entities'] ?? []),
                    'summary' => $analysis['summary'] ?? '',
                    'keyPoints' => $analysis['key_points'] ?? [],
                    'images' => array_map(fn ($image) => [
                        'url' => $image['url'] ?? '',
                        'alt' => $image['alt'] ?? '',
                        'caption' => $image['caption'] ?? '',
                    ], $analysis['images'] ?? []),
                    'links' => array_map(fn ($link) => [
                        'url' => $link['url'] ?? '',
                        'text' => $link['text'] ?? '',
                        'type' => $link['type'] ?? '',
                    ], $analysis['links'] ?? []),
                    'metadata' => $analysis['metadata'] ?? [],
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'analysis' => [
                    'url' => $url,
                    'title' => '',
                    'description' => '',
                    'content' => '',
                    'wordCount' => 0,
                    'readingTime' => 0,
                    'language' => $language,
                    'sentiment' => ['score' => 0.0, 'label' => 'neutral', 'confidence' => 0.0],
                    'topics' => [],
                    'keywords' => [],
                    'entities' => [],
                    'summary' => '',
                    'keyPoints' => [],
                    'images' => [],
                    'links' => [],
                    'metadata' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze text content.
     *
     * @param string               $text          Text content to analyze
     * @param array<string, mixed> $analysisTypes Types of analysis to perform
     * @param string               $language      Language for analysis
     *
     * @return array{
     *     success: bool,
     *     analysis: array{
     *         text: string,
     *         wordCount: int,
     *         characterCount: int,
     *         language: string,
     *         sentiment: array{
     *             score: float,
     *             label: string,
     *             confidence: float,
     *         },
     *         topics: array<int, string>,
     *         keywords: array<int, string>,
     *         entities: array<int, array{
     *             text: string,
     *             type: string,
     *             confidence: float,
     *         }>,
     *         summary: string,
     *         keyPoints: array<int, string>,
     *         readability: array{
     *             score: float,
     *             level: string,
     *         },
     *         emotions: array<string, float>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function analyzeText(
        string $text,
        array $analysisTypes = ['sentiment', 'topics', 'entities', 'summary', 'readability'],
        string $language = 'en',
    ): array {
        try {
            $requestData = [
                'text' => $text,
                'analysis_types' => $analysisTypes,
                'language' => $language,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/analyze/text", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $analysis = $data['analysis'] ?? [];

            return [
                'success' => true,
                'analysis' => [
                    'text' => $text,
                    'wordCount' => $analysis['word_count'] ?? str_word_count($text),
                    'characterCount' => $analysis['character_count'] ?? \strlen($text),
                    'language' => $analysis['language'] ?? $language,
                    'sentiment' => [
                        'score' => $analysis['sentiment']['score'] ?? 0.0,
                        'label' => $analysis['sentiment']['label'] ?? 'neutral',
                        'confidence' => $analysis['sentiment']['confidence'] ?? 0.0,
                    ],
                    'topics' => $analysis['topics'] ?? [],
                    'keywords' => $analysis['keywords'] ?? [],
                    'entities' => array_map(fn ($entity) => [
                        'text' => $entity['text'] ?? '',
                        'type' => $entity['type'] ?? '',
                        'confidence' => $entity['confidence'] ?? 0.0,
                    ], $analysis['entities'] ?? []),
                    'summary' => $analysis['summary'] ?? '',
                    'keyPoints' => $analysis['key_points'] ?? [],
                    'readability' => [
                        'score' => $analysis['readability']['score'] ?? 0.0,
                        'level' => $analysis['readability']['level'] ?? 'intermediate',
                    ],
                    'emotions' => $analysis['emotions'] ?? [],
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'analysis' => [
                    'text' => $text,
                    'wordCount' => str_word_count($text),
                    'characterCount' => \strlen($text),
                    'language' => $language,
                    'sentiment' => ['score' => 0.0, 'label' => 'neutral', 'confidence' => 0.0],
                    'topics' => [],
                    'keywords' => [],
                    'entities' => [],
                    'summary' => '',
                    'keyPoints' => [],
                    'readability' => ['score' => 0.0, 'level' => 'intermediate'],
                    'emotions' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze images.
     *
     * @param string               $imageUrl      URL of image to analyze
     * @param array<string, mixed> $analysisTypes Types of analysis to perform
     * @param string               $language      Language for analysis
     *
     * @return array{
     *     success: bool,
     *     analysis: array{
     *         imageUrl: string,
     *         width: int,
     *         height: int,
     *         format: string,
     *         fileSize: int,
     *         objects: array<int, array{
     *             name: string,
     *             confidence: float,
     *             boundingBox: array{
     *                 x: float,
     *                 y: float,
     *                 width: float,
     *                 height: float,
     *             },
     *         }>,
     *         text: array<int, array{
     *             text: string,
     *             confidence: float,
     *             boundingBox: array{
     *                 x: float,
     *                 y: float,
     *                 width: float,
     *                 height: float,
     *             },
     *         }>,
     *         faces: array<int, array{
     *             confidence: float,
     *             emotions: array<string, float>,
     *             age: int,
     *             gender: string,
     *             boundingBox: array{
     *                 x: float,
     *                 y: float,
     *                 width: float,
     *                 height: float,
     *             },
     *         }>,
     *         colors: array<int, array{
     *             color: string,
     *             hex: string,
     *             percentage: float,
     *         }>,
     *         labels: array<int, array{
     *             name: string,
     *             confidence: float,
     *         }>,
     *         description: string,
     *         tags: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function analyzeImage(
        string $imageUrl,
        array $analysisTypes = ['objects', 'text', 'faces', 'colors', 'labels', 'description'],
        string $language = 'en',
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'analysis_types' => $analysisTypes,
                'language' => $language,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/analyze/image", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $analysis = $data['analysis'] ?? [];

            return [
                'success' => true,
                'analysis' => [
                    'imageUrl' => $imageUrl,
                    'width' => $analysis['width'] ?? 0,
                    'height' => $analysis['height'] ?? 0,
                    'format' => $analysis['format'] ?? '',
                    'fileSize' => $analysis['file_size'] ?? 0,
                    'objects' => array_map(fn ($object) => [
                        'name' => $object['name'] ?? '',
                        'confidence' => $object['confidence'] ?? 0.0,
                        'boundingBox' => [
                            'x' => $object['bounding_box']['x'] ?? 0.0,
                            'y' => $object['bounding_box']['y'] ?? 0.0,
                            'width' => $object['bounding_box']['width'] ?? 0.0,
                            'height' => $object['bounding_box']['height'] ?? 0.0,
                        ],
                    ], $analysis['objects'] ?? []),
                    'text' => array_map(fn ($text) => [
                        'text' => $text['text'] ?? '',
                        'confidence' => $text['confidence'] ?? 0.0,
                        'boundingBox' => [
                            'x' => $text['bounding_box']['x'] ?? 0.0,
                            'y' => $text['bounding_box']['y'] ?? 0.0,
                            'width' => $text['bounding_box']['width'] ?? 0.0,
                            'height' => $text['bounding_box']['height'] ?? 0.0,
                        ],
                    ], $analysis['text'] ?? []),
                    'faces' => array_map(fn ($face) => [
                        'confidence' => $face['confidence'] ?? 0.0,
                        'emotions' => $face['emotions'] ?? [],
                        'age' => $face['age'] ?? 0,
                        'gender' => $face['gender'] ?? '',
                        'boundingBox' => [
                            'x' => $face['bounding_box']['x'] ?? 0.0,
                            'y' => $face['bounding_box']['y'] ?? 0.0,
                            'width' => $face['bounding_box']['width'] ?? 0.0,
                            'height' => $face['bounding_box']['height'] ?? 0.0,
                        ],
                    ], $analysis['faces'] ?? []),
                    'colors' => array_map(fn ($color) => [
                        'color' => $color['color'] ?? '',
                        'hex' => $color['hex'] ?? '',
                        'percentage' => $color['percentage'] ?? 0.0,
                    ], $analysis['colors'] ?? []),
                    'labels' => array_map(fn ($label) => [
                        'name' => $label['name'] ?? '',
                        'confidence' => $label['confidence'] ?? 0.0,
                    ], $analysis['labels'] ?? []),
                    'description' => $analysis['description'] ?? '',
                    'tags' => $analysis['tags'] ?? [],
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'analysis' => [
                    'imageUrl' => $imageUrl,
                    'width' => 0,
                    'height' => 0,
                    'format' => '',
                    'fileSize' => 0,
                    'objects' => [],
                    'text' => [],
                    'faces' => [],
                    'colors' => [],
                    'labels' => [],
                    'description' => '',
                    'tags' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze documents.
     *
     * @param string               $documentUrl   URL of document to analyze
     * @param string               $documentType  Document type (pdf, docx, txt, etc.)
     * @param array<string, mixed> $analysisTypes Types of analysis to perform
     * @param string               $language      Language for analysis
     *
     * @return array{
     *     success: bool,
     *     analysis: array{
     *         documentUrl: string,
     *         documentType: string,
     *         title: string,
     *         content: string,
     *         wordCount: int,
     *         pageCount: int,
     *         language: string,
     *         sentiment: array{
     *             score: float,
     *             label: string,
     *             confidence: float,
     *         },
     *         topics: array<int, string>,
     *         keywords: array<int, string>,
     *         entities: array<int, array{
     *             text: string,
     *             type: string,
     *             confidence: float,
     *         }>,
     *         summary: string,
     *         keyPoints: array<int, string>,
     *         structure: array{
     *             headings: array<int, array{
     *                 level: int,
     *                 text: string,
     *                 page: int,
     *             }>,
     *             paragraphs: int,
     *             tables: int,
     *             images: int,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function analyzeDocument(
        string $documentUrl,
        string $documentType = 'pdf',
        array $analysisTypes = ['content', 'sentiment', 'topics', 'entities', 'summary', 'structure'],
        string $language = 'en',
    ): array {
        try {
            $requestData = [
                'document_url' => $documentUrl,
                'document_type' => $documentType,
                'analysis_types' => $analysisTypes,
                'language' => $language,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/analyze/document", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $analysis = $data['analysis'] ?? [];

            return [
                'success' => true,
                'analysis' => [
                    'documentUrl' => $documentUrl,
                    'documentType' => $documentType,
                    'title' => $analysis['title'] ?? '',
                    'content' => $analysis['content'] ?? '',
                    'wordCount' => $analysis['word_count'] ?? 0,
                    'pageCount' => $analysis['page_count'] ?? 0,
                    'language' => $analysis['language'] ?? $language,
                    'sentiment' => [
                        'score' => $analysis['sentiment']['score'] ?? 0.0,
                        'label' => $analysis['sentiment']['label'] ?? 'neutral',
                        'confidence' => $analysis['sentiment']['confidence'] ?? 0.0,
                    ],
                    'topics' => $analysis['topics'] ?? [],
                    'keywords' => $analysis['keywords'] ?? [],
                    'entities' => array_map(fn ($entity) => [
                        'text' => $entity['text'] ?? '',
                        'type' => $entity['type'] ?? '',
                        'confidence' => $entity['confidence'] ?? 0.0,
                    ], $analysis['entities'] ?? []),
                    'summary' => $analysis['summary'] ?? '',
                    'keyPoints' => $analysis['key_points'] ?? [],
                    'structure' => [
                        'headings' => array_map(fn ($heading) => [
                            'level' => $heading['level'] ?? 1,
                            'text' => $heading['text'] ?? '',
                            'page' => $heading['page'] ?? 1,
                        ], $analysis['structure']['headings'] ?? []),
                        'paragraphs' => $analysis['structure']['paragraphs'] ?? 0,
                        'tables' => $analysis['structure']['tables'] ?? 0,
                        'images' => $analysis['structure']['images'] ?? 0,
                    ],
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'analysis' => [
                    'documentUrl' => $documentUrl,
                    'documentType' => $documentType,
                    'title' => '',
                    'content' => '',
                    'wordCount' => 0,
                    'pageCount' => 0,
                    'language' => $language,
                    'sentiment' => ['score' => 0.0, 'label' => 'neutral', 'confidence' => 0.0],
                    'topics' => [],
                    'keywords' => [],
                    'entities' => [],
                    'summary' => '',
                    'keyPoints' => [],
                    'structure' => [
                        'headings' => [],
                        'paragraphs' => 0,
                        'tables' => 0,
                        'images' => 0,
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Summarize content.
     *
     * @param string $content     Content to summarize
     * @param string $contentType Type of content (url, text, document)
     * @param int    $maxLength   Maximum summary length
     * @param string $style       Summary style (brief, detailed, bulleted)
     * @param string $language    Language for summary
     *
     * @return array{
     *     success: bool,
     *     summary: array{
     *         text: string,
     *         length: int,
     *         style: string,
     *         keyPoints: array<int, string>,
     *         keywords: array<int, string>,
     *         topics: array<int, string>,
     *         confidence: float,
     *         originalLength: int,
     *         compressionRatio: float,
     *     },
     *     contentType: string,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function summarizeContent(
        string $content,
        string $contentType = 'text',
        int $maxLength = 200,
        string $style = 'brief',
        string $language = 'en',
    ): array {
        try {
            $requestData = [
                'content' => $content,
                'content_type' => $contentType,
                'max_length' => max(50, min($maxLength, 1000)),
                'style' => $style,
                'language' => $language,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/summarize", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $summary = $data['summary'] ?? [];
            $originalLength = \strlen($content);

            return [
                'success' => true,
                'summary' => [
                    'text' => $summary['text'] ?? '',
                    'length' => $summary['length'] ?? 0,
                    'style' => $summary['style'] ?? $style,
                    'keyPoints' => $summary['key_points'] ?? [],
                    'keywords' => $summary['keywords'] ?? [],
                    'topics' => $summary['topics'] ?? [],
                    'confidence' => $summary['confidence'] ?? 0.0,
                    'originalLength' => $originalLength,
                    'compressionRatio' => $originalLength > 0 ? ($summary['length'] ?? 0) / $originalLength : 0.0,
                ],
                'contentType' => $contentType,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'summary' => [
                    'text' => '',
                    'length' => 0,
                    'style' => $style,
                    'keyPoints' => [],
                    'keywords' => [],
                    'topics' => [],
                    'confidence' => 0.0,
                    'originalLength' => \strlen($content),
                    'compressionRatio' => 0.0,
                ],
                'contentType' => $contentType,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract keywords.
     *
     * @param string $content     Content to extract keywords from
     * @param string $contentType Type of content (url, text, document)
     * @param int    $maxKeywords Maximum number of keywords
     * @param string $language    Language for extraction
     *
     * @return array{
     *     success: bool,
     *     keywords: array<int, array{
     *         keyword: string,
     *         relevance: float,
     *         frequency: int,
     *         category: string,
     *     }>,
     *     totalKeywords: int,
     *     contentType: string,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function extractKeywords(
        string $content,
        string $contentType = 'text',
        int $maxKeywords = 20,
        string $language = 'en',
    ): array {
        try {
            $requestData = [
                'content' => $content,
                'content_type' => $contentType,
                'max_keywords' => max(1, min($maxKeywords, 100)),
                'language' => $language,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/extract/keywords", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $keywords = $data['keywords'] ?? [];

            return [
                'success' => true,
                'keywords' => array_map(fn ($keyword) => [
                    'keyword' => $keyword['keyword'] ?? '',
                    'relevance' => $keyword['relevance'] ?? 0.0,
                    'frequency' => $keyword['frequency'] ?? 0,
                    'category' => $keyword['category'] ?? '',
                ], $keywords),
                'totalKeywords' => \count($keywords),
                'contentType' => $contentType,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'keywords' => [],
                'totalKeywords' => 0,
                'contentType' => $contentType,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get content insights.
     *
     * @param string $content     Content to analyze
     * @param string $contentType Type of content (url, text, document)
     * @param string $language    Language for analysis
     *
     * @return array{
     *     success: bool,
     *     insights: array{
     *         readability: array{
     *             score: float,
     *             level: string,
     *             gradeLevel: int,
     *         },
     *         complexity: array{
     *             score: float,
     *             level: string,
     *             factors: array<int, string>,
     *         },
     *         tone: array{
     *             score: float,
     *             label: string,
     *             confidence: float,
     *         },
     *         intent: array{
     *             primary: string,
     *             secondary: array<int, string>,
     *             confidence: float,
     *         },
     *         audience: array{
     *             level: string,
     *             demographics: array<string, mixed>,
     *             interests: array<int, string>,
     *         },
     *         engagement: array{
     *             score: float,
     *             factors: array<int, string>,
     *             recommendations: array<int, string>,
     *         },
     *     },
     *     contentType: string,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function getInsights(
        string $content,
        string $contentType = 'text',
        string $language = 'en',
    ): array {
        try {
            $requestData = [
                'content' => $content,
                'content_type' => $contentType,
                'language' => $language,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/insights", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $insights = $data['insights'] ?? [];

            return [
                'success' => true,
                'insights' => [
                    'readability' => [
                        'score' => $insights['readability']['score'] ?? 0.0,
                        'level' => $insights['readability']['level'] ?? 'intermediate',
                        'gradeLevel' => $insights['readability']['grade_level'] ?? 8,
                    ],
                    'complexity' => [
                        'score' => $insights['complexity']['score'] ?? 0.0,
                        'level' => $insights['complexity']['level'] ?? 'medium',
                        'factors' => $insights['complexity']['factors'] ?? [],
                    ],
                    'tone' => [
                        'score' => $insights['tone']['score'] ?? 0.0,
                        'label' => $insights['tone']['label'] ?? 'neutral',
                        'confidence' => $insights['tone']['confidence'] ?? 0.0,
                    ],
                    'intent' => [
                        'primary' => $insights['intent']['primary'] ?? '',
                        'secondary' => $insights['intent']['secondary'] ?? [],
                        'confidence' => $insights['intent']['confidence'] ?? 0.0,
                    ],
                    'audience' => [
                        'level' => $insights['audience']['level'] ?? 'general',
                        'demographics' => $insights['audience']['demographics'] ?? [],
                        'interests' => $insights['audience']['interests'] ?? [],
                    ],
                    'engagement' => [
                        'score' => $insights['engagement']['score'] ?? 0.0,
                        'factors' => $insights['engagement']['factors'] ?? [],
                        'recommendations' => $insights['engagement']['recommendations'] ?? [],
                    ],
                ],
                'contentType' => $contentType,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'insights' => [
                    'readability' => ['score' => 0.0, 'level' => 'intermediate', 'gradeLevel' => 8],
                    'complexity' => ['score' => 0.0, 'level' => 'medium', 'factors' => []],
                    'tone' => ['score' => 0.0, 'label' => 'neutral', 'confidence' => 0.0],
                    'intent' => ['primary' => '', 'secondary' => [], 'confidence' => 0.0],
                    'audience' => ['level' => 'general', 'demographics' => [], 'interests' => []],
                    'engagement' => ['score' => 0.0, 'factors' => [], 'recommendations' => []],
                ],
                'contentType' => $contentType,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Translate content.
     *
     * @param string $content        Content to translate
     * @param string $targetLanguage Target language code
     * @param string $sourceLanguage Source language code (auto-detect if empty)
     * @param string $contentType    Type of content (text, url, document)
     *
     * @return array{
     *     success: bool,
     *     translation: array{
     *         originalText: string,
     *         translatedText: string,
     *         sourceLanguage: string,
     *         targetLanguage: string,
     *         confidence: float,
     *         wordCount: int,
     *         characterCount: int,
     *     },
     *     contentType: string,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function translateContent(
        string $content,
        string $targetLanguage,
        string $sourceLanguage = '',
        string $contentType = 'text',
    ): array {
        try {
            $requestData = [
                'content' => $content,
                'target_language' => $targetLanguage,
                'content_type' => $contentType,
            ];

            if ($sourceLanguage) {
                $requestData['source_language'] = $sourceLanguage;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/translate", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $translation = $data['translation'] ?? [];

            return [
                'success' => true,
                'translation' => [
                    'originalText' => $content,
                    'translatedText' => $translation['translated_text'] ?? '',
                    'sourceLanguage' => $translation['source_language'] ?? $sourceLanguage,
                    'targetLanguage' => $targetLanguage,
                    'confidence' => $translation['confidence'] ?? 0.0,
                    'wordCount' => $translation['word_count'] ?? 0,
                    'characterCount' => $translation['character_count'] ?? 0,
                ],
                'contentType' => $contentType,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'translation' => [
                    'originalText' => $content,
                    'translatedText' => '',
                    'sourceLanguage' => $sourceLanguage,
                    'targetLanguage' => $targetLanguage,
                    'confidence' => 0.0,
                    'wordCount' => 0,
                    'characterCount' => 0,
                ],
                'contentType' => $contentType,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}

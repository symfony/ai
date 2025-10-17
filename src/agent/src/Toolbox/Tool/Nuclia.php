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
#[AsTool('nuclia_upload', 'Tool that uploads documents to Nuclia knowledge base')]
#[AsTool('nuclia_search', 'Tool that searches Nuclia knowledge base', method: 'search')]
#[AsTool('nuclia_get_resource', 'Tool that retrieves resources from Nuclia', method: 'getResource')]
#[AsTool('nuclia_create_knowledge_box', 'Tool that creates knowledge boxes', method: 'createKnowledgeBox')]
#[AsTool('nuclia_list_knowledge_boxes', 'Tool that lists knowledge boxes', method: 'listKnowledgeBoxes')]
#[AsTool('nuclia_delete_resource', 'Tool that deletes resources', method: 'deleteResource')]
#[AsTool('nuclia_extract_text', 'Tool that extracts text from documents', method: 'extractText')]
#[AsTool('nuclia_generate_answer', 'Tool that generates answers from knowledge base', method: 'generateAnswer')]
final readonly class Nuclia
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://nuclia.cloud/api/v1',
        private array $options = [],
    ) {
    }

    /**
     * Upload documents to Nuclia knowledge base.
     *
     * @param string               $knowledgeBoxId Knowledge box ID
     * @param string               $filePath       Path to file to upload
     * @param string               $title          Document title
     * @param string               $summary        Document summary
     * @param array<string, mixed> $metadata       Additional metadata
     *
     * @return array{
     *     success: bool,
     *     upload: array{
     *         knowledge_box_id: string,
     *         resource_id: string,
     *         title: string,
     *         summary: string,
     *         file_path: string,
     *         file_size: int,
     *         upload_status: string,
     *         processing_status: string,
     *         extracted_text: string,
     *         entities: array<int, array{
     *             label: string,
     *             text: string,
     *             start: int,
     *             end: int,
     *         }>,
     *         metadata: array<string, mixed>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $knowledgeBoxId,
        string $filePath,
        string $title,
        string $summary = '',
        array $metadata = [],
    ): array {
        try {
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}.");
            }

            $fileContent = file_get_contents($filePath);
            $fileName = basename($filePath);
            $fileSize = \strlen($fileContent);

            $requestData = [
                'title' => $title,
                'summary' => $summary,
                'metadata' => $metadata,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/kb/{$knowledgeBoxId}/upload", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'multipart/form-data',
                ],
                'body' => [
                    'file' => $fileContent,
                    'filename' => $fileName,
                    'data' => json_encode($requestData),
                ],
            ] + $this->options);

            $data = $response->toArray();
            $upload = $data['upload'] ?? [];

            return [
                'success' => true,
                'upload' => [
                    'knowledge_box_id' => $knowledgeBoxId,
                    'resource_id' => $upload['resource_id'] ?? '',
                    'title' => $title,
                    'summary' => $summary,
                    'file_path' => $filePath,
                    'file_size' => $fileSize,
                    'upload_status' => $upload['upload_status'] ?? 'uploaded',
                    'processing_status' => $upload['processing_status'] ?? 'processing',
                    'extracted_text' => $upload['extracted_text'] ?? '',
                    'entities' => array_map(fn ($entity) => [
                        'label' => $entity['label'] ?? '',
                        'text' => $entity['text'] ?? '',
                        'start' => $entity['start'] ?? 0,
                        'end' => $entity['end'] ?? 0,
                    ], $upload['entities'] ?? []),
                    'metadata' => $metadata,
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'upload' => [
                    'knowledge_box_id' => $knowledgeBoxId,
                    'resource_id' => '',
                    'title' => $title,
                    'summary' => $summary,
                    'file_path' => $filePath,
                    'file_size' => 0,
                    'upload_status' => 'failed',
                    'processing_status' => 'failed',
                    'extracted_text' => '',
                    'entities' => [],
                    'metadata' => $metadata,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search Nuclia knowledge base.
     *
     * @param string               $knowledgeBoxId Knowledge box ID
     * @param string               $query          Search query
     * @param int                  $pageSize       Number of results per page
     * @param int                  $pageNumber     Page number
     * @param array<string, mixed> $filters        Search filters
     *
     * @return array{
     *     success: bool,
     *     search: array{
     *         knowledge_box_id: string,
     *         query: string,
     *         results: array<int, array{
     *             resource_id: string,
     *             title: string,
     *             summary: string,
     *             score: float,
     *             text: string,
     *             metadata: array<string, mixed>,
     *             entities: array<int, array{
     *                 label: string,
     *                 text: string,
     *                 start: int,
     *                 end: int,
     *             }>,
     *         }>,
     *         total_results: int,
     *         page_size: int,
     *         page_number: int,
     *         total_pages: int,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function search(
        string $knowledgeBoxId,
        string $query,
        int $pageSize = 10,
        int $pageNumber = 1,
        array $filters = [],
    ): array {
        try {
            $requestData = [
                'query' => $query,
                'page_size' => max(1, min($pageSize, 100)),
                'page_number' => max(1, $pageNumber),
                'filters' => $filters,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/kb/{$knowledgeBoxId}/search", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $search = $data['search'] ?? [];

            return [
                'success' => true,
                'search' => [
                    'knowledge_box_id' => $knowledgeBoxId,
                    'query' => $query,
                    'results' => array_map(fn ($result) => [
                        'resource_id' => $result['resource_id'] ?? '',
                        'title' => $result['title'] ?? '',
                        'summary' => $result['summary'] ?? '',
                        'score' => $result['score'] ?? 0.0,
                        'text' => $result['text'] ?? '',
                        'metadata' => $result['metadata'] ?? [],
                        'entities' => array_map(fn ($entity) => [
                            'label' => $entity['label'] ?? '',
                            'text' => $entity['text'] ?? '',
                            'start' => $entity['start'] ?? 0,
                            'end' => $entity['end'] ?? 0,
                        ], $result['entities'] ?? []),
                    ], $search['results'] ?? []),
                    'total_results' => $search['total_results'] ?? 0,
                    'page_size' => $pageSize,
                    'page_number' => $pageNumber,
                    'total_pages' => $search['total_pages'] ?? 0,
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'search' => [
                    'knowledge_box_id' => $knowledgeBoxId,
                    'query' => $query,
                    'results' => [],
                    'total_results' => 0,
                    'page_size' => $pageSize,
                    'page_number' => $pageNumber,
                    'total_pages' => 0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get resource from Nuclia.
     *
     * @param string $knowledgeBoxId  Knowledge box ID
     * @param string $resourceId      Resource ID
     * @param bool   $includeText     Whether to include extracted text
     * @param bool   $includeEntities Whether to include entities
     *
     * @return array{
     *     success: bool,
     *     resource: array{
     *         resource_id: string,
     *         title: string,
     *         summary: string,
     *         created_at: string,
     *         updated_at: string,
     *         file_type: string,
     *         file_size: int,
     *         text: string,
     *         entities: array<int, array{
     *             label: string,
     *             text: string,
     *             start: int,
     *             end: int,
     *             confidence: float,
     *         }>,
     *         metadata: array<string, mixed>,
     *         status: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function getResource(
        string $knowledgeBoxId,
        string $resourceId,
        bool $includeText = true,
        bool $includeEntities = true,
    ): array {
        try {
            $response = $this->httpClient->request('GET', "{$this->baseUrl}/kb/{$knowledgeBoxId}/resource/{$resourceId}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                ],
                'query' => [
                    'include_text' => $includeText ? 'true' : 'false',
                    'include_entities' => $includeEntities ? 'true' : 'false',
                ],
            ] + $this->options);

            $data = $response->toArray();
            $resource = $data['resource'] ?? [];

            return [
                'success' => true,
                'resource' => [
                    'resource_id' => $resourceId,
                    'title' => $resource['title'] ?? '',
                    'summary' => $resource['summary'] ?? '',
                    'created_at' => $resource['created_at'] ?? '',
                    'updated_at' => $resource['updated_at'] ?? '',
                    'file_type' => $resource['file_type'] ?? '',
                    'file_size' => $resource['file_size'] ?? 0,
                    'text' => $includeText ? ($resource['text'] ?? '') : '',
                    'entities' => $includeEntities ? array_map(fn ($entity) => [
                        'label' => $entity['label'] ?? '',
                        'text' => $entity['text'] ?? '',
                        'start' => $entity['start'] ?? 0,
                        'end' => $entity['end'] ?? 0,
                        'confidence' => $entity['confidence'] ?? 0.0,
                    ], $resource['entities'] ?? []) : [],
                    'metadata' => $resource['metadata'] ?? [],
                    'status' => $resource['status'] ?? 'unknown',
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'resource' => [
                    'resource_id' => $resourceId,
                    'title' => '',
                    'summary' => '',
                    'created_at' => '',
                    'updated_at' => '',
                    'file_type' => '',
                    'file_size' => 0,
                    'text' => '',
                    'entities' => [],
                    'metadata' => [],
                    'status' => 'unknown',
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create knowledge box.
     *
     * @param string               $title       Knowledge box title
     * @param string               $description Knowledge box description
     * @param array<string, mixed> $settings    Knowledge box settings
     *
     * @return array{
     *     success: bool,
     *     knowledge_box: array{
     *         id: string,
     *         title: string,
     *         description: string,
     *         created_at: string,
     *         settings: array<string, mixed>,
     *         status: string,
     *         resource_count: int,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function createKnowledgeBox(
        string $title,
        string $description = '',
        array $settings = [],
    ): array {
        try {
            $requestData = [
                'title' => $title,
                'description' => $description,
                'settings' => $settings,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/kb", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $knowledgeBox = $data['knowledge_box'] ?? [];

            return [
                'success' => true,
                'knowledge_box' => [
                    'id' => $knowledgeBox['id'] ?? '',
                    'title' => $title,
                    'description' => $description,
                    'created_at' => $knowledgeBox['created_at'] ?? '',
                    'settings' => $settings,
                    'status' => $knowledgeBox['status'] ?? 'active',
                    'resource_count' => $knowledgeBox['resource_count'] ?? 0,
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'knowledge_box' => [
                    'id' => '',
                    'title' => $title,
                    'description' => $description,
                    'created_at' => '',
                    'settings' => $settings,
                    'status' => 'failed',
                    'resource_count' => 0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List knowledge boxes.
     *
     * @param int $pageSize   Number of results per page
     * @param int $pageNumber Page number
     *
     * @return array{
     *     success: bool,
     *     knowledge_boxes: array<int, array{
     *         id: string,
     *         title: string,
     *         description: string,
     *         created_at: string,
     *         resource_count: int,
     *         status: string,
     *     }>,
     *     total_count: int,
     *     page_size: int,
     *     page_number: int,
     *     total_pages: int,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function listKnowledgeBoxes(
        int $pageSize = 20,
        int $pageNumber = 1,
    ): array {
        try {
            $response = $this->httpClient->request('GET', "{$this->baseUrl}/kb", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                ],
                'query' => [
                    'page_size' => max(1, min($pageSize, 100)),
                    'page_number' => max(1, $pageNumber),
                ],
            ] + $this->options);

            $data = $response->toArray();
            $knowledgeBoxes = $data['knowledge_boxes'] ?? [];

            return [
                'success' => true,
                'knowledge_boxes' => array_map(fn ($kb) => [
                    'id' => $kb['id'] ?? '',
                    'title' => $kb['title'] ?? '',
                    'description' => $kb['description'] ?? '',
                    'created_at' => $kb['created_at'] ?? '',
                    'resource_count' => $kb['resource_count'] ?? 0,
                    'status' => $kb['status'] ?? 'unknown',
                ], $knowledgeBoxes),
                'total_count' => $data['total_count'] ?? 0,
                'page_size' => $pageSize,
                'page_number' => $pageNumber,
                'total_pages' => $data['total_pages'] ?? 0,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'knowledge_boxes' => [],
                'total_count' => 0,
                'page_size' => $pageSize,
                'page_number' => $pageNumber,
                'total_pages' => 0,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete resource from Nuclia.
     *
     * @param string $knowledgeBoxId Knowledge box ID
     * @param string $resourceId     Resource ID to delete
     *
     * @return array{
     *     success: bool,
     *     deletion: array{
     *         knowledge_box_id: string,
     *         resource_id: string,
     *         deleted_at: string,
     *         status: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function deleteResource(
        string $knowledgeBoxId,
        string $resourceId,
    ): array {
        try {
            $response = $this->httpClient->request('DELETE', "{$this->baseUrl}/kb/{$knowledgeBoxId}/resource/{$resourceId}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                ],
            ] + $this->options);

            $data = $response->toArray();
            $deletion = $data['deletion'] ?? [];

            return [
                'success' => true,
                'deletion' => [
                    'knowledge_box_id' => $knowledgeBoxId,
                    'resource_id' => $resourceId,
                    'deleted_at' => $deletion['deleted_at'] ?? '',
                    'status' => 'deleted',
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'deletion' => [
                    'knowledge_box_id' => $knowledgeBoxId,
                    'resource_id' => $resourceId,
                    'deleted_at' => '',
                    'status' => 'failed',
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract text from documents.
     *
     * @param string $knowledgeBoxId Knowledge box ID
     * @param string $resourceId     Resource ID
     * @param string $textType       Type of text to extract (full, summary, entities)
     *
     * @return array{
     *     success: bool,
     *     extraction: array{
     *         resource_id: string,
     *         text_type: string,
     *         extracted_text: string,
     *         entities: array<int, array{
     *             label: string,
     *             text: string,
     *             start: int,
     *             end: int,
     *             confidence: float,
     *         }>,
     *         summary: string,
     *         word_count: int,
     *         character_count: int,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function extractText(
        string $knowledgeBoxId,
        string $resourceId,
        string $textType = 'full',
    ): array {
        try {
            $response = $this->httpClient->request('GET', "{$this->baseUrl}/kb/{$knowledgeBoxId}/resource/{$resourceId}/text", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                ],
                'query' => [
                    'text_type' => $textType,
                ],
            ] + $this->options);

            $data = $response->toArray();
            $extraction = $data['extraction'] ?? [];

            return [
                'success' => true,
                'extraction' => [
                    'resource_id' => $resourceId,
                    'text_type' => $textType,
                    'extracted_text' => $extraction['extracted_text'] ?? '',
                    'entities' => array_map(fn ($entity) => [
                        'label' => $entity['label'] ?? '',
                        'text' => $entity['text'] ?? '',
                        'start' => $entity['start'] ?? 0,
                        'end' => $entity['end'] ?? 0,
                        'confidence' => $entity['confidence'] ?? 0.0,
                    ], $extraction['entities'] ?? []),
                    'summary' => $extraction['summary'] ?? '',
                    'word_count' => $extraction['word_count'] ?? 0,
                    'character_count' => $extraction['character_count'] ?? 0,
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'extraction' => [
                    'resource_id' => $resourceId,
                    'text_type' => $textType,
                    'extracted_text' => '',
                    'entities' => [],
                    'summary' => '',
                    'word_count' => 0,
                    'character_count' => 0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate answer from knowledge base.
     *
     * @param string $knowledgeBoxId      Knowledge box ID
     * @param string $question            Question to answer
     * @param int    $maxResults          Maximum number of results to consider
     * @param float  $confidenceThreshold Minimum confidence threshold
     *
     * @return array{
     *     success: bool,
     *     answer: array{
     *         question: string,
     *         answer: string,
     *         confidence: float,
     *         sources: array<int, array{
     *             resource_id: string,
     *             title: string,
     *             score: float,
     *             text: string,
     *         }>,
     *         entities: array<int, array{
     *             label: string,
     *             text: string,
     *             confidence: float,
     *         }>,
     *         reasoning: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function generateAnswer(
        string $knowledgeBoxId,
        string $question,
        int $maxResults = 5,
        float $confidenceThreshold = 0.7,
    ): array {
        try {
            $requestData = [
                'question' => $question,
                'max_results' => max(1, min($maxResults, 20)),
                'confidence_threshold' => max(0.0, min($confidenceThreshold, 1.0)),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/kb/{$knowledgeBoxId}/answer", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $answer = $data['answer'] ?? [];

            return [
                'success' => true,
                'answer' => [
                    'question' => $question,
                    'answer' => $answer['answer'] ?? '',
                    'confidence' => $answer['confidence'] ?? 0.0,
                    'sources' => array_map(fn ($source) => [
                        'resource_id' => $source['resource_id'] ?? '',
                        'title' => $source['title'] ?? '',
                        'score' => $source['score'] ?? 0.0,
                        'text' => $source['text'] ?? '',
                    ], $answer['sources'] ?? []),
                    'entities' => array_map(fn ($entity) => [
                        'label' => $entity['label'] ?? '',
                        'text' => $entity['text'] ?? '',
                        'confidence' => $entity['confidence'] ?? 0.0,
                    ], $answer['entities'] ?? []),
                    'reasoning' => $answer['reasoning'] ?? '',
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'answer' => [
                    'question' => $question,
                    'answer' => '',
                    'confidence' => 0.0,
                    'sources' => [],
                    'entities' => [],
                    'reasoning' => '',
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}

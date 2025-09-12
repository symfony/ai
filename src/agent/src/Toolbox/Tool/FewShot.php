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
#[AsTool('few_shot_learn', 'Tool that performs few-shot learning with examples')]
#[AsTool('few_shot_classify', 'Tool that classifies data using few-shot examples', method: 'classify')]
#[AsTool('few_shot_generate', 'Tool that generates content using few-shot examples', method: 'generate')]
#[AsTool('few_shot_extract', 'Tool that extracts information using few-shot examples', method: 'extract')]
#[AsTool('few_shot_transform', 'Tool that transforms data using few-shot examples', method: 'transform')]
#[AsTool('few_shot_compare', 'Tool that compares data using few-shot examples', method: 'compare')]
#[AsTool('few_shot_similarity', 'Tool that finds similar data using few-shot examples', method: 'similarity')]
#[AsTool('few_shot_cluster', 'Tool that clusters data using few-shot examples', method: 'cluster')]
final readonly class FewShot
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.fewshot.ai/v1',
        private array $options = [],
    ) {
    }

    /**
     * Perform few-shot learning with examples.
     *
     * @param string $task Task description
     * @param array<int, array{
     *     input: string,
     *     output: string,
     *     explanation?: string,
     * }> $examples Few-shot examples
     * @param string               $input      Input to process
     * @param string               $model      Model to use
     * @param array<string, mixed> $parameters Additional parameters
     *
     * @return array{
     *     success: bool,
     *     result: array{
     *         input: string,
     *         output: string,
     *         confidence: float,
     *         reasoning: string,
     *         examples_used: int,
     *         model: string,
     *     },
     *     task: string,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $task,
        array $examples,
        string $input,
        string $model = 'gpt-3.5-turbo',
        array $parameters = [],
    ): array {
        try {
            $requestData = [
                'task' => $task,
                'examples' => $examples,
                'input' => $input,
                'model' => $model,
                'parameters' => $parameters,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/learn", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $result = $data['result'] ?? [];

            return [
                'success' => true,
                'result' => [
                    'input' => $input,
                    'output' => $result['output'] ?? '',
                    'confidence' => $result['confidence'] ?? 0.0,
                    'reasoning' => $result['reasoning'] ?? '',
                    'examples_used' => \count($examples),
                    'model' => $model,
                ],
                'task' => $task,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'result' => [
                    'input' => $input,
                    'output' => '',
                    'confidence' => 0.0,
                    'reasoning' => '',
                    'examples_used' => \count($examples),
                    'model' => $model,
                ],
                'task' => $task,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Classify data using few-shot examples.
     *
     * @param string             $input      Input to classify
     * @param array<int, string> $categories Categories to classify into
     * @param array<int, array{
     *     input: string,
     *     category: string,
     *     confidence?: float,
     * }> $examples Few-shot classification examples
     * @param string $model Model to use
     *
     * @return array{
     *     success: bool,
     *     classification: array{
     *         input: string,
     *         category: string,
     *         confidence: float,
     *         probabilities: array<string, float>,
     *         reasoning: string,
     *     },
     *     categories: array<int, string>,
     *     examples_used: int,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function classify(
        string $input,
        array $categories,
        array $examples,
        string $model = 'gpt-3.5-turbo',
    ): array {
        try {
            $requestData = [
                'input' => $input,
                'categories' => $categories,
                'examples' => $examples,
                'model' => $model,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/classify", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $classification = $data['classification'] ?? [];

            return [
                'success' => true,
                'classification' => [
                    'input' => $input,
                    'category' => $classification['category'] ?? '',
                    'confidence' => $classification['confidence'] ?? 0.0,
                    'probabilities' => $classification['probabilities'] ?? [],
                    'reasoning' => $classification['reasoning'] ?? '',
                ],
                'categories' => $categories,
                'examples_used' => \count($examples),
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'classification' => [
                    'input' => $input,
                    'category' => '',
                    'confidence' => 0.0,
                    'probabilities' => [],
                    'reasoning' => '',
                ],
                'categories' => $categories,
                'examples_used' => \count($examples),
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate content using few-shot examples.
     *
     * @param string $prompt Generation prompt
     * @param array<int, array{
     *     prompt: string,
     *     output: string,
     *     style?: string,
     * }> $examples Few-shot generation examples
     * @param string $model       Model to use
     * @param int    $maxTokens   Maximum tokens to generate
     * @param float  $temperature Generation temperature
     *
     * @return array{
     *     success: bool,
     *     generation: array{
     *         prompt: string,
     *         output: string,
     *         confidence: float,
     *         style: string,
     *         tokens_used: int,
     *         examples_used: int,
     *     },
     *     model: string,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function generate(
        string $prompt,
        array $examples,
        string $model = 'gpt-3.5-turbo',
        int $maxTokens = 1000,
        float $temperature = 0.7,
    ): array {
        try {
            $requestData = [
                'prompt' => $prompt,
                'examples' => $examples,
                'model' => $model,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/generate", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $generation = $data['generation'] ?? [];

            return [
                'success' => true,
                'generation' => [
                    'prompt' => $prompt,
                    'output' => $generation['output'] ?? '',
                    'confidence' => $generation['confidence'] ?? 0.0,
                    'style' => $generation['style'] ?? '',
                    'tokens_used' => $generation['tokens_used'] ?? 0,
                    'examples_used' => \count($examples),
                ],
                'model' => $model,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'generation' => [
                    'prompt' => $prompt,
                    'output' => '',
                    'confidence' => 0.0,
                    'style' => '',
                    'tokens_used' => 0,
                    'examples_used' => \count($examples),
                ],
                'model' => $model,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract information using few-shot examples.
     *
     * @param string $text           Text to extract from
     * @param string $extractionType Type of extraction (entities, relationships, facts, etc.)
     * @param array<int, array{
     *     text: string,
     *     extracted: array<string, mixed>,
     *     format?: string,
     * }> $examples Few-shot extraction examples
     * @param string $model Model to use
     *
     * @return array{
     *     success: bool,
     *     extraction: array{
     *         text: string,
     *         extracted: array<string, mixed>,
     *         confidence: float,
     *         extraction_type: string,
     *         format: string,
     *         examples_used: int,
     *     },
     *     model: string,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function extract(
        string $text,
        string $extractionType,
        array $examples,
        string $model = 'gpt-3.5-turbo',
    ): array {
        try {
            $requestData = [
                'text' => $text,
                'extraction_type' => $extractionType,
                'examples' => $examples,
                'model' => $model,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/extract", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $extraction = $data['extraction'] ?? [];

            return [
                'success' => true,
                'extraction' => [
                    'text' => $text,
                    'extracted' => $extraction['extracted'] ?? [],
                    'confidence' => $extraction['confidence'] ?? 0.0,
                    'extraction_type' => $extractionType,
                    'format' => $extraction['format'] ?? 'json',
                    'examples_used' => \count($examples),
                ],
                'model' => $model,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'extraction' => [
                    'text' => $text,
                    'extracted' => [],
                    'confidence' => 0.0,
                    'extraction_type' => $extractionType,
                    'format' => 'json',
                    'examples_used' => \count($examples),
                ],
                'model' => $model,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Transform data using few-shot examples.
     *
     * @param string $input              Input to transform
     * @param string $transformationType Type of transformation
     * @param array<int, array{
     *     input: string,
     *     output: string,
     *     transformation?: string,
     * }> $examples Few-shot transformation examples
     * @param string $model Model to use
     *
     * @return array{
     *     success: bool,
     *     transformation: array{
     *         input: string,
     *         output: string,
     *         transformation_type: string,
     *         confidence: float,
     *         reasoning: string,
     *         examples_used: int,
     *     },
     *     model: string,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function transform(
        string $input,
        string $transformationType,
        array $examples,
        string $model = 'gpt-3.5-turbo',
    ): array {
        try {
            $requestData = [
                'input' => $input,
                'transformation_type' => $transformationType,
                'examples' => $examples,
                'model' => $model,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/transform", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $transformation = $data['transformation'] ?? [];

            return [
                'success' => true,
                'transformation' => [
                    'input' => $input,
                    'output' => $transformation['output'] ?? '',
                    'transformation_type' => $transformationType,
                    'confidence' => $transformation['confidence'] ?? 0.0,
                    'reasoning' => $transformation['reasoning'] ?? '',
                    'examples_used' => \count($examples),
                ],
                'model' => $model,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'transformation' => [
                    'input' => $input,
                    'output' => '',
                    'transformation_type' => $transformationType,
                    'confidence' => 0.0,
                    'reasoning' => '',
                    'examples_used' => \count($examples),
                ],
                'model' => $model,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Compare data using few-shot examples.
     *
     * @param string $item1          First item to compare
     * @param string $item2          Second item to compare
     * @param string $comparisonType Type of comparison
     * @param array<int, array{
     *     item1: string,
     *     item2: string,
     *     comparison: array{
     *         similarity: float,
     *         differences: array<int, string>,
     *         relationship: string,
     *     },
     * }> $examples Few-shot comparison examples
     * @param string $model Model to use
     *
     * @return array{
     *     success: bool,
     *     comparison: array{
     *         item1: string,
     *         item2: string,
     *         similarity: float,
     *         differences: array<int, string>,
     *         relationship: string,
     *         confidence: float,
     *         reasoning: string,
     *         examples_used: int,
     *     },
     *     comparisonType: string,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function compare(
        string $item1,
        string $item2,
        string $comparisonType,
        array $examples,
        string $model = 'gpt-3.5-turbo',
    ): array {
        try {
            $requestData = [
                'item1' => $item1,
                'item2' => $item2,
                'comparison_type' => $comparisonType,
                'examples' => $examples,
                'model' => $model,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/compare", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $comparison = $data['comparison'] ?? [];

            return [
                'success' => true,
                'comparison' => [
                    'item1' => $item1,
                    'item2' => $item2,
                    'similarity' => $comparison['similarity'] ?? 0.0,
                    'differences' => $comparison['differences'] ?? [],
                    'relationship' => $comparison['relationship'] ?? '',
                    'confidence' => $comparison['confidence'] ?? 0.0,
                    'reasoning' => $comparison['reasoning'] ?? '',
                    'examples_used' => \count($examples),
                ],
                'comparisonType' => $comparisonType,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'comparison' => [
                    'item1' => $item1,
                    'item2' => $item2,
                    'similarity' => 0.0,
                    'differences' => [],
                    'relationship' => '',
                    'confidence' => 0.0,
                    'reasoning' => '',
                    'examples_used' => \count($examples),
                ],
                'comparisonType' => $comparisonType,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Find similar data using few-shot examples.
     *
     * @param string             $query      Query to find similarities for
     * @param array<int, string> $candidates Candidate items to compare against
     * @param array<int, array{
     *     query: string,
     *     candidates: array<int, string>,
     *     similarities: array<string, float>,
     *     top_matches: array<int, array{
     *         item: string,
     *         similarity: float,
     *         reasoning: string,
     *     }>,
     * }> $examples Few-shot similarity examples
     * @param string $model Model to use
     * @param int    $topK  Number of top similar items to return
     *
     * @return array{
     *     success: bool,
     *     similarity: array{
     *         query: string,
     *         similarities: array<string, float>,
     *         top_matches: array<int, array{
     *             item: string,
     *             similarity: float,
     *             reasoning: string,
     *         }>,
     *         confidence: float,
     *         examples_used: int,
     *     },
     *     candidates: array<int, string>,
     *     topK: int,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function similarity(
        string $query,
        array $candidates,
        array $examples,
        string $model = 'gpt-3.5-turbo',
        int $topK = 5,
    ): array {
        try {
            $requestData = [
                'query' => $query,
                'candidates' => $candidates,
                'examples' => $examples,
                'model' => $model,
                'top_k' => $topK,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/similarity", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $similarity = $data['similarity'] ?? [];

            return [
                'success' => true,
                'similarity' => [
                    'query' => $query,
                    'similarities' => $similarity['similarities'] ?? [],
                    'top_matches' => array_map(fn ($match) => [
                        'item' => $match['item'] ?? '',
                        'similarity' => $match['similarity'] ?? 0.0,
                        'reasoning' => $match['reasoning'] ?? '',
                    ], $similarity['top_matches'] ?? []),
                    'confidence' => $similarity['confidence'] ?? 0.0,
                    'examples_used' => \count($examples),
                ],
                'candidates' => $candidates,
                'topK' => $topK,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'similarity' => [
                    'query' => $query,
                    'similarities' => [],
                    'top_matches' => [],
                    'confidence' => 0.0,
                    'examples_used' => \count($examples),
                ],
                'candidates' => $candidates,
                'topK' => $topK,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cluster data using few-shot examples.
     *
     * @param array<int, string> $items Items to cluster
     * @param array<int, array{
     *     items: array<int, string>,
     *     clusters: array<int, array{
     *         id: string,
     *         items: array<int, string>,
     *         centroid: string,
     *         description: string,
     *     }>,
     * }> $examples Few-shot clustering examples
     * @param string $model       Model to use
     * @param int    $numClusters Number of clusters to create
     *
     * @return array{
     *     success: bool,
     *     clustering: array{
     *         items: array<int, string>,
     *         clusters: array<int, array{
     *             id: string,
     *             items: array<int, string>,
     *             centroid: string,
     *             description: string,
     *             confidence: float,
     *         }>,
     *         silhouette_score: float,
     *         examples_used: int,
     *     },
     *     numClusters: int,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function cluster(
        array $items,
        array $examples,
        string $model = 'gpt-3.5-turbo',
        int $numClusters = 3,
    ): array {
        try {
            $requestData = [
                'items' => $items,
                'examples' => $examples,
                'model' => $model,
                'num_clusters' => $numClusters,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/cluster", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $clustering = $data['clustering'] ?? [];

            return [
                'success' => true,
                'clustering' => [
                    'items' => $items,
                    'clusters' => array_map(fn ($cluster) => [
                        'id' => $cluster['id'] ?? '',
                        'items' => $cluster['items'] ?? [],
                        'centroid' => $cluster['centroid'] ?? '',
                        'description' => $cluster['description'] ?? '',
                        'confidence' => $cluster['confidence'] ?? 0.0,
                    ], $clustering['clusters'] ?? []),
                    'silhouette_score' => $clustering['silhouette_score'] ?? 0.0,
                    'examples_used' => \count($examples),
                ],
                'numClusters' => $numClusters,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'clustering' => [
                    'items' => $items,
                    'clusters' => [],
                    'silhouette_score' => 0.0,
                    'examples_used' => \count($examples),
                ],
                'numClusters' => $numClusters,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}

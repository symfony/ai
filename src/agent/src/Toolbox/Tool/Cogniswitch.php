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
#[AsTool('cogniswitch_extract', 'Tool that extracts knowledge using Cogniswitch')]
#[AsTool('cogniswitch_search', 'Tool that searches knowledge base', method: 'search')]
#[AsTool('cogniswitch_analyze', 'Tool that analyzes content', method: 'analyze')]
#[AsTool('cogniswitch_synthesize', 'Tool that synthesizes information', method: 'synthesize')]
#[AsTool('cogniswitch_validate', 'Tool that validates knowledge', method: 'validate')]
#[AsTool('cogniswitch_enrich', 'Tool that enriches content', method: 'enrich')]
#[AsTool('cogniswitch_classify', 'Tool that classifies content', method: 'classify')]
#[AsTool('cogniswitch_summarize', 'Tool that summarizes content', method: 'summarize')]
final readonly class Cogniswitch
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.cogniswitch.ai/v1',
        private array $options = [],
    ) {
    }

    /**
     * Extract knowledge using Cogniswitch.
     *
     * @param string               $content        Content to extract knowledge from
     * @param string               $extractionType Type of extraction
     * @param array<string, mixed> $options        Extraction options
     * @param array<string, mixed> $context        Extraction context
     *
     * @return array{
     *     success: bool,
     *     extraction: array{
     *         content: string,
     *         extraction_type: string,
     *         knowledge_items: array<int, array{
     *             id: string,
     *             type: string,
     *             content: string,
     *             confidence: float,
     *             source: array{
     *                 start: int,
     *                 end: int,
     *                 text: string,
     *             },
     *             entities: array<int, array{
     *                 name: string,
     *                 type: string,
     *                 confidence: float,
     *             }>,
     *             relations: array<int, array{
     *                 subject: string,
     *                 predicate: string,
     *                 object: string,
     *                 confidence: float,
     *             }>,
     *         }>,
     *         summary: string,
     *         metadata: array<string, mixed>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $content,
        string $extractionType = 'entities_relations',
        array $options = [],
        array $context = [],
    ): array {
        try {
            $requestData = [
                'content' => $content,
                'extraction_type' => $extractionType,
                'options' => $options,
                'context' => $context,
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
                    'content' => $content,
                    'extraction_type' => $extractionType,
                    'knowledge_items' => array_map(fn ($item) => [
                        'id' => $item['id'] ?? '',
                        'type' => $item['type'] ?? '',
                        'content' => $item['content'] ?? '',
                        'confidence' => $item['confidence'] ?? 0.0,
                        'source' => [
                            'start' => $item['source']['start'] ?? 0,
                            'end' => $item['source']['end'] ?? 0,
                            'text' => $item['source']['text'] ?? '',
                        ],
                        'entities' => array_map(fn ($entity) => [
                            'name' => $entity['name'] ?? '',
                            'type' => $entity['type'] ?? '',
                            'confidence' => $entity['confidence'] ?? 0.0,
                        ], $item['entities'] ?? []),
                        'relations' => array_map(fn ($relation) => [
                            'subject' => $relation['subject'] ?? '',
                            'predicate' => $relation['predicate'] ?? '',
                            'object' => $relation['object'] ?? '',
                            'confidence' => $relation['confidence'] ?? 0.0,
                        ], $item['relations'] ?? []),
                    ], $extraction['knowledge_items'] ?? []),
                    'summary' => $extraction['summary'] ?? '',
                    'metadata' => $extraction['metadata'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'extraction' => [
                    'content' => $content,
                    'extraction_type' => $extractionType,
                    'knowledge_items' => [],
                    'summary' => '',
                    'metadata' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search knowledge base.
     *
     * @param string               $query      Search query
     * @param array<string, mixed> $filters    Search filters
     * @param string               $searchType Type of search
     * @param int                  $limit      Number of results to return
     *
     * @return array{
     *     success: bool,
     *     search_results: array{
     *         query: string,
     *         search_type: string,
     *         results: array<int, array{
     *             id: string,
     *             title: string,
     *             content: string,
     *             relevance_score: float,
     *             source: string,
     *             metadata: array<string, mixed>,
     *             entities: array<int, array{
     *                 name: string,
     *                 type: string,
     *                 confidence: float,
     *             }>,
     *             relations: array<int, array{
     *                 subject: string,
     *                 predicate: string,
     *                 object: string,
     *             }>,
     *         }>,
     *         total_results: int,
     *         facets: array<string, array<int, array{
     *             value: string,
     *             count: int,
     *         }>>,
     *         suggestions: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function search(
        string $query,
        array $filters = [],
        string $searchType = 'semantic',
        int $limit = 10,
    ): array {
        try {
            $requestData = [
                'query' => $query,
                'filters' => $filters,
                'search_type' => $searchType,
                'limit' => max(1, min($limit, 100)),
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
                    'search_type' => $searchType,
                    'results' => array_map(fn ($result) => [
                        'id' => $result['id'] ?? '',
                        'title' => $result['title'] ?? '',
                        'content' => $result['content'] ?? '',
                        'relevance_score' => $result['relevance_score'] ?? 0.0,
                        'source' => $result['source'] ?? '',
                        'metadata' => $result['metadata'] ?? [],
                        'entities' => array_map(fn ($entity) => [
                            'name' => $entity['name'] ?? '',
                            'type' => $entity['type'] ?? '',
                            'confidence' => $entity['confidence'] ?? 0.0,
                        ], $result['entities'] ?? []),
                        'relations' => array_map(fn ($relation) => [
                            'subject' => $relation['subject'] ?? '',
                            'predicate' => $relation['predicate'] ?? '',
                            'object' => $relation['object'] ?? '',
                        ], $result['relations'] ?? []),
                    ], $searchResults['results'] ?? []),
                    'total_results' => $searchResults['total_results'] ?? 0,
                    'facets' => array_map(fn ($facet) => array_map(fn ($item) => [
                        'value' => $item['value'] ?? '',
                        'count' => $item['count'] ?? 0,
                    ], $facet), $searchResults['facets'] ?? []),
                    'suggestions' => $searchResults['suggestions'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'search_results' => [
                    'query' => $query,
                    'search_type' => $searchType,
                    'results' => [],
                    'total_results' => 0,
                    'facets' => [],
                    'suggestions' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze content.
     *
     * @param string               $content       Content to analyze
     * @param array<string, mixed> $analysisTypes Types of analysis to perform
     * @param array<string, mixed> $options       Analysis options
     *
     * @return array{
     *     success: bool,
     *     analysis: array{
     *         content: string,
     *         analysis_types: array<string, mixed>,
     *         sentiment: array{
     *             score: float,
     *             label: string,
     *             confidence: float,
     *         },
     *         topics: array<int, array{
     *             topic: string,
     *             score: float,
     *             relevance: float,
     *         }>,
     *         entities: array<int, array{
     *             name: string,
     *             type: string,
     *             confidence: float,
     *             frequency: int,
     *         }>,
     *         key_phrases: array<int, string>,
     *         language: array{
     *             code: string,
     *             confidence: float,
     *         },
     *         readability: array{
     *             score: float,
     *             level: string,
     *             metrics: array<string, float>,
     *         },
     *         insights: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function analyze(
        string $content,
        array $analysisTypes = ['sentiment', 'topics', 'entities', 'readability'],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'content' => $content,
                'analysis_types' => $analysisTypes,
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/analyze", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $analysis = $responseData['analysis'] ?? [];

            return [
                'success' => true,
                'analysis' => [
                    'content' => $content,
                    'analysis_types' => $analysisTypes,
                    'sentiment' => [
                        'score' => $analysis['sentiment']['score'] ?? 0.0,
                        'label' => $analysis['sentiment']['label'] ?? 'neutral',
                        'confidence' => $analysis['sentiment']['confidence'] ?? 0.0,
                    ],
                    'topics' => array_map(fn ($topic) => [
                        'topic' => $topic['topic'] ?? '',
                        'score' => $topic['score'] ?? 0.0,
                        'relevance' => $topic['relevance'] ?? 0.0,
                    ], $analysis['topics'] ?? []),
                    'entities' => array_map(fn ($entity) => [
                        'name' => $entity['name'] ?? '',
                        'type' => $entity['type'] ?? '',
                        'confidence' => $entity['confidence'] ?? 0.0,
                        'frequency' => $entity['frequency'] ?? 0,
                    ], $analysis['entities'] ?? []),
                    'key_phrases' => $analysis['key_phrases'] ?? [],
                    'language' => [
                        'code' => $analysis['language']['code'] ?? 'en',
                        'confidence' => $analysis['language']['confidence'] ?? 0.0,
                    ],
                    'readability' => [
                        'score' => $analysis['readability']['score'] ?? 0.0,
                        'level' => $analysis['readability']['level'] ?? 'intermediate',
                        'metrics' => $analysis['readability']['metrics'] ?? [],
                    ],
                    'insights' => $analysis['insights'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'analysis' => [
                    'content' => $content,
                    'analysis_types' => $analysisTypes,
                    'sentiment' => [
                        'score' => 0.0,
                        'label' => 'neutral',
                        'confidence' => 0.0,
                    ],
                    'topics' => [],
                    'entities' => [],
                    'key_phrases' => [],
                    'language' => [
                        'code' => 'en',
                        'confidence' => 0.0,
                    ],
                    'readability' => [
                        'score' => 0.0,
                        'level' => 'intermediate',
                        'metrics' => [],
                    ],
                    'insights' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Synthesize information.
     *
     * @param array<int, string>   $sources       Sources to synthesize
     * @param string               $synthesisType Type of synthesis
     * @param array<string, mixed> $options       Synthesis options
     *
     * @return array{
     *     success: bool,
     *     synthesis: array{
     *         sources: array<int, string>,
     *         synthesis_type: string,
     *         synthesized_content: string,
     *         key_points: array<int, string>,
     *         contradictions: array<int, array{
     *             source1: string,
     *             source2: string,
     *             contradiction: string,
     *         }>,
     *         consensus_points: array<int, string>,
     *         confidence_scores: array<string, float>,
     *         metadata: array<string, mixed>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function synthesize(
        array $sources,
        string $synthesisType = 'comprehensive',
        array $options = [],
    ): array {
        try {
            $requestData = [
                'sources' => $sources,
                'synthesis_type' => $synthesisType,
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/synthesize", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $synthesis = $responseData['synthesis'] ?? [];

            return [
                'success' => true,
                'synthesis' => [
                    'sources' => $sources,
                    'synthesis_type' => $synthesisType,
                    'synthesized_content' => $synthesis['synthesized_content'] ?? '',
                    'key_points' => $synthesis['key_points'] ?? [],
                    'contradictions' => array_map(fn ($contradiction) => [
                        'source1' => $contradiction['source1'] ?? '',
                        'source2' => $contradiction['source2'] ?? '',
                        'contradiction' => $contradiction['contradiction'] ?? '',
                    ], $synthesis['contradictions'] ?? []),
                    'consensus_points' => $synthesis['consensus_points'] ?? [],
                    'confidence_scores' => $synthesis['confidence_scores'] ?? [],
                    'metadata' => $synthesis['metadata'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'synthesis' => [
                    'sources' => $sources,
                    'synthesis_type' => $synthesisType,
                    'synthesized_content' => '',
                    'key_points' => [],
                    'contradictions' => [],
                    'consensus_points' => [],
                    'confidence_scores' => [],
                    'metadata' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate knowledge.
     *
     * @param string               $content            Content to validate
     * @param array<string, mixed> $validationCriteria Validation criteria
     * @param array<string, mixed> $referenceData      Reference data for validation
     *
     * @return array{
     *     success: bool,
     *     validation: array{
     *         content: string,
     *         validation_criteria: array<string, mixed>,
     *         is_valid: bool,
     *         confidence: float,
     *         issues: array<int, array{
     *             type: string,
     *             description: string,
     *             severity: string,
     *             location: array{
     *                 start: int,
     *                 end: int,
     *             },
     *             suggestion: string,
     *         }>,
     *         facts_check: array{
     *             verified_facts: int,
     *             unverified_facts: int,
     *             contradicted_facts: int,
     *         },
     *         sources_verification: array<int, array{
     *             source: string,
     *             is_verified: bool,
     *             confidence: float,
     *         }>,
     *         recommendations: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function validate(
        string $content,
        array $validationCriteria = [],
        array $referenceData = [],
    ): array {
        try {
            $requestData = [
                'content' => $content,
                'validation_criteria' => $validationCriteria,
                'reference_data' => $referenceData,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/validate", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $validation = $responseData['validation'] ?? [];

            return [
                'success' => true,
                'validation' => [
                    'content' => $content,
                    'validation_criteria' => $validationCriteria,
                    'is_valid' => $validation['is_valid'] ?? false,
                    'confidence' => $validation['confidence'] ?? 0.0,
                    'issues' => array_map(fn ($issue) => [
                        'type' => $issue['type'] ?? '',
                        'description' => $issue['description'] ?? '',
                        'severity' => $issue['severity'] ?? 'low',
                        'location' => [
                            'start' => $issue['location']['start'] ?? 0,
                            'end' => $issue['location']['end'] ?? 0,
                        ],
                        'suggestion' => $issue['suggestion'] ?? '',
                    ], $validation['issues'] ?? []),
                    'facts_check' => [
                        'verified_facts' => $validation['facts_check']['verified_facts'] ?? 0,
                        'unverified_facts' => $validation['facts_check']['unverified_facts'] ?? 0,
                        'contradicted_facts' => $validation['facts_check']['contradicted_facts'] ?? 0,
                    ],
                    'sources_verification' => array_map(fn ($source) => [
                        'source' => $source['source'] ?? '',
                        'is_verified' => $source['is_verified'] ?? false,
                        'confidence' => $source['confidence'] ?? 0.0,
                    ], $validation['sources_verification'] ?? []),
                    'recommendations' => $validation['recommendations'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'validation' => [
                    'content' => $content,
                    'validation_criteria' => $validationCriteria,
                    'is_valid' => false,
                    'confidence' => 0.0,
                    'issues' => [],
                    'facts_check' => [
                        'verified_facts' => 0,
                        'unverified_facts' => 0,
                        'contradicted_facts' => 0,
                    ],
                    'sources_verification' => [],
                    'recommendations' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Enrich content.
     *
     * @param string               $content        Content to enrich
     * @param string               $enrichmentType Type of enrichment
     * @param array<string, mixed> $options        Enrichment options
     *
     * @return array{
     *     success: bool,
     *     enrichment: array{
     *         original_content: string,
     *         enriched_content: string,
     *         enrichment_type: string,
     *         added_elements: array<int, array{
     *             type: string,
     *             content: string,
     *             position: int,
     *             confidence: float,
     *         }>,
     *         contextual_info: array<int, array{
     *             topic: string,
     *             information: string,
     *             relevance: float,
     *         }>,
     *         related_concepts: array<int, array{
     *             concept: string,
     *             description: string,
     *             connection_strength: float,
     *         }>,
     *         metadata: array<string, mixed>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function enrich(
        string $content,
        string $enrichmentType = 'contextual',
        array $options = [],
    ): array {
        try {
            $requestData = [
                'content' => $content,
                'enrichment_type' => $enrichmentType,
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/enrich", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $enrichment = $responseData['enrichment'] ?? [];

            return [
                'success' => true,
                'enrichment' => [
                    'original_content' => $content,
                    'enriched_content' => $enrichment['enriched_content'] ?? '',
                    'enrichment_type' => $enrichmentType,
                    'added_elements' => array_map(fn ($element) => [
                        'type' => $element['type'] ?? '',
                        'content' => $element['content'] ?? '',
                        'position' => $element['position'] ?? 0,
                        'confidence' => $element['confidence'] ?? 0.0,
                    ], $enrichment['added_elements'] ?? []),
                    'contextual_info' => array_map(fn ($info) => [
                        'topic' => $info['topic'] ?? '',
                        'information' => $info['information'] ?? '',
                        'relevance' => $info['relevance'] ?? 0.0,
                    ], $enrichment['contextual_info'] ?? []),
                    'related_concepts' => array_map(fn ($concept) => [
                        'concept' => $concept['concept'] ?? '',
                        'description' => $concept['description'] ?? '',
                        'connection_strength' => $concept['connection_strength'] ?? 0.0,
                    ], $enrichment['related_concepts'] ?? []),
                    'metadata' => $enrichment['metadata'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'enrichment' => [
                    'original_content' => $content,
                    'enriched_content' => '',
                    'enrichment_type' => $enrichmentType,
                    'added_elements' => [],
                    'contextual_info' => [],
                    'related_concepts' => [],
                    'metadata' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Classify content.
     *
     * @param string               $content               Content to classify
     * @param array<string, mixed> $classificationOptions Classification options
     *
     * @return array{
     *     success: bool,
     *     classification: array{
     *         content: string,
     *         primary_category: string,
     *         secondary_categories: array<int, string>,
     *         confidence_scores: array<string, float>,
     *         tags: array<int, string>,
     *         attributes: array<string, mixed>,
     *         reasoning: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function classify(
        string $content,
        array $classificationOptions = [],
    ): array {
        try {
            $requestData = [
                'content' => $content,
                'classification_options' => $classificationOptions,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/classify", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $classification = $responseData['classification'] ?? [];

            return [
                'success' => true,
                'classification' => [
                    'content' => $content,
                    'primary_category' => $classification['primary_category'] ?? '',
                    'secondary_categories' => $classification['secondary_categories'] ?? [],
                    'confidence_scores' => $classification['confidence_scores'] ?? [],
                    'tags' => $classification['tags'] ?? [],
                    'attributes' => $classification['attributes'] ?? [],
                    'reasoning' => $classification['reasoning'] ?? '',
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'classification' => [
                    'content' => $content,
                    'primary_category' => '',
                    'secondary_categories' => [],
                    'confidence_scores' => [],
                    'tags' => [],
                    'attributes' => [],
                    'reasoning' => '',
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Summarize content.
     *
     * @param string               $content     Content to summarize
     * @param string               $summaryType Type of summary
     * @param int                  $maxLength   Maximum summary length
     * @param array<string, mixed> $options     Summary options
     *
     * @return array{
     *     success: bool,
     *     summary: array{
     *         original_content: string,
     *         summary: string,
     *         summary_type: string,
     *         max_length: int,
     *         compression_ratio: float,
     *         key_points: array<int, string>,
     *         sentiment: string,
     *         topics: array<int, string>,
     *         entities: array<int, array{
     *             name: string,
     *             type: string,
     *             mentions: int,
     *         }>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function summarize(
        string $content,
        string $summaryType = 'extractive',
        int $maxLength = 200,
        array $options = [],
    ): array {
        try {
            $requestData = [
                'content' => $content,
                'summary_type' => $summaryType,
                'max_length' => max(50, min($maxLength, 1000)),
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/summarize", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $summary = $responseData['summary'] ?? [];

            return [
                'success' => true,
                'summary' => [
                    'original_content' => $content,
                    'summary' => $summary['summary'] ?? '',
                    'summary_type' => $summaryType,
                    'max_length' => $maxLength,
                    'compression_ratio' => $summary['compression_ratio'] ?? 0.0,
                    'key_points' => $summary['key_points'] ?? [],
                    'sentiment' => $summary['sentiment'] ?? 'neutral',
                    'topics' => $summary['topics'] ?? [],
                    'entities' => array_map(fn ($entity) => [
                        'name' => $entity['name'] ?? '',
                        'type' => $entity['type'] ?? '',
                        'mentions' => $entity['mentions'] ?? 0,
                    ], $summary['entities'] ?? []),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'summary' => [
                    'original_content' => $content,
                    'summary' => '',
                    'summary_type' => $summaryType,
                    'max_length' => $maxLength,
                    'compression_ratio' => 0.0,
                    'key_points' => [],
                    'sentiment' => 'neutral',
                    'topics' => [],
                    'entities' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}

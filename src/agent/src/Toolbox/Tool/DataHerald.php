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
#[AsTool('dataherald_query', 'Tool that generates natural language queries using DataHerald')]
#[AsTool('dataherald_sql_generation', 'Tool that generates SQL from natural language', method: 'sqlGeneration')]
#[AsTool('dataherald_schema_analysis', 'Tool that analyzes database schema', method: 'schemaAnalysis')]
#[AsTool('dataherald_query_explanation', 'Tool that explains SQL queries', method: 'queryExplanation')]
#[AsTool('dataherald_data_visualization', 'Tool that generates data visualizations', method: 'dataVisualization')]
#[AsTool('dataherald_nlq_generation', 'Tool that generates natural language questions', method: 'nlqGeneration')]
#[AsTool('dataherald_query_optimization', 'Tool that optimizes queries', method: 'queryOptimization')]
#[AsTool('dataherald_data_storytelling', 'Tool that creates data stories', method: 'dataStorytelling')]
final readonly class DataHerald
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.dataherald.com/v1',
        private array $options = [],
    ) {
    }

    /**
     * Generate natural language queries using DataHerald.
     *
     * @param string               $question     Natural language question
     * @param string               $databaseType Database type (mysql, postgresql, sqlite, mssql)
     * @param array<string, mixed> $context      Additional context
     * @param string               $language     Language for response
     *
     * @return array{
     *     success: bool,
     *     query_generation: array{
     *         question: string,
     *         database_type: string,
     *         generated_sql: string,
     *         confidence: float,
     *         explanation: string,
     *         parameters: array<string, mixed>,
     *         execution_plan: array<string, mixed>,
     *         alternatives: array<int, array{
     *             sql: string,
     *             confidence: float,
     *             explanation: string,
     *         }>,
     *     },
     *     language: string,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $question,
        string $databaseType = 'mysql',
        array $context = [],
        string $language = 'en',
    ): array {
        try {
            $requestData = [
                'question' => $question,
                'database_type' => $databaseType,
                'context' => $context,
                'language' => $language,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/query/generate", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $result = $data['query_generation'] ?? [];

            return [
                'success' => true,
                'query_generation' => [
                    'question' => $question,
                    'database_type' => $databaseType,
                    'generated_sql' => $result['generated_sql'] ?? '',
                    'confidence' => $result['confidence'] ?? 0.0,
                    'explanation' => $result['explanation'] ?? '',
                    'parameters' => $result['parameters'] ?? [],
                    'execution_plan' => $result['execution_plan'] ?? [],
                    'alternatives' => array_map(fn ($alt) => [
                        'sql' => $alt['sql'] ?? '',
                        'confidence' => $alt['confidence'] ?? 0.0,
                        'explanation' => $alt['explanation'] ?? '',
                    ], $result['alternatives'] ?? []),
                ],
                'language' => $language,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'query_generation' => [
                    'question' => $question,
                    'database_type' => $databaseType,
                    'generated_sql' => '',
                    'confidence' => 0.0,
                    'explanation' => '',
                    'parameters' => [],
                    'execution_plan' => [],
                    'alternatives' => [],
                ],
                'language' => $language,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate SQL from natural language.
     *
     * @param string               $question     Natural language question
     * @param string               $schema       Database schema information
     * @param string               $databaseType Database type
     * @param array<string, mixed> $examples     Example queries for context
     *
     * @return array{
     *     success: bool,
     *     sql_generation: array{
     *         question: string,
     *         schema: string,
     *         database_type: string,
     *         sql_query: string,
     *         confidence: float,
     *         complexity: string,
     *         estimated_rows: int,
     *         execution_time: float,
     *         tables_used: array<int, string>,
     *         joins: array<int, array{
     *             type: string,
     *             tables: array<int, string>,
     *             condition: string,
     *         }>,
     *         validation: array{
     *             syntax_valid: bool,
     *             schema_compatible: bool,
     *             warnings: array<int, string>,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function sqlGeneration(
        string $question,
        string $schema,
        string $databaseType = 'mysql',
        array $examples = [],
    ): array {
        try {
            $requestData = [
                'question' => $question,
                'schema' => $schema,
                'database_type' => $databaseType,
                'examples' => $examples,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/sql/generate", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $result = $data['sql_generation'] ?? [];

            return [
                'success' => true,
                'sql_generation' => [
                    'question' => $question,
                    'schema' => $schema,
                    'database_type' => $databaseType,
                    'sql_query' => $result['sql_query'] ?? '',
                    'confidence' => $result['confidence'] ?? 0.0,
                    'complexity' => $result['complexity'] ?? 'simple',
                    'estimated_rows' => $result['estimated_rows'] ?? 0,
                    'execution_time' => $result['execution_time'] ?? 0.0,
                    'tables_used' => $result['tables_used'] ?? [],
                    'joins' => array_map(fn ($join) => [
                        'type' => $join['type'] ?? '',
                        'tables' => $join['tables'] ?? [],
                        'condition' => $join['condition'] ?? '',
                    ], $result['joins'] ?? []),
                    'validation' => [
                        'syntax_valid' => $result['validation']['syntax_valid'] ?? false,
                        'schema_compatible' => $result['validation']['schema_compatible'] ?? false,
                        'warnings' => $result['validation']['warnings'] ?? [],
                    ],
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'sql_generation' => [
                    'question' => $question,
                    'schema' => $schema,
                    'database_type' => $databaseType,
                    'sql_query' => '',
                    'confidence' => 0.0,
                    'complexity' => 'simple',
                    'estimated_rows' => 0,
                    'execution_time' => 0.0,
                    'tables_used' => [],
                    'joins' => [],
                    'validation' => [
                        'syntax_valid' => false,
                        'schema_compatible' => false,
                        'warnings' => [],
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze database schema.
     *
     * @param string               $schema          Database schema
     * @param string               $databaseType    Database type
     * @param array<string, mixed> $analysisOptions Analysis options
     *
     * @return array{
     *     success: bool,
     *     schema_analysis: array{
     *         schema: string,
     *         database_type: string,
     *         tables: array<int, array{
     *             name: string,
     *             columns: array<int, array{
     *                 name: string,
     *                 type: string,
     *                 nullable: bool,
     *                 primary_key: bool,
     *                 foreign_key: array{
     *                     table: string,
     *                     column: string,
     *                 }|null,
     *             }>,
     *             indexes: array<int, array{
     *                 name: string,
     *                 columns: array<int, string>,
     *                 unique: bool,
     *             }>,
     *             relationships: array<int, array{
     *                 type: string,
     *                 target_table: string,
     *                 columns: array<string, string>,
     *             }>,
     *         }>,
     *         relationships: array<int, array{
     *             from_table: string,
     *             to_table: string,
     *             type: string,
     *             columns: array<string, string>,
     *         }>,
     *         insights: array<int, string>,
     *         recommendations: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function schemaAnalysis(
        string $schema,
        string $databaseType = 'mysql',
        array $analysisOptions = [],
    ): array {
        try {
            $requestData = [
                'schema' => $schema,
                'database_type' => $databaseType,
                'analysis_options' => $analysisOptions,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/schema/analyze", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $result = $data['schema_analysis'] ?? [];

            return [
                'success' => true,
                'schema_analysis' => [
                    'schema' => $schema,
                    'database_type' => $databaseType,
                    'tables' => array_map(fn ($table) => [
                        'name' => $table['name'] ?? '',
                        'columns' => array_map(fn ($column) => [
                            'name' => $column['name'] ?? '',
                            'type' => $column['type'] ?? '',
                            'nullable' => $column['nullable'] ?? false,
                            'primary_key' => $column['primary_key'] ?? false,
                            'foreign_key' => $column['foreign_key'] ?? null,
                        ], $table['columns'] ?? []),
                        'indexes' => array_map(fn ($index) => [
                            'name' => $index['name'] ?? '',
                            'columns' => $index['columns'] ?? [],
                            'unique' => $index['unique'] ?? false,
                        ], $table['indexes'] ?? []),
                        'relationships' => array_map(fn ($rel) => [
                            'type' => $rel['type'] ?? '',
                            'target_table' => $rel['target_table'] ?? '',
                            'columns' => $rel['columns'] ?? [],
                        ], $table['relationships'] ?? []),
                    ], $result['tables'] ?? []),
                    'relationships' => array_map(fn ($rel) => [
                        'from_table' => $rel['from_table'] ?? '',
                        'to_table' => $rel['to_table'] ?? '',
                        'type' => $rel['type'] ?? '',
                        'columns' => $rel['columns'] ?? [],
                    ], $result['relationships'] ?? []),
                    'insights' => $result['insights'] ?? [],
                    'recommendations' => $result['recommendations'] ?? [],
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'schema_analysis' => [
                    'schema' => $schema,
                    'database_type' => $databaseType,
                    'tables' => [],
                    'relationships' => [],
                    'insights' => [],
                    'recommendations' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Explain SQL queries.
     *
     * @param string $sqlQuery        SQL query to explain
     * @param string $databaseType    Database type
     * @param string $explanationType Type of explanation
     *
     * @return array{
     *     success: bool,
     *     query_explanation: array{
     *         sql_query: string,
     *         database_type: string,
     *         explanation_type: string,
     *         explanation: string,
     *         purpose: string,
     *         steps: array<int, array{
     *             step: int,
     *             operation: string,
     *             description: string,
     *             tables_involved: array<int, string>,
     *         }>,
     *         complexity: string,
     *         performance_notes: array<int, string>,
     *         business_impact: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function queryExplanation(
        string $sqlQuery,
        string $databaseType = 'mysql',
        string $explanationType = 'detailed',
    ): array {
        try {
            $requestData = [
                'sql_query' => $sqlQuery,
                'database_type' => $databaseType,
                'explanation_type' => $explanationType,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/query/explain", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $result = $data['query_explanation'] ?? [];

            return [
                'success' => true,
                'query_explanation' => [
                    'sql_query' => $sqlQuery,
                    'database_type' => $databaseType,
                    'explanation_type' => $explanationType,
                    'explanation' => $result['explanation'] ?? '',
                    'purpose' => $result['purpose'] ?? '',
                    'steps' => array_map(fn ($step) => [
                        'step' => $step['step'] ?? 0,
                        'operation' => $step['operation'] ?? '',
                        'description' => $step['description'] ?? '',
                        'tables_involved' => $step['tables_involved'] ?? [],
                    ], $result['steps'] ?? []),
                    'complexity' => $result['complexity'] ?? 'simple',
                    'performance_notes' => $result['performance_notes'] ?? [],
                    'business_impact' => $result['business_impact'] ?? '',
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'query_explanation' => [
                    'sql_query' => $sqlQuery,
                    'database_type' => $databaseType,
                    'explanation_type' => $explanationType,
                    'explanation' => '',
                    'purpose' => '',
                    'steps' => [],
                    'complexity' => 'simple',
                    'performance_notes' => [],
                    'business_impact' => '',
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate data visualizations.
     *
     * @param array<int, array<string, mixed>> $data              Data to visualize
     * @param string                           $visualizationType Type of visualization
     * @param array<string, mixed>             $options           Visualization options
     *
     * @return array{
     *     success: bool,
     *     data_visualization: array{
     *         data: array<int, array<string, mixed>>,
     *         visualization_type: string,
     *         chart_config: array<string, mixed>,
     *         insights: array<int, string>,
     *         recommendations: array<int, string>,
     *         chart_url: string,
     *         embed_code: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function dataVisualization(
        array $data,
        string $visualizationType = 'bar',
        array $options = [],
    ): array {
        try {
            $requestData = [
                'data' => $data,
                'visualization_type' => $visualizationType,
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/visualization/generate", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $result = $data['data_visualization'] ?? [];

            return [
                'success' => true,
                'data_visualization' => [
                    'data' => $data,
                    'visualization_type' => $visualizationType,
                    'chart_config' => $result['chart_config'] ?? [],
                    'insights' => $result['insights'] ?? [],
                    'recommendations' => $result['recommendations'] ?? [],
                    'chart_url' => $result['chart_url'] ?? '',
                    'embed_code' => $result['embed_code'] ?? '',
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data_visualization' => [
                    'data' => $data,
                    'visualization_type' => $visualizationType,
                    'chart_config' => [],
                    'insights' => [],
                    'recommendations' => [],
                    'chart_url' => '',
                    'embed_code' => '',
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate natural language questions.
     *
     * @param string               $schema       Database schema
     * @param string               $databaseType Database type
     * @param array<string, mixed> $context      Context for question generation
     *
     * @return array{
     *     success: bool,
     *     nlq_generation: array{
     *         schema: string,
     *         database_type: string,
     *         questions: array<int, array{
     *             question: string,
     *             complexity: string,
     *             category: string,
     *             sql_example: string,
     *             business_value: string,
     *         }>,
     *         categories: array<string, int>,
     *         difficulty_distribution: array<string, int>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function nlqGeneration(
        string $schema,
        string $databaseType = 'mysql',
        array $context = [],
    ): array {
        try {
            $requestData = [
                'schema' => $schema,
                'database_type' => $databaseType,
                'context' => $context,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/nlq/generate", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $result = $data['nlq_generation'] ?? [];

            return [
                'success' => true,
                'nlq_generation' => [
                    'schema' => $schema,
                    'database_type' => $databaseType,
                    'questions' => array_map(fn ($question) => [
                        'question' => $question['question'] ?? '',
                        'complexity' => $question['complexity'] ?? 'simple',
                        'category' => $question['category'] ?? '',
                        'sql_example' => $question['sql_example'] ?? '',
                        'business_value' => $question['business_value'] ?? '',
                    ], $result['questions'] ?? []),
                    'categories' => $result['categories'] ?? [],
                    'difficulty_distribution' => $result['difficulty_distribution'] ?? [],
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'nlq_generation' => [
                    'schema' => $schema,
                    'database_type' => $databaseType,
                    'questions' => [],
                    'categories' => [],
                    'difficulty_distribution' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Optimize queries.
     *
     * @param string               $sqlQuery            SQL query to optimize
     * @param string               $databaseType        Database type
     * @param array<string, mixed> $optimizationOptions Optimization options
     *
     * @return array{
     *     success: bool,
     *     query_optimization: array{
     *         original_query: string,
     *         optimized_query: string,
     *         database_type: string,
     *         improvements: array<int, array{
     *             type: string,
     *             description: string,
     *             impact: string,
     *             before: string,
     *             after: string,
     *         }>,
     *         performance_metrics: array{
     *             original_time: float,
     *             optimized_time: float,
     *             improvement_percentage: float,
     *         },
     *         recommendations: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function queryOptimization(
        string $sqlQuery,
        string $databaseType = 'mysql',
        array $optimizationOptions = [],
    ): array {
        try {
            $requestData = [
                'sql_query' => $sqlQuery,
                'database_type' => $databaseType,
                'optimization_options' => $optimizationOptions,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/query/optimize", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $result = $data['query_optimization'] ?? [];

            return [
                'success' => true,
                'query_optimization' => [
                    'original_query' => $sqlQuery,
                    'optimized_query' => $result['optimized_query'] ?? '',
                    'database_type' => $databaseType,
                    'improvements' => array_map(fn ($improvement) => [
                        'type' => $improvement['type'] ?? '',
                        'description' => $improvement['description'] ?? '',
                        'impact' => $improvement['impact'] ?? '',
                        'before' => $improvement['before'] ?? '',
                        'after' => $improvement['after'] ?? '',
                    ], $result['improvements'] ?? []),
                    'performance_metrics' => [
                        'original_time' => $result['performance_metrics']['original_time'] ?? 0.0,
                        'optimized_time' => $result['performance_metrics']['optimized_time'] ?? 0.0,
                        'improvement_percentage' => $result['performance_metrics']['improvement_percentage'] ?? 0.0,
                    ],
                    'recommendations' => $result['recommendations'] ?? [],
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'query_optimization' => [
                    'original_query' => $sqlQuery,
                    'optimized_query' => '',
                    'database_type' => $databaseType,
                    'improvements' => [],
                    'performance_metrics' => [
                        'original_time' => 0.0,
                        'optimized_time' => 0.0,
                        'improvement_percentage' => 0.0,
                    ],
                    'recommendations' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create data stories.
     *
     * @param array<int, array<string, mixed>> $data      Data for storytelling
     * @param string                           $storyType Type of story to create
     * @param array<string, mixed>             $options   Story options
     *
     * @return array{
     *     success: bool,
     *     data_storytelling: array{
     *         data: array<int, array<string, mixed>>,
     *         story_type: string,
     *         story: array{
     *             title: string,
     *             narrative: string,
     *             key_insights: array<int, string>,
     *             visualizations: array<int, array{
     *                 type: string,
     *                 title: string,
     *                 description: string,
     *                 chart_url: string,
     *             }>,
     *             recommendations: array<int, string>,
     *             conclusion: string,
     *         },
     *         story_url: string,
     *         embed_code: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function dataStorytelling(
        array $data,
        string $storyType = 'analytical',
        array $options = [],
    ): array {
        try {
            $requestData = [
                'data' => $data,
                'story_type' => $storyType,
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/storytelling/create", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $result = $responseData['data_storytelling'] ?? [];

            return [
                'success' => true,
                'data_storytelling' => [
                    'data' => $data,
                    'story_type' => $storyType,
                    'story' => [
                        'title' => $result['story']['title'] ?? '',
                        'narrative' => $result['story']['narrative'] ?? '',
                        'key_insights' => $result['story']['key_insights'] ?? [],
                        'visualizations' => array_map(fn ($viz) => [
                            'type' => $viz['type'] ?? '',
                            'title' => $viz['title'] ?? '',
                            'description' => $viz['description'] ?? '',
                            'chart_url' => $viz['chart_url'] ?? '',
                        ], $result['story']['visualizations'] ?? []),
                        'recommendations' => $result['story']['recommendations'] ?? [],
                        'conclusion' => $result['story']['conclusion'] ?? '',
                    ],
                    'story_url' => $result['story_url'] ?? '',
                    'embed_code' => $result['embed_code'] ?? '',
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data_storytelling' => [
                    'data' => $data,
                    'story_type' => $storyType,
                    'story' => [
                        'title' => '',
                        'narrative' => '',
                        'key_insights' => [],
                        'visualizations' => [],
                        'recommendations' => [],
                        'conclusion' => '',
                    ],
                    'story_url' => '',
                    'embed_code' => '',
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}

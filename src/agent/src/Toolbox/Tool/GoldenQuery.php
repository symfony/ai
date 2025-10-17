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
#[AsTool('golden_query_execute', 'Tool that executes SQL queries using Golden Query')]
#[AsTool('golden_query_explain', 'Tool that explains SQL queries', method: 'explain')]
#[AsTool('golden_query_optimize', 'Tool that optimizes SQL queries', method: 'optimize')]
#[AsTool('golden_query_schema', 'Tool that retrieves database schema', method: 'getSchema')]
#[AsTool('golden_query_validate', 'Tool that validates SQL queries', method: 'validate')]
#[AsTool('golden_query_convert', 'Tool that converts SQL between dialects', method: 'convert')]
#[AsTool('golden_query_analyze', 'Tool that analyzes query performance', method: 'analyze')]
#[AsTool('golden_query_suggest', 'Tool that suggests query improvements', method: 'suggest')]
final readonly class GoldenQuery
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.goldenquery.com/v1',
        private array $options = [],
    ) {
    }

    /**
     * Execute SQL query using Golden Query.
     *
     * @param string               $query        SQL query to execute
     * @param string               $databaseType Database type (mysql, postgresql, sqlite, mssql)
     * @param array<string, mixed> $parameters   Query parameters
     * @param bool                 $explain      Whether to include query explanation
     *
     * @return array{
     *     success: bool,
     *     execution: array{
     *         query: string,
     *         database_type: string,
     *         result: array<int, array<string, mixed>>,
     *         columns: array<int, array{
     *             name: string,
     *             type: string,
     *             nullable: bool,
     *         }>,
     *         row_count: int,
     *         execution_time: float,
     *         explain_plan: array<string, mixed>,
     *         warnings: array<int, string>,
     *         parameters: array<string, mixed>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $query,
        string $databaseType = 'mysql',
        array $parameters = [],
        bool $explain = false,
    ): array {
        try {
            $requestData = [
                'query' => $query,
                'database_type' => $databaseType,
                'parameters' => $parameters,
                'explain' => $explain,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/query/execute", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $execution = $data['execution'] ?? [];

            return [
                'success' => true,
                'execution' => [
                    'query' => $query,
                    'database_type' => $databaseType,
                    'result' => $execution['result'] ?? [],
                    'columns' => array_map(fn ($column) => [
                        'name' => $column['name'] ?? '',
                        'type' => $column['type'] ?? '',
                        'nullable' => $column['nullable'] ?? false,
                    ], $execution['columns'] ?? []),
                    'row_count' => $execution['row_count'] ?? 0,
                    'execution_time' => $execution['execution_time'] ?? 0.0,
                    'explain_plan' => $explain ? ($execution['explain_plan'] ?? []) : [],
                    'warnings' => $execution['warnings'] ?? [],
                    'parameters' => $parameters,
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'execution' => [
                    'query' => $query,
                    'database_type' => $databaseType,
                    'result' => [],
                    'columns' => [],
                    'row_count' => 0,
                    'execution_time' => 0.0,
                    'explain_plan' => [],
                    'warnings' => [],
                    'parameters' => $parameters,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Explain SQL query.
     *
     * @param string $query        SQL query to explain
     * @param string $databaseType Database type
     * @param string $explainType  Type of explanation (detailed, simple, performance)
     *
     * @return array{
     *     success: bool,
     *     explanation: array{
     *         query: string,
     *         database_type: string,
     *         explain_type: string,
     *         explanation: string,
     *         steps: array<int, array{
     *             step: int,
     *             operation: string,
     *             description: string,
     *             cost: float,
     *             rows: int,
     *         }>,
     *         complexity: string,
     *         recommendations: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function explain(
        string $query,
        string $databaseType = 'mysql',
        string $explainType = 'detailed',
    ): array {
        try {
            $requestData = [
                'query' => $query,
                'database_type' => $databaseType,
                'explain_type' => $explainType,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/query/explain", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $explanation = $data['explanation'] ?? [];

            return [
                'success' => true,
                'explanation' => [
                    'query' => $query,
                    'database_type' => $databaseType,
                    'explain_type' => $explainType,
                    'explanation' => $explanation['explanation'] ?? '',
                    'steps' => array_map(fn ($step) => [
                        'step' => $step['step'] ?? 0,
                        'operation' => $step['operation'] ?? '',
                        'description' => $step['description'] ?? '',
                        'cost' => $step['cost'] ?? 0.0,
                        'rows' => $step['rows'] ?? 0,
                    ], $explanation['steps'] ?? []),
                    'complexity' => $explanation['complexity'] ?? 'unknown',
                    'recommendations' => $explanation['recommendations'] ?? [],
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'explanation' => [
                    'query' => $query,
                    'database_type' => $databaseType,
                    'explain_type' => $explainType,
                    'explanation' => '',
                    'steps' => [],
                    'complexity' => 'unknown',
                    'recommendations' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Optimize SQL query.
     *
     * @param string               $query               SQL query to optimize
     * @param string               $databaseType        Database type
     * @param array<string, mixed> $optimizationOptions Optimization options
     *
     * @return array{
     *     success: bool,
     *     optimization: array{
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
     *         performance_estimate: array{
     *             original_time: float,
     *             optimized_time: float,
     *             improvement_percentage: float,
     *         },
     *         warnings: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function optimize(
        string $query,
        string $databaseType = 'mysql',
        array $optimizationOptions = [],
    ): array {
        try {
            $requestData = [
                'query' => $query,
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
            $optimization = $data['optimization'] ?? [];

            return [
                'success' => true,
                'optimization' => [
                    'original_query' => $query,
                    'optimized_query' => $optimization['optimized_query'] ?? '',
                    'database_type' => $databaseType,
                    'improvements' => array_map(fn ($improvement) => [
                        'type' => $improvement['type'] ?? '',
                        'description' => $improvement['description'] ?? '',
                        'impact' => $improvement['impact'] ?? '',
                        'before' => $improvement['before'] ?? '',
                        'after' => $improvement['after'] ?? '',
                    ], $optimization['improvements'] ?? []),
                    'performance_estimate' => [
                        'original_time' => $optimization['performance_estimate']['original_time'] ?? 0.0,
                        'optimized_time' => $optimization['performance_estimate']['optimized_time'] ?? 0.0,
                        'improvement_percentage' => $optimization['performance_estimate']['improvement_percentage'] ?? 0.0,
                    ],
                    'warnings' => $optimization['warnings'] ?? [],
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'optimization' => [
                    'original_query' => $query,
                    'optimized_query' => '',
                    'database_type' => $databaseType,
                    'improvements' => [],
                    'performance_estimate' => [
                        'original_time' => 0.0,
                        'optimized_time' => 0.0,
                        'improvement_percentage' => 0.0,
                    ],
                    'warnings' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get database schema.
     *
     * @param string $databaseType Database type
     * @param string $schemaName   Schema name (optional)
     *
     * @return array{
     *     success: bool,
     *     schema: array{
     *         database_type: string,
     *         schema_name: string,
     *         tables: array<int, array{
     *             name: string,
     *             columns: array<int, array{
     *                 name: string,
     *                 type: string,
     *                 nullable: bool,
     *                 default_value: mixed,
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
     *             row_count: int,
     *         }>,
     *         relationships: array<int, array{
     *             from_table: string,
     *             from_column: string,
     *             to_table: string,
     *             to_column: string,
     *             type: string,
     *         }>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function getSchema(
        string $databaseType = 'mysql',
        string $schemaName = '',
    ): array {
        try {
            $requestData = [
                'database_type' => $databaseType,
                'schema_name' => $schemaName,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/schema", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $schema = $data['schema'] ?? [];

            return [
                'success' => true,
                'schema' => [
                    'database_type' => $databaseType,
                    'schema_name' => $schema['schema_name'] ?? $schemaName,
                    'tables' => array_map(fn ($table) => [
                        'name' => $table['name'] ?? '',
                        'columns' => array_map(fn ($column) => [
                            'name' => $column['name'] ?? '',
                            'type' => $column['type'] ?? '',
                            'nullable' => $column['nullable'] ?? false,
                            'default_value' => $column['default_value'] ?? null,
                            'primary_key' => $column['primary_key'] ?? false,
                            'foreign_key' => $column['foreign_key'] ?? null,
                        ], $table['columns'] ?? []),
                        'indexes' => array_map(fn ($index) => [
                            'name' => $index['name'] ?? '',
                            'columns' => $index['columns'] ?? [],
                            'unique' => $index['unique'] ?? false,
                        ], $table['indexes'] ?? []),
                        'row_count' => $table['row_count'] ?? 0,
                    ], $schema['tables'] ?? []),
                    'relationships' => array_map(fn ($rel) => [
                        'from_table' => $rel['from_table'] ?? '',
                        'from_column' => $rel['from_column'] ?? '',
                        'to_table' => $rel['to_table'] ?? '',
                        'to_column' => $rel['to_column'] ?? '',
                        'type' => $rel['type'] ?? '',
                    ], $schema['relationships'] ?? []),
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'schema' => [
                    'database_type' => $databaseType,
                    'schema_name' => $schemaName,
                    'tables' => [],
                    'relationships' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate SQL query.
     *
     * @param string $query        SQL query to validate
     * @param string $databaseType Database type
     * @param bool   $strict       Whether to use strict validation
     *
     * @return array{
     *     success: bool,
     *     validation: array{
     *         query: string,
     *         database_type: string,
     *         is_valid: bool,
     *         errors: array<int, array{
     *             line: int,
     *             column: int,
     *             message: string,
     *             severity: string,
     *         }>,
     *         warnings: array<int, array{
     *             line: int,
     *             column: int,
     *             message: string,
     *             severity: string,
     *         }>,
     *         syntax_score: float,
     *         performance_score: float,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function validate(
        string $query,
        string $databaseType = 'mysql',
        bool $strict = false,
    ): array {
        try {
            $requestData = [
                'query' => $query,
                'database_type' => $databaseType,
                'strict' => $strict,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/query/validate", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $validation = $data['validation'] ?? [];

            return [
                'success' => true,
                'validation' => [
                    'query' => $query,
                    'database_type' => $databaseType,
                    'is_valid' => $validation['is_valid'] ?? false,
                    'errors' => array_map(fn ($error) => [
                        'line' => $error['line'] ?? 0,
                        'column' => $error['column'] ?? 0,
                        'message' => $error['message'] ?? '',
                        'severity' => $error['severity'] ?? 'error',
                    ], $validation['errors'] ?? []),
                    'warnings' => array_map(fn ($warning) => [
                        'line' => $warning['line'] ?? 0,
                        'column' => $warning['column'] ?? 0,
                        'message' => $warning['message'] ?? '',
                        'severity' => $warning['severity'] ?? 'warning',
                    ], $validation['warnings'] ?? []),
                    'syntax_score' => $validation['syntax_score'] ?? 0.0,
                    'performance_score' => $validation['performance_score'] ?? 0.0,
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'validation' => [
                    'query' => $query,
                    'database_type' => $databaseType,
                    'is_valid' => false,
                    'errors' => [],
                    'warnings' => [],
                    'syntax_score' => 0.0,
                    'performance_score' => 0.0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Convert SQL between dialects.
     *
     * @param string $query             SQL query to convert
     * @param string $fromDialect       Source SQL dialect
     * @param string $toDialect         Target SQL dialect
     * @param bool   $preserveSemantics Whether to preserve query semantics
     *
     * @return array{
     *     success: bool,
     *     conversion: array{
     *         original_query: string,
     *         converted_query: string,
     *         from_dialect: string,
     *         to_dialect: string,
     *         changes: array<int, array{
     *             type: string,
     *             original: string,
     *             converted: string,
     *             reason: string,
     *         }>,
     *         compatibility_score: float,
     *         warnings: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function convert(
        string $query,
        string $fromDialect = 'mysql',
        string $toDialect = 'postgresql',
        bool $preserveSemantics = true,
    ): array {
        try {
            $requestData = [
                'query' => $query,
                'from_dialect' => $fromDialect,
                'to_dialect' => $toDialect,
                'preserve_semantics' => $preserveSemantics,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/query/convert", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $conversion = $data['conversion'] ?? [];

            return [
                'success' => true,
                'conversion' => [
                    'original_query' => $query,
                    'converted_query' => $conversion['converted_query'] ?? '',
                    'from_dialect' => $fromDialect,
                    'to_dialect' => $toDialect,
                    'changes' => array_map(fn ($change) => [
                        'type' => $change['type'] ?? '',
                        'original' => $change['original'] ?? '',
                        'converted' => $change['converted'] ?? '',
                        'reason' => $change['reason'] ?? '',
                    ], $conversion['changes'] ?? []),
                    'compatibility_score' => $conversion['compatibility_score'] ?? 0.0,
                    'warnings' => $conversion['warnings'] ?? [],
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'conversion' => [
                    'original_query' => $query,
                    'converted_query' => '',
                    'from_dialect' => $fromDialect,
                    'to_dialect' => $toDialect,
                    'changes' => [],
                    'compatibility_score' => 0.0,
                    'warnings' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze query performance.
     *
     * @param string               $query           SQL query to analyze
     * @param string               $databaseType    Database type
     * @param array<string, mixed> $analysisOptions Analysis options
     *
     * @return array{
     *     success: bool,
     *     analysis: array{
     *         query: string,
     *         database_type: string,
     *         performance_metrics: array{
     *             execution_time: float,
     *             cost: float,
     *             rows_examined: int,
     *             rows_returned: int,
     *             io_operations: int,
     *         },
     *         bottlenecks: array<int, array{
     *             type: string,
     *             description: string,
     *             impact: string,
     *             suggestion: string,
     *         }>,
     *         recommendations: array<int, string>,
     *         complexity_analysis: array{
     *             time_complexity: string,
     *             space_complexity: string,
     *             join_complexity: string,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function analyze(
        string $query,
        string $databaseType = 'mysql',
        array $analysisOptions = [],
    ): array {
        try {
            $requestData = [
                'query' => $query,
                'database_type' => $databaseType,
                'analysis_options' => $analysisOptions,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/query/analyze", [
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
                    'query' => $query,
                    'database_type' => $databaseType,
                    'performance_metrics' => [
                        'execution_time' => $analysis['performance_metrics']['execution_time'] ?? 0.0,
                        'cost' => $analysis['performance_metrics']['cost'] ?? 0.0,
                        'rows_examined' => $analysis['performance_metrics']['rows_examined'] ?? 0,
                        'rows_returned' => $analysis['performance_metrics']['rows_returned'] ?? 0,
                        'io_operations' => $analysis['performance_metrics']['io_operations'] ?? 0,
                    ],
                    'bottlenecks' => array_map(fn ($bottleneck) => [
                        'type' => $bottleneck['type'] ?? '',
                        'description' => $bottleneck['description'] ?? '',
                        'impact' => $bottleneck['impact'] ?? '',
                        'suggestion' => $bottleneck['suggestion'] ?? '',
                    ], $analysis['bottlenecks'] ?? []),
                    'recommendations' => $analysis['recommendations'] ?? [],
                    'complexity_analysis' => [
                        'time_complexity' => $analysis['complexity_analysis']['time_complexity'] ?? 'unknown',
                        'space_complexity' => $analysis['complexity_analysis']['space_complexity'] ?? 'unknown',
                        'join_complexity' => $analysis['complexity_analysis']['join_complexity'] ?? 'unknown',
                    ],
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'analysis' => [
                    'query' => $query,
                    'database_type' => $databaseType,
                    'performance_metrics' => [
                        'execution_time' => 0.0,
                        'cost' => 0.0,
                        'rows_examined' => 0,
                        'rows_returned' => 0,
                        'io_operations' => 0,
                    ],
                    'bottlenecks' => [],
                    'recommendations' => [],
                    'complexity_analysis' => [
                        'time_complexity' => 'unknown',
                        'space_complexity' => 'unknown',
                        'join_complexity' => 'unknown',
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Suggest query improvements.
     *
     * @param string               $query        SQL query to improve
     * @param string               $databaseType Database type
     * @param array<string, mixed> $context      Context information
     *
     * @return array{
     *     success: bool,
     *     suggestions: array{
     *         query: string,
     *         database_type: string,
     *         improvements: array<int, array{
     *             type: string,
     *             description: string,
     *             priority: string,
     *             impact: string,
     *             example: string,
     *         }>,
     *         alternative_queries: array<int, array{
     *             query: string,
     *             description: string,
     *             advantages: array<int, string>,
     *         }>,
     *         best_practices: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function suggest(
        string $query,
        string $databaseType = 'mysql',
        array $context = [],
    ): array {
        try {
            $requestData = [
                'query' => $query,
                'database_type' => $databaseType,
                'context' => $context,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/query/suggest", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $suggestions = $data['suggestions'] ?? [];

            return [
                'success' => true,
                'suggestions' => [
                    'query' => $query,
                    'database_type' => $databaseType,
                    'improvements' => array_map(fn ($improvement) => [
                        'type' => $improvement['type'] ?? '',
                        'description' => $improvement['description'] ?? '',
                        'priority' => $improvement['priority'] ?? 'medium',
                        'impact' => $improvement['impact'] ?? '',
                        'example' => $improvement['example'] ?? '',
                    ], $suggestions['improvements'] ?? []),
                    'alternative_queries' => array_map(fn ($alt) => [
                        'query' => $alt['query'] ?? '',
                        'description' => $alt['description'] ?? '',
                        'advantages' => $alt['advantages'] ?? [],
                    ], $suggestions['alternative_queries'] ?? []),
                    'best_practices' => $suggestions['best_practices'] ?? [],
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'suggestions' => [
                    'query' => $query,
                    'database_type' => $databaseType,
                    'improvements' => [],
                    'alternative_queries' => [],
                    'best_practices' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}

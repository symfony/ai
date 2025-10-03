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
#[AsTool('riza_analyze', 'Tool that analyzes data using Riza')]
#[AsTool('riza_predict', 'Tool that makes predictions', method: 'predict')]
#[AsTool('riza_train', 'Tool that trains models', method: 'train')]
#[AsTool('riza_evaluate', 'Tool that evaluates models', method: 'evaluate')]
#[AsTool('riza_deploy', 'Tool that deploys models', method: 'deploy')]
#[AsTool('riza_optimize', 'Tool that optimizes models', method: 'optimize')]
#[AsTool('riza_visualize', 'Tool that creates visualizations', method: 'visualize')]
#[AsTool('riza_export', 'Tool that exports results', method: 'export')]
final readonly class Riza
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.riza.ai/v1',
        private array $options = [],
    ) {
    }

    /**
     * Analyze data using Riza.
     *
     * @param array<int, array<string, mixed>> $data         Data to analyze
     * @param string                           $analysisType Type of analysis
     * @param array<string, mixed>             $options      Analysis options
     *
     * @return array{
     *     success: bool,
     *     analysis: array{
     *         data: array<int, array<string, mixed>>,
     *         analysis_type: string,
     *         results: array{
     *             summary: array{
     *                 total_records: int,
     *                 columns: array<int, string>,
     *                 data_types: array<string, string>,
     *                 missing_values: array<string, int>,
     *                 unique_values: array<string, int>,
     *             },
     *             statistics: array<string, array{
     *                 mean: float,
     *                 median: float,
     *                 std: float,
     *                 min: float,
     *                 max: float,
     *                 quartiles: array<float>,
     *             }>,
     *             correlations: array<string, array<string, float>>,
     *             insights: array<int, string>,
     *             recommendations: array<int, string>,
     *         },
     *         visualizations: array<int, array{
     *             type: string,
     *             title: string,
     *             url: string,
     *         }>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        array $data,
        string $analysisType = 'descriptive',
        array $options = [],
    ): array {
        try {
            $requestData = [
                'data' => $data,
                'analysis_type' => $analysisType,
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
                    'data' => $data,
                    'analysis_type' => $analysisType,
                    'results' => [
                        'summary' => [
                            'total_records' => $analysis['results']['summary']['total_records'] ?? \count($data),
                            'columns' => $analysis['results']['summary']['columns'] ?? [],
                            'data_types' => $analysis['results']['summary']['data_types'] ?? [],
                            'missing_values' => $analysis['results']['summary']['missing_values'] ?? [],
                            'unique_values' => $analysis['results']['summary']['unique_values'] ?? [],
                        ],
                        'statistics' => $analysis['results']['statistics'] ?? [],
                        'correlations' => $analysis['results']['correlations'] ?? [],
                        'insights' => $analysis['results']['insights'] ?? [],
                        'recommendations' => $analysis['results']['recommendations'] ?? [],
                    ],
                    'visualizations' => array_map(fn ($viz) => [
                        'type' => $viz['type'] ?? '',
                        'title' => $viz['title'] ?? '',
                        'url' => $viz['url'] ?? '',
                    ], $analysis['visualizations'] ?? []),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'analysis' => [
                    'data' => $data,
                    'analysis_type' => $analysisType,
                    'results' => [
                        'summary' => [
                            'total_records' => \count($data),
                            'columns' => [],
                            'data_types' => [],
                            'missing_values' => [],
                            'unique_values' => [],
                        ],
                        'statistics' => [],
                        'correlations' => [],
                        'insights' => [],
                        'recommendations' => [],
                    ],
                    'visualizations' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Make predictions.
     *
     * @param array<int, array<string, mixed>> $data              Data for prediction
     * @param string                           $modelType         Model type to use
     * @param array<string, mixed>             $modelParams       Model parameters
     * @param array<string, mixed>             $predictionOptions Prediction options
     *
     * @return array{
     *     success: bool,
     *     prediction: array{
     *         data: array<int, array<string, mixed>>,
     *         model_type: string,
     *         predictions: array<int, array{
     *             input: array<string, mixed>,
     *             prediction: mixed,
     *             confidence: float,
     *             probability: array<string, float>,
     *         }>,
     *         model_metrics: array{
     *             accuracy: float,
     *             precision: float,
     *             recall: float,
     *             f1_score: float,
     *             auc: float,
     *         },
     *         feature_importance: array<string, float>,
     *         prediction_interval: array{
     *             lower_bound: float,
     *             upper_bound: float,
     *             confidence_level: float,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function predict(
        array $data,
        string $modelType = 'classification',
        array $modelParams = [],
        array $predictionOptions = [],
    ): array {
        try {
            $requestData = [
                'data' => $data,
                'model_type' => $modelType,
                'model_params' => $modelParams,
                'prediction_options' => $predictionOptions,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/predict", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $prediction = $responseData['prediction'] ?? [];

            return [
                'success' => true,
                'prediction' => [
                    'data' => $data,
                    'model_type' => $modelType,
                    'predictions' => array_map(fn ($pred) => [
                        'input' => $pred['input'] ?? [],
                        'prediction' => $pred['prediction'] ?? null,
                        'confidence' => $pred['confidence'] ?? 0.0,
                        'probability' => $pred['probability'] ?? [],
                    ], $prediction['predictions'] ?? []),
                    'model_metrics' => [
                        'accuracy' => $prediction['model_metrics']['accuracy'] ?? 0.0,
                        'precision' => $prediction['model_metrics']['precision'] ?? 0.0,
                        'recall' => $prediction['model_metrics']['recall'] ?? 0.0,
                        'f1_score' => $prediction['model_metrics']['f1_score'] ?? 0.0,
                        'auc' => $prediction['model_metrics']['auc'] ?? 0.0,
                    ],
                    'feature_importance' => $prediction['feature_importance'] ?? [],
                    'prediction_interval' => [
                        'lower_bound' => $prediction['prediction_interval']['lower_bound'] ?? 0.0,
                        'upper_bound' => $prediction['prediction_interval']['upper_bound'] ?? 0.0,
                        'confidence_level' => $prediction['prediction_interval']['confidence_level'] ?? 0.95,
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'prediction' => [
                    'data' => $data,
                    'model_type' => $modelType,
                    'predictions' => [],
                    'model_metrics' => [
                        'accuracy' => 0.0,
                        'precision' => 0.0,
                        'recall' => 0.0,
                        'f1_score' => 0.0,
                        'auc' => 0.0,
                    ],
                    'feature_importance' => [],
                    'prediction_interval' => [
                        'lower_bound' => 0.0,
                        'upper_bound' => 0.0,
                        'confidence_level' => 0.95,
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Train models.
     *
     * @param array<int, array<string, mixed>> $trainingData   Training data
     * @param string                           $modelType      Model type to train
     * @param array<string, mixed>             $trainingParams Training parameters
     * @param array<string, mixed>             $validationData Validation data
     *
     * @return array{
     *     success: bool,
     *     training: array{
     *         training_data: array<int, array<string, mixed>>,
     *         model_type: string,
     *         model_id: string,
     *         training_results: array{
     *             epochs: int,
     *             final_loss: float,
     *             validation_loss: float,
     *             training_accuracy: float,
     *             validation_accuracy: float,
     *             training_time: float,
     *         },
     *         hyperparameters: array<string, mixed>,
     *         feature_importance: array<string, float>,
     *         model_performance: array{
     *             accuracy: float,
     *             precision: float,
     *             recall: float,
     *             f1_score: float,
     *             confusion_matrix: array<int, array<int, int>>,
     *         },
     *         model_url: string,
     *         training_plots: array<int, array{
     *             type: string,
     *             title: string,
     *             url: string,
     *         }>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function train(
        array $trainingData,
        string $modelType = 'classification',
        array $trainingParams = [],
        array $validationData = [],
    ): array {
        try {
            $requestData = [
                'training_data' => $trainingData,
                'model_type' => $modelType,
                'training_params' => $trainingParams,
                'validation_data' => $validationData,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/train", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $training = $responseData['training'] ?? [];

            return [
                'success' => true,
                'training' => [
                    'training_data' => $trainingData,
                    'model_type' => $modelType,
                    'model_id' => $training['model_id'] ?? '',
                    'training_results' => [
                        'epochs' => $training['training_results']['epochs'] ?? 0,
                        'final_loss' => $training['training_results']['final_loss'] ?? 0.0,
                        'validation_loss' => $training['training_results']['validation_loss'] ?? 0.0,
                        'training_accuracy' => $training['training_results']['training_accuracy'] ?? 0.0,
                        'validation_accuracy' => $training['training_results']['validation_accuracy'] ?? 0.0,
                        'training_time' => $training['training_results']['training_time'] ?? 0.0,
                    ],
                    'hyperparameters' => $training['hyperparameters'] ?? [],
                    'feature_importance' => $training['feature_importance'] ?? [],
                    'model_performance' => [
                        'accuracy' => $training['model_performance']['accuracy'] ?? 0.0,
                        'precision' => $training['model_performance']['precision'] ?? 0.0,
                        'recall' => $training['model_performance']['recall'] ?? 0.0,
                        'f1_score' => $training['model_performance']['f1_score'] ?? 0.0,
                        'confusion_matrix' => $training['model_performance']['confusion_matrix'] ?? [],
                    ],
                    'model_url' => $training['model_url'] ?? '',
                    'training_plots' => array_map(fn ($plot) => [
                        'type' => $plot['type'] ?? '',
                        'title' => $plot['title'] ?? '',
                        'url' => $plot['url'] ?? '',
                    ], $training['training_plots'] ?? []),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'training' => [
                    'training_data' => $trainingData,
                    'model_type' => $modelType,
                    'model_id' => '',
                    'training_results' => [
                        'epochs' => 0,
                        'final_loss' => 0.0,
                        'validation_loss' => 0.0,
                        'training_accuracy' => 0.0,
                        'validation_accuracy' => 0.0,
                        'training_time' => 0.0,
                    ],
                    'hyperparameters' => [],
                    'feature_importance' => [],
                    'model_performance' => [
                        'accuracy' => 0.0,
                        'precision' => 0.0,
                        'recall' => 0.0,
                        'f1_score' => 0.0,
                        'confusion_matrix' => [],
                    ],
                    'model_url' => '',
                    'training_plots' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Evaluate models.
     *
     * @param string                           $modelId           Model ID to evaluate
     * @param array<int, array<string, mixed>> $testData          Test data
     * @param array<string, mixed>             $evaluationMetrics Metrics to evaluate
     *
     * @return array{
     *     success: bool,
     *     evaluation: array{
     *         model_id: string,
     *         test_data: array<int, array<string, mixed>>,
     *         evaluation_metrics: array{
     *             accuracy: float,
     *             precision: float,
     *             recall: float,
     *             f1_score: float,
     *             auc: float,
     *             mse: float,
     *             rmse: float,
     *             mae: float,
     *             r2_score: float,
     *         },
     *         confusion_matrix: array<int, array<int, int>>,
     *         classification_report: array<string, array{
     *             precision: float,
     *             recall: float,
     *             f1_score: float,
     *             support: int,
     *         }>,
     *         feature_importance: array<string, float>,
     *         predictions: array<int, array{
     *             actual: mixed,
     *             predicted: mixed,
     *             confidence: float,
     *         }>,
     *         evaluation_plots: array<int, array{
     *             type: string,
     *             title: string,
     *             url: string,
     *         }>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function evaluate(
        string $modelId,
        array $testData,
        array $evaluationMetrics = ['accuracy', 'precision', 'recall', 'f1_score'],
    ): array {
        try {
            $requestData = [
                'model_id' => $modelId,
                'test_data' => $testData,
                'evaluation_metrics' => $evaluationMetrics,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/evaluate", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $evaluation = $responseData['evaluation'] ?? [];

            return [
                'success' => true,
                'evaluation' => [
                    'model_id' => $modelId,
                    'test_data' => $testData,
                    'evaluation_metrics' => [
                        'accuracy' => $evaluation['evaluation_metrics']['accuracy'] ?? 0.0,
                        'precision' => $evaluation['evaluation_metrics']['precision'] ?? 0.0,
                        'recall' => $evaluation['evaluation_metrics']['recall'] ?? 0.0,
                        'f1_score' => $evaluation['evaluation_metrics']['f1_score'] ?? 0.0,
                        'auc' => $evaluation['evaluation_metrics']['auc'] ?? 0.0,
                        'mse' => $evaluation['evaluation_metrics']['mse'] ?? 0.0,
                        'rmse' => $evaluation['evaluation_metrics']['rmse'] ?? 0.0,
                        'mae' => $evaluation['evaluation_metrics']['mae'] ?? 0.0,
                        'r2_score' => $evaluation['evaluation_metrics']['r2_score'] ?? 0.0,
                    ],
                    'confusion_matrix' => $evaluation['confusion_matrix'] ?? [],
                    'classification_report' => $evaluation['classification_report'] ?? [],
                    'feature_importance' => $evaluation['feature_importance'] ?? [],
                    'predictions' => array_map(fn ($pred) => [
                        'actual' => $pred['actual'] ?? null,
                        'predicted' => $pred['predicted'] ?? null,
                        'confidence' => $pred['confidence'] ?? 0.0,
                    ], $evaluation['predictions'] ?? []),
                    'evaluation_plots' => array_map(fn ($plot) => [
                        'type' => $plot['type'] ?? '',
                        'title' => $plot['title'] ?? '',
                        'url' => $plot['url'] ?? '',
                    ], $evaluation['evaluation_plots'] ?? []),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'evaluation' => [
                    'model_id' => $modelId,
                    'test_data' => $testData,
                    'evaluation_metrics' => [
                        'accuracy' => 0.0,
                        'precision' => 0.0,
                        'recall' => 0.0,
                        'f1_score' => 0.0,
                        'auc' => 0.0,
                        'mse' => 0.0,
                        'rmse' => 0.0,
                        'mae' => 0.0,
                        'r2_score' => 0.0,
                    ],
                    'confusion_matrix' => [],
                    'classification_report' => [],
                    'feature_importance' => [],
                    'predictions' => [],
                    'evaluation_plots' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Deploy models.
     *
     * @param string               $modelId          Model ID to deploy
     * @param string               $deploymentType   Deployment type
     * @param array<string, mixed> $deploymentConfig Deployment configuration
     *
     * @return array{
     *     success: bool,
     *     deployment: array{
     *         model_id: string,
     *         deployment_id: string,
     *         deployment_type: string,
     *         status: string,
     *         endpoint_url: string,
     *         api_key: string,
     *         deployment_config: array<string, mixed>,
     *         health_check: array{
     *             status: string,
     *             response_time: float,
     *             uptime: float,
     *         },
     *         scaling_config: array{
     *             min_instances: int,
     *             max_instances: int,
     *             target_cpu: float,
     *         },
     *         monitoring: array{
     *             metrics_url: string,
     *             logs_url: string,
     *             alerts_enabled: bool,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function deploy(
        string $modelId,
        string $deploymentType = 'api',
        array $deploymentConfig = [],
    ): array {
        try {
            $requestData = [
                'model_id' => $modelId,
                'deployment_type' => $deploymentType,
                'deployment_config' => $deploymentConfig,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/deploy", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $deployment = $responseData['deployment'] ?? [];

            return [
                'success' => true,
                'deployment' => [
                    'model_id' => $modelId,
                    'deployment_id' => $deployment['deployment_id'] ?? '',
                    'deployment_type' => $deploymentType,
                    'status' => $deployment['status'] ?? 'deployed',
                    'endpoint_url' => $deployment['endpoint_url'] ?? '',
                    'api_key' => $deployment['api_key'] ?? '',
                    'deployment_config' => $deploymentConfig,
                    'health_check' => [
                        'status' => $deployment['health_check']['status'] ?? 'healthy',
                        'response_time' => $deployment['health_check']['response_time'] ?? 0.0,
                        'uptime' => $deployment['health_check']['uptime'] ?? 0.0,
                    ],
                    'scaling_config' => [
                        'min_instances' => $deployment['scaling_config']['min_instances'] ?? 1,
                        'max_instances' => $deployment['scaling_config']['max_instances'] ?? 10,
                        'target_cpu' => $deployment['scaling_config']['target_cpu'] ?? 0.7,
                    ],
                    'monitoring' => [
                        'metrics_url' => $deployment['monitoring']['metrics_url'] ?? '',
                        'logs_url' => $deployment['monitoring']['logs_url'] ?? '',
                        'alerts_enabled' => $deployment['monitoring']['alerts_enabled'] ?? false,
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'deployment' => [
                    'model_id' => $modelId,
                    'deployment_id' => '',
                    'deployment_type' => $deploymentType,
                    'status' => 'failed',
                    'endpoint_url' => '',
                    'api_key' => '',
                    'deployment_config' => $deploymentConfig,
                    'health_check' => [
                        'status' => 'unhealthy',
                        'response_time' => 0.0,
                        'uptime' => 0.0,
                    ],
                    'scaling_config' => [
                        'min_instances' => 1,
                        'max_instances' => 10,
                        'target_cpu' => 0.7,
                    ],
                    'monitoring' => [
                        'metrics_url' => '',
                        'logs_url' => '',
                        'alerts_enabled' => false,
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Optimize models.
     *
     * @param string               $modelId             Model ID to optimize
     * @param array<string, mixed> $optimizationParams  Optimization parameters
     * @param array<string, mixed> $optimizationTargets Optimization targets
     *
     * @return array{
     *     success: bool,
     *     optimization: array{
     *         model_id: string,
     *         optimization_params: array<string, mixed>,
     *         optimization_targets: array<string, mixed>,
     *         optimized_model_id: string,
     *         optimization_results: array{
     *             original_performance: array<string, float>,
     *             optimized_performance: array<string, float>,
     *             improvement: array<string, float>,
     *             optimization_time: float,
     *         },
     *         hyperparameter_tuning: array{
     *             best_params: array<string, mixed>,
     *             search_space: array<string, mixed>,
     *             trials: int,
     *         },
     *         model_compression: array{
     *             original_size: float,
     *             compressed_size: float,
     *             compression_ratio: float,
     *             accuracy_loss: float,
     *         },
     *         optimization_plots: array<int, array{
     *             type: string,
     *             title: string,
     *             url: string,
     *         }>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function optimize(
        string $modelId,
        array $optimizationParams = [],
        array $optimizationTargets = ['accuracy', 'speed'],
    ): array {
        try {
            $requestData = [
                'model_id' => $modelId,
                'optimization_params' => $optimizationParams,
                'optimization_targets' => $optimizationTargets,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/optimize", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $optimization = $responseData['optimization'] ?? [];

            return [
                'success' => true,
                'optimization' => [
                    'model_id' => $modelId,
                    'optimization_params' => $optimizationParams,
                    'optimization_targets' => $optimizationTargets,
                    'optimized_model_id' => $optimization['optimized_model_id'] ?? '',
                    'optimization_results' => [
                        'original_performance' => $optimization['optimization_results']['original_performance'] ?? [],
                        'optimized_performance' => $optimization['optimization_results']['optimized_performance'] ?? [],
                        'improvement' => $optimization['optimization_results']['improvement'] ?? [],
                        'optimization_time' => $optimization['optimization_results']['optimization_time'] ?? 0.0,
                    ],
                    'hyperparameter_tuning' => [
                        'best_params' => $optimization['hyperparameter_tuning']['best_params'] ?? [],
                        'search_space' => $optimization['hyperparameter_tuning']['search_space'] ?? [],
                        'trials' => $optimization['hyperparameter_tuning']['trials'] ?? 0,
                    ],
                    'model_compression' => [
                        'original_size' => $optimization['model_compression']['original_size'] ?? 0.0,
                        'compressed_size' => $optimization['model_compression']['compressed_size'] ?? 0.0,
                        'compression_ratio' => $optimization['model_compression']['compression_ratio'] ?? 0.0,
                        'accuracy_loss' => $optimization['model_compression']['accuracy_loss'] ?? 0.0,
                    ],
                    'optimization_plots' => array_map(fn ($plot) => [
                        'type' => $plot['type'] ?? '',
                        'title' => $plot['title'] ?? '',
                        'url' => $plot['url'] ?? '',
                    ], $optimization['optimization_plots'] ?? []),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'optimization' => [
                    'model_id' => $modelId,
                    'optimization_params' => $optimizationParams,
                    'optimization_targets' => $optimizationTargets,
                    'optimized_model_id' => '',
                    'optimization_results' => [
                        'original_performance' => [],
                        'optimized_performance' => [],
                        'improvement' => [],
                        'optimization_time' => 0.0,
                    ],
                    'hyperparameter_tuning' => [
                        'best_params' => [],
                        'search_space' => [],
                        'trials' => 0,
                    ],
                    'model_compression' => [
                        'original_size' => 0.0,
                        'compressed_size' => 0.0,
                        'compression_ratio' => 0.0,
                        'accuracy_loss' => 0.0,
                    ],
                    'optimization_plots' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create visualizations.
     *
     * @param array<int, array<string, mixed>> $data              Data to visualize
     * @param string                           $visualizationType Type of visualization
     * @param array<string, mixed>             $options           Visualization options
     *
     * @return array{
     *     success: bool,
     *     visualization: array{
     *         data: array<int, array<string, mixed>>,
     *         visualization_type: string,
     *         charts: array<int, array{
     *             chart_id: string,
     *             type: string,
     *             title: string,
     *             url: string,
     *             embed_code: string,
     *         }>,
     *         dashboard_url: string,
     *         insights: array<int, string>,
     *         export_formats: array<string, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function visualize(
        array $data,
        string $visualizationType = 'dashboard',
        array $options = [],
    ): array {
        try {
            $requestData = [
                'data' => $data,
                'visualization_type' => $visualizationType,
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/visualize", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $visualization = $responseData['visualization'] ?? [];

            return [
                'success' => true,
                'visualization' => [
                    'data' => $data,
                    'visualization_type' => $visualizationType,
                    'charts' => array_map(fn ($chart) => [
                        'chart_id' => $chart['chart_id'] ?? '',
                        'type' => $chart['type'] ?? '',
                        'title' => $chart['title'] ?? '',
                        'url' => $chart['url'] ?? '',
                        'embed_code' => $chart['embed_code'] ?? '',
                    ], $visualization['charts'] ?? []),
                    'dashboard_url' => $visualization['dashboard_url'] ?? '',
                    'insights' => $visualization['insights'] ?? [],
                    'export_formats' => $visualization['export_formats'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'visualization' => [
                    'data' => $data,
                    'visualization_type' => $visualizationType,
                    'charts' => [],
                    'dashboard_url' => '',
                    'insights' => [],
                    'export_formats' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Export results.
     *
     * @param string               $exportId      Export ID or data identifier
     * @param string               $format        Export format
     * @param array<string, mixed> $exportOptions Export options
     *
     * @return array{
     *     success: bool,
     *     export: array{
     *         export_id: string,
     *         format: string,
     *         file_url: string,
     *         file_size: int,
     *         download_url: string,
     *         expires_at: string,
     *         metadata: array{
     *             created_at: string,
     *             record_count: int,
     *             columns: array<int, string>,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function export(
        string $exportId,
        string $format = 'csv',
        array $exportOptions = [],
    ): array {
        try {
            $requestData = [
                'export_id' => $exportId,
                'format' => $format,
                'export_options' => $exportOptions,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/export", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $export = $responseData['export'] ?? [];

            return [
                'success' => true,
                'export' => [
                    'export_id' => $exportId,
                    'format' => $format,
                    'file_url' => $export['file_url'] ?? '',
                    'file_size' => $export['file_size'] ?? 0,
                    'download_url' => $export['download_url'] ?? '',
                    'expires_at' => $export['expires_at'] ?? '',
                    'metadata' => [
                        'created_at' => $export['metadata']['created_at'] ?? date('c'),
                        'record_count' => $export['metadata']['record_count'] ?? 0,
                        'columns' => $export['metadata']['columns'] ?? [],
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'export' => [
                    'export_id' => $exportId,
                    'format' => $format,
                    'file_url' => '',
                    'file_size' => 0,
                    'download_url' => '',
                    'expires_at' => '',
                    'metadata' => [
                        'created_at' => date('c'),
                        'record_count' => 0,
                        'columns' => [],
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}

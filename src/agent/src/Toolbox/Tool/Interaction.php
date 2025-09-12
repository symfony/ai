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
#[AsTool('interaction_create', 'Tool that creates user interactions using Interaction API')]
#[AsTool('interaction_track', 'Tool that tracks user interactions', method: 'track')]
#[AsTool('interaction_analyze', 'Tool that analyzes user interactions', method: 'analyze')]
#[AsTool('interaction_survey', 'Tool that creates surveys', method: 'survey')]
#[AsTool('interaction_feedback', 'Tool that collects feedback', method: 'feedback')]
#[AsTool('interaction_engagement', 'Tool that measures engagement', method: 'engagement')]
#[AsTool('interaction_behavior', 'Tool that analyzes user behavior', method: 'behavior')]
#[AsTool('interaction_personalization', 'Tool that provides personalization', method: 'personalization')]
final readonly class Interaction
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.interaction.com/v1',
        private array $options = [],
    ) {
    }

    /**
     * Create user interactions using Interaction API.
     *
     * @param string               $userId          User ID
     * @param string               $interactionType Type of interaction
     * @param array<string, mixed> $data            Interaction data
     * @param array<string, mixed> $context         Interaction context
     *
     * @return array{
     *     success: bool,
     *     interaction: array{
     *         interaction_id: string,
     *         user_id: string,
     *         interaction_type: string,
     *         data: array<string, mixed>,
     *         context: array<string, mixed>,
     *         timestamp: string,
     *         session_id: string,
     *         device_info: array{
     *             type: string,
     *             os: string,
     *             browser: string,
     *         },
     *         location: array{
     *             country: string,
     *             region: string,
     *             city: string,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $userId,
        string $interactionType,
        array $data = [],
        array $context = [],
    ): array {
        try {
            $requestData = [
                'user_id' => $userId,
                'interaction_type' => $interactionType,
                'data' => $data,
                'context' => $context,
                'timestamp' => date('c'),
                'session_id' => $context['session_id'] ?? uniqid(),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/interactions", [
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
                    'interaction_id' => $interaction['interaction_id'] ?? '',
                    'user_id' => $userId,
                    'interaction_type' => $interactionType,
                    'data' => $data,
                    'context' => $context,
                    'timestamp' => $interaction['timestamp'] ?? date('c'),
                    'session_id' => $context['session_id'] ?? uniqid(),
                    'device_info' => [
                        'type' => $interaction['device_info']['type'] ?? 'unknown',
                        'os' => $interaction['device_info']['os'] ?? 'unknown',
                        'browser' => $interaction['device_info']['browser'] ?? 'unknown',
                    ],
                    'location' => [
                        'country' => $interaction['location']['country'] ?? 'unknown',
                        'region' => $interaction['location']['region'] ?? 'unknown',
                        'city' => $interaction['location']['city'] ?? 'unknown',
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'interaction' => [
                    'interaction_id' => '',
                    'user_id' => $userId,
                    'interaction_type' => $interactionType,
                    'data' => $data,
                    'context' => $context,
                    'timestamp' => date('c'),
                    'session_id' => $context['session_id'] ?? uniqid(),
                    'device_info' => [
                        'type' => 'unknown',
                        'os' => 'unknown',
                        'browser' => 'unknown',
                    ],
                    'location' => [
                        'country' => 'unknown',
                        'region' => 'unknown',
                        'city' => 'unknown',
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Track user interactions.
     *
     * @param string               $userId     User ID
     * @param string               $eventType  Event type
     * @param array<string, mixed> $properties Event properties
     * @param array<string, mixed> $metadata   Event metadata
     *
     * @return array{
     *     success: bool,
     *     tracking: array{
     *         event_id: string,
     *         user_id: string,
     *         event_type: string,
     *         properties: array<string, mixed>,
     *         metadata: array<string, mixed>,
     *         timestamp: string,
     *         session_id: string,
     *         funnel_step: string,
     *         conversion_value: float,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function track(
        string $userId,
        string $eventType,
        array $properties = [],
        array $metadata = [],
    ): array {
        try {
            $requestData = [
                'user_id' => $userId,
                'event_type' => $eventType,
                'properties' => $properties,
                'metadata' => $metadata,
                'timestamp' => date('c'),
                'session_id' => $metadata['session_id'] ?? uniqid(),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/track", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $tracking = $responseData['tracking'] ?? [];

            return [
                'success' => true,
                'tracking' => [
                    'event_id' => $tracking['event_id'] ?? '',
                    'user_id' => $userId,
                    'event_type' => $eventType,
                    'properties' => $properties,
                    'metadata' => $metadata,
                    'timestamp' => $tracking['timestamp'] ?? date('c'),
                    'session_id' => $metadata['session_id'] ?? uniqid(),
                    'funnel_step' => $tracking['funnel_step'] ?? '',
                    'conversion_value' => $tracking['conversion_value'] ?? 0.0,
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'tracking' => [
                    'event_id' => '',
                    'user_id' => $userId,
                    'event_type' => $eventType,
                    'properties' => $properties,
                    'metadata' => $metadata,
                    'timestamp' => date('c'),
                    'session_id' => $metadata['session_id'] ?? uniqid(),
                    'funnel_step' => '',
                    'conversion_value' => 0.0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze user interactions.
     *
     * @param string               $userId    User ID (optional for aggregate analysis)
     * @param string               $timeframe Analysis timeframe
     * @param array<string, mixed> $filters   Analysis filters
     * @param array<string, mixed> $metrics   Metrics to analyze
     *
     * @return array{
     *     success: bool,
     *     analysis: array{
     *         user_id: string,
     *         timeframe: string,
     *         metrics: array{
     *             total_interactions: int,
     *             unique_users: int,
     *             avg_session_duration: float,
     *             bounce_rate: float,
     *             conversion_rate: float,
     *         },
     *         trends: array<int, array{
     *             date: string,
     *             interactions: int,
     *             users: int,
     *             conversion_rate: float,
     *         }>,
     *         top_events: array<int, array{
     *             event_type: string,
     *             count: int,
     *             percentage: float,
     *         }>,
     *         user_segments: array<int, array{
     *             segment: string,
     *             users: int,
     *             avg_interactions: float,
     *         }>,
     *         insights: array<int, string>,
     *         recommendations: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function analyze(
        string $userId = '',
        string $timeframe = '7d',
        array $filters = [],
        array $metrics = ['interactions', 'engagement', 'conversion'],
    ): array {
        try {
            $requestData = [
                'user_id' => $userId,
                'timeframe' => $timeframe,
                'filters' => $filters,
                'metrics' => $metrics,
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
                    'user_id' => $userId,
                    'timeframe' => $timeframe,
                    'metrics' => [
                        'total_interactions' => $analysis['metrics']['total_interactions'] ?? 0,
                        'unique_users' => $analysis['metrics']['unique_users'] ?? 0,
                        'avg_session_duration' => $analysis['metrics']['avg_session_duration'] ?? 0.0,
                        'bounce_rate' => $analysis['metrics']['bounce_rate'] ?? 0.0,
                        'conversion_rate' => $analysis['metrics']['conversion_rate'] ?? 0.0,
                    ],
                    'trends' => array_map(fn ($trend) => [
                        'date' => $trend['date'] ?? '',
                        'interactions' => $trend['interactions'] ?? 0,
                        'users' => $trend['users'] ?? 0,
                        'conversion_rate' => $trend['conversion_rate'] ?? 0.0,
                    ], $analysis['trends'] ?? []),
                    'top_events' => array_map(fn ($event) => [
                        'event_type' => $event['event_type'] ?? '',
                        'count' => $event['count'] ?? 0,
                        'percentage' => $event['percentage'] ?? 0.0,
                    ], $analysis['top_events'] ?? []),
                    'user_segments' => array_map(fn ($segment) => [
                        'segment' => $segment['segment'] ?? '',
                        'users' => $segment['users'] ?? 0,
                        'avg_interactions' => $segment['avg_interactions'] ?? 0.0,
                    ], $analysis['user_segments'] ?? []),
                    'insights' => $analysis['insights'] ?? [],
                    'recommendations' => $analysis['recommendations'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'analysis' => [
                    'user_id' => $userId,
                    'timeframe' => $timeframe,
                    'metrics' => [
                        'total_interactions' => 0,
                        'unique_users' => 0,
                        'avg_session_duration' => 0.0,
                        'bounce_rate' => 0.0,
                        'conversion_rate' => 0.0,
                    ],
                    'trends' => [],
                    'top_events' => [],
                    'user_segments' => [],
                    'insights' => [],
                    'recommendations' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create surveys.
     *
     * @param string $title       Survey title
     * @param string $description Survey description
     * @param array<int, array{
     *     question: string,
     *     type: string,
     *     options?: array<int, string>,
     *     required: bool,
     * }> $questions Survey questions
     * @param array<string, mixed> $settings Survey settings
     *
     * @return array{
     *     success: bool,
     *     survey: array{
     *         survey_id: string,
     *         title: string,
     *         description: string,
     *         questions: array<int, array{
     *             question_id: string,
     *             question: string,
     *             type: string,
     *             options: array<int, string>,
     *             required: bool,
     *         }>,
     *         settings: array<string, mixed>,
     *         status: string,
     *         created_at: string,
     *         responses_count: int,
     *         completion_rate: float,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function survey(
        string $title,
        string $description,
        array $questions,
        array $settings = [],
    ): array {
        try {
            $requestData = [
                'title' => $title,
                'description' => $description,
                'questions' => $questions,
                'settings' => $settings,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/surveys", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $survey = $responseData['survey'] ?? [];

            return [
                'success' => true,
                'survey' => [
                    'survey_id' => $survey['survey_id'] ?? '',
                    'title' => $title,
                    'description' => $description,
                    'questions' => array_map(fn ($question, $index) => [
                        'question_id' => $question['question_id'] ?? "q_{$index}",
                        'question' => $question['question'],
                        'type' => $question['type'],
                        'options' => $question['options'] ?? [],
                        'required' => $question['required'] ?? false,
                    ], $questions),
                    'settings' => $settings,
                    'status' => $survey['status'] ?? 'active',
                    'created_at' => $survey['created_at'] ?? date('c'),
                    'responses_count' => $survey['responses_count'] ?? 0,
                    'completion_rate' => $survey['completion_rate'] ?? 0.0,
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'survey' => [
                    'survey_id' => '',
                    'title' => $title,
                    'description' => $description,
                    'questions' => array_map(fn ($question, $index) => [
                        'question_id' => "q_{$index}",
                        'question' => $question['question'],
                        'type' => $question['type'],
                        'options' => $question['options'] ?? [],
                        'required' => $question['required'] ?? false,
                    ], $questions),
                    'settings' => $settings,
                    'status' => 'failed',
                    'created_at' => date('c'),
                    'responses_count' => 0,
                    'completion_rate' => 0.0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Collect feedback.
     *
     * @param string               $userId       User ID
     * @param string               $feedbackType Type of feedback
     * @param string               $content      Feedback content
     * @param array<string, mixed> $metadata     Feedback metadata
     * @param int                  $rating       Rating (1-5)
     *
     * @return array{
     *     success: bool,
     *     feedback: array{
     *         feedback_id: string,
     *         user_id: string,
     *         feedback_type: string,
     *         content: string,
     *         rating: int,
     *         metadata: array<string, mixed>,
     *         timestamp: string,
     *         sentiment: array{
     *             score: float,
     *             label: string,
     *             confidence: float,
     *         },
     *         categories: array<int, string>,
     *         priority: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function feedback(
        string $userId,
        string $feedbackType,
        string $content,
        array $metadata = [],
        int $rating = 0,
    ): array {
        try {
            $requestData = [
                'user_id' => $userId,
                'feedback_type' => $feedbackType,
                'content' => $content,
                'metadata' => $metadata,
                'rating' => max(0, min($rating, 5)),
                'timestamp' => date('c'),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/feedback", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $feedback = $responseData['feedback'] ?? [];

            return [
                'success' => true,
                'feedback' => [
                    'feedback_id' => $feedback['feedback_id'] ?? '',
                    'user_id' => $userId,
                    'feedback_type' => $feedbackType,
                    'content' => $content,
                    'rating' => $rating,
                    'metadata' => $metadata,
                    'timestamp' => $feedback['timestamp'] ?? date('c'),
                    'sentiment' => [
                        'score' => $feedback['sentiment']['score'] ?? 0.0,
                        'label' => $feedback['sentiment']['label'] ?? 'neutral',
                        'confidence' => $feedback['sentiment']['confidence'] ?? 0.0,
                    ],
                    'categories' => $feedback['categories'] ?? [],
                    'priority' => $feedback['priority'] ?? 'medium',
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'feedback' => [
                    'feedback_id' => '',
                    'user_id' => $userId,
                    'feedback_type' => $feedbackType,
                    'content' => $content,
                    'rating' => $rating,
                    'metadata' => $metadata,
                    'timestamp' => date('c'),
                    'sentiment' => [
                        'score' => 0.0,
                        'label' => 'neutral',
                        'confidence' => 0.0,
                    ],
                    'categories' => [],
                    'priority' => 'medium',
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Measure engagement.
     *
     * @param string               $userId    User ID
     * @param string               $timeframe Engagement timeframe
     * @param array<string, mixed> $metrics   Engagement metrics
     *
     * @return array{
     *     success: bool,
     *     engagement: array{
     *         user_id: string,
     *         timeframe: string,
     *         score: float,
     *         metrics: array{
     *             session_count: int,
     *             avg_session_duration: float,
     *             page_views: int,
     *             interactions: int,
     *             return_visits: int,
     *         },
     *         trends: array<int, array{
     *             date: string,
     *             score: float,
     *             sessions: int,
     *             duration: float,
     *         }>,
     *         benchmarks: array{
     *             percentile: float,
     *             category: string,
     *             comparison: string,
     *         },
     *         recommendations: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function engagement(
        string $userId,
        string $timeframe = '30d',
        array $metrics = ['sessions', 'duration', 'interactions'],
    ): array {
        try {
            $requestData = [
                'user_id' => $userId,
                'timeframe' => $timeframe,
                'metrics' => $metrics,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/engagement", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $engagement = $responseData['engagement'] ?? [];

            return [
                'success' => true,
                'engagement' => [
                    'user_id' => $userId,
                    'timeframe' => $timeframe,
                    'score' => $engagement['score'] ?? 0.0,
                    'metrics' => [
                        'session_count' => $engagement['metrics']['session_count'] ?? 0,
                        'avg_session_duration' => $engagement['metrics']['avg_session_duration'] ?? 0.0,
                        'page_views' => $engagement['metrics']['page_views'] ?? 0,
                        'interactions' => $engagement['metrics']['interactions'] ?? 0,
                        'return_visits' => $engagement['metrics']['return_visits'] ?? 0,
                    ],
                    'trends' => array_map(fn ($trend) => [
                        'date' => $trend['date'] ?? '',
                        'score' => $trend['score'] ?? 0.0,
                        'sessions' => $trend['sessions'] ?? 0,
                        'duration' => $trend['duration'] ?? 0.0,
                    ], $engagement['trends'] ?? []),
                    'benchmarks' => [
                        'percentile' => $engagement['benchmarks']['percentile'] ?? 0.0,
                        'category' => $engagement['benchmarks']['category'] ?? 'average',
                        'comparison' => $engagement['benchmarks']['comparison'] ?? '',
                    ],
                    'recommendations' => $engagement['recommendations'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'engagement' => [
                    'user_id' => $userId,
                    'timeframe' => $timeframe,
                    'score' => 0.0,
                    'metrics' => [
                        'session_count' => 0,
                        'avg_session_duration' => 0.0,
                        'page_views' => 0,
                        'interactions' => 0,
                        'return_visits' => 0,
                    ],
                    'trends' => [],
                    'benchmarks' => [
                        'percentile' => 0.0,
                        'category' => 'average',
                        'comparison' => '',
                    ],
                    'recommendations' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze user behavior.
     *
     * @param string               $userId        User ID
     * @param string               $timeframe     Analysis timeframe
     * @param array<string, mixed> $behaviorTypes Types of behavior to analyze
     *
     * @return array{
     *     success: bool,
     *     behavior: array{
     *         user_id: string,
     *         timeframe: string,
     *         patterns: array<int, array{
     *             pattern_type: string,
     *             description: string,
     *             frequency: int,
     *             confidence: float,
     *         }>,
     *         sequences: array<int, array{
     *             sequence: array<int, string>,
     *             frequency: int,
     *             probability: float,
     *         }>,
     *         anomalies: array<int, array{
     *             type: string,
     *             description: string,
     *             severity: string,
     *             timestamp: string,
     *         }>,
     *         predictions: array<int, array{
     *             event: string,
     *             probability: float,
     *             timeframe: string,
     *         }>,
     *         insights: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function behavior(
        string $userId,
        string $timeframe = '30d',
        array $behaviorTypes = ['navigation', 'interaction', 'engagement'],
    ): array {
        try {
            $requestData = [
                'user_id' => $userId,
                'timeframe' => $timeframe,
                'behavior_types' => $behaviorTypes,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/behavior", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $behavior = $responseData['behavior'] ?? [];

            return [
                'success' => true,
                'behavior' => [
                    'user_id' => $userId,
                    'timeframe' => $timeframe,
                    'patterns' => array_map(fn ($pattern) => [
                        'pattern_type' => $pattern['pattern_type'] ?? '',
                        'description' => $pattern['description'] ?? '',
                        'frequency' => $pattern['frequency'] ?? 0,
                        'confidence' => $pattern['confidence'] ?? 0.0,
                    ], $behavior['patterns'] ?? []),
                    'sequences' => array_map(fn ($sequence) => [
                        'sequence' => $sequence['sequence'] ?? [],
                        'frequency' => $sequence['frequency'] ?? 0,
                        'probability' => $sequence['probability'] ?? 0.0,
                    ], $behavior['sequences'] ?? []),
                    'anomalies' => array_map(fn ($anomaly) => [
                        'type' => $anomaly['type'] ?? '',
                        'description' => $anomaly['description'] ?? '',
                        'severity' => $anomaly['severity'] ?? 'low',
                        'timestamp' => $anomaly['timestamp'] ?? '',
                    ], $behavior['anomalies'] ?? []),
                    'predictions' => array_map(fn ($prediction) => [
                        'event' => $prediction['event'] ?? '',
                        'probability' => $prediction['probability'] ?? 0.0,
                        'timeframe' => $prediction['timeframe'] ?? '',
                    ], $behavior['predictions'] ?? []),
                    'insights' => $behavior['insights'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'behavior' => [
                    'user_id' => $userId,
                    'timeframe' => $timeframe,
                    'patterns' => [],
                    'sequences' => [],
                    'anomalies' => [],
                    'predictions' => [],
                    'insights' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Provide personalization.
     *
     * @param string               $userId      User ID
     * @param string               $context     Personalization context
     * @param array<string, mixed> $preferences User preferences
     * @param array<string, mixed> $options     Personalization options
     *
     * @return array{
     *     success: bool,
     *     personalization: array{
     *         user_id: string,
     *         context: string,
     *         recommendations: array<int, array{
     *             type: string,
     *             content: string,
     *             score: float,
     *             reason: string,
     *         }>,
     *         segments: array<int, string>,
     *         preferences: array<string, mixed>,
     *         next_best_action: array{
     *             action: string,
     *             score: float,
     *             reasoning: string,
     *         },
     *         content_variations: array<int, array{
     *             variation_id: string,
     *             content: string,
     *             targeting: array<string, mixed>,
     *         }>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function personalization(
        string $userId,
        string $context,
        array $preferences = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'user_id' => $userId,
                'context' => $context,
                'preferences' => $preferences,
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/personalization", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $personalization = $responseData['personalization'] ?? [];

            return [
                'success' => true,
                'personalization' => [
                    'user_id' => $userId,
                    'context' => $context,
                    'recommendations' => array_map(fn ($rec) => [
                        'type' => $rec['type'] ?? '',
                        'content' => $rec['content'] ?? '',
                        'score' => $rec['score'] ?? 0.0,
                        'reason' => $rec['reason'] ?? '',
                    ], $personalization['recommendations'] ?? []),
                    'segments' => $personalization['segments'] ?? [],
                    'preferences' => $preferences,
                    'next_best_action' => [
                        'action' => $personalization['next_best_action']['action'] ?? '',
                        'score' => $personalization['next_best_action']['score'] ?? 0.0,
                        'reasoning' => $personalization['next_best_action']['reasoning'] ?? '',
                    ],
                    'content_variations' => array_map(fn ($variation) => [
                        'variation_id' => $variation['variation_id'] ?? '',
                        'content' => $variation['content'] ?? '',
                        'targeting' => $variation['targeting'] ?? [],
                    ], $personalization['content_variations'] ?? []),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'personalization' => [
                    'user_id' => $userId,
                    'context' => $context,
                    'recommendations' => [],
                    'segments' => [],
                    'preferences' => $preferences,
                    'next_best_action' => [
                        'action' => '',
                        'score' => 0.0,
                        'reasoning' => '',
                    ],
                    'content_variations' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}

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
#[AsTool('scene_explain_analyze', 'Tool that analyzes and explains scenes in images')]
#[AsTool('scene_explain_describe', 'Tool that describes visual scenes', method: 'describeScene')]
#[AsTool('scene_explain_identify_objects', 'Tool that identifies objects in scenes', method: 'identifyObjects')]
#[AsTool('scene_explain_analyze_relationships', 'Tool that analyzes object relationships', method: 'analyzeRelationships')]
#[AsTool('scene_explain_detect_activities', 'Tool that detects activities in scenes', method: 'detectActivities')]
#[AsTool('scene_explain_analyze_context', 'Tool that analyzes scene context', method: 'analyzeContext')]
#[AsTool('scene_explain_generate_story', 'Tool that generates stories from scenes', method: 'generateStory')]
#[AsTool('scene_explain_answer_questions', 'Tool that answers questions about scenes', method: 'answerQuestions')]
final readonly class SceneExplain
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.sceneexplain.com',
        private array $options = [],
    ) {
    }

    /**
     * Analyze and explain scenes in images.
     *
     * @param string               $imageUrl     URL or base64 encoded image
     * @param string               $analysisType Type of analysis (basic, detailed, comprehensive)
     * @param array<string, mixed> $options      Analysis options
     *
     * @return array{
     *     success: bool,
     *     scene_analysis: array{
     *         image_url: string,
     *         analysis_type: string,
     *         scene_description: string,
     *         detected_objects: array<int, array{
     *             object_name: string,
     *             confidence: float,
     *             bounding_box: array{
     *                 x: int,
     *                 y: int,
     *                 width: int,
     *                 height: int,
     *             },
     *             attributes: array<string, mixed>,
     *         }>,
     *         scene_context: array{
     *             location_type: string,
     *             time_of_day: string,
     *             weather: string,
     *             mood: string,
     *             activity_type: string,
     *         },
     *         relationships: array<int, array{
     *             subject: string,
     *             predicate: string,
     *             object: string,
     *             confidence: float,
     *         }>,
     *         key_insights: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $imageUrl,
        string $analysisType = 'detailed',
        array $options = [],
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'analysis_type' => $analysisType,
                'options' => array_merge([
                    'include_relationships' => $options['include_relationships'] ?? true,
                    'include_context' => $options['include_context'] ?? true,
                    'include_activities' => $options['include_activities'] ?? true,
                    'confidence_threshold' => $options['confidence_threshold'] ?? 0.5,
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/analyze", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'scene_analysis' => [
                    'image_url' => $imageUrl,
                    'analysis_type' => $analysisType,
                    'scene_description' => $responseData['scene_description'] ?? '',
                    'detected_objects' => array_map(fn ($obj) => [
                        'object_name' => $obj['name'] ?? '',
                        'confidence' => $obj['confidence'] ?? 0.0,
                        'bounding_box' => [
                            'x' => $obj['bbox']['x'] ?? 0,
                            'y' => $obj['bbox']['y'] ?? 0,
                            'width' => $obj['bbox']['width'] ?? 0,
                            'height' => $obj['bbox']['height'] ?? 0,
                        ],
                        'attributes' => $obj['attributes'] ?? [],
                    ], $responseData['objects'] ?? []),
                    'scene_context' => [
                        'location_type' => $responseData['context']['location'] ?? '',
                        'time_of_day' => $responseData['context']['time_of_day'] ?? '',
                        'weather' => $responseData['context']['weather'] ?? '',
                        'mood' => $responseData['context']['mood'] ?? '',
                        'activity_type' => $responseData['context']['activity'] ?? '',
                    ],
                    'relationships' => array_map(fn ($rel) => [
                        'subject' => $rel['subject'] ?? '',
                        'predicate' => $rel['predicate'] ?? '',
                        'object' => $rel['object'] ?? '',
                        'confidence' => $rel['confidence'] ?? 0.0,
                    ], $responseData['relationships'] ?? []),
                    'key_insights' => $responseData['insights'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'scene_analysis' => [
                    'image_url' => $imageUrl,
                    'analysis_type' => $analysisType,
                    'scene_description' => '',
                    'detected_objects' => [],
                    'scene_context' => [
                        'location_type' => '',
                        'time_of_day' => '',
                        'weather' => '',
                        'mood' => '',
                        'activity_type' => '',
                    ],
                    'relationships' => [],
                    'key_insights' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Describe visual scenes.
     *
     * @param string               $imageUrl         URL or base64 encoded image
     * @param string               $descriptionStyle Style of description
     * @param array<string, mixed> $options          Description options
     *
     * @return array{
     *     success: bool,
     *     scene_description: array{
     *         image_url: string,
     *         description_style: string,
     *         description: string,
     *         detailed_description: string,
     *         summary: string,
     *         visual_elements: array<int, string>,
     *         color_palette: array<int, string>,
     *         composition_notes: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function describeScene(
        string $imageUrl,
        string $descriptionStyle = 'natural',
        array $options = [],
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'description_style' => $descriptionStyle,
                'options' => array_merge([
                    'include_colors' => $options['include_colors'] ?? true,
                    'include_composition' => $options['include_composition'] ?? true,
                    'detail_level' => $options['detail_level'] ?? 'medium',
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/describe", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'scene_description' => [
                    'image_url' => $imageUrl,
                    'description_style' => $descriptionStyle,
                    'description' => $responseData['description'] ?? '',
                    'detailed_description' => $responseData['detailed_description'] ?? '',
                    'summary' => $responseData['summary'] ?? '',
                    'visual_elements' => $responseData['visual_elements'] ?? [],
                    'color_palette' => $responseData['color_palette'] ?? [],
                    'composition_notes' => $responseData['composition'] ?? '',
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'scene_description' => [
                    'image_url' => $imageUrl,
                    'description_style' => $descriptionStyle,
                    'description' => '',
                    'detailed_description' => '',
                    'summary' => '',
                    'visual_elements' => [],
                    'color_palette' => [],
                    'composition_notes' => '',
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Identify objects in scenes.
     *
     * @param string               $imageUrl         URL or base64 encoded image
     * @param array<string>        $objectCategories Object categories to detect
     * @param array<string, mixed> $options          Detection options
     *
     * @return array{
     *     success: bool,
     *     object_identification: array{
     *         image_url: string,
     *         object_categories: array<string>,
     *         detected_objects: array<int, array{
     *             object_name: string,
     *             category: string,
     *             confidence: float,
     *             bounding_box: array{
     *                 x: int,
     *                 y: int,
     *                 width: int,
     *                 height: int,
     *             },
     *             attributes: array<string, mixed>,
     *             relationships: array<int, string>,
     *         }>,
     *         object_count: int,
     *         category_distribution: array<string, int>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function identifyObjects(
        string $imageUrl,
        array $objectCategories = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'object_categories' => $objectCategories ?: ['person', 'vehicle', 'animal', 'furniture', 'food'],
                'options' => array_merge([
                    'confidence_threshold' => $options['confidence_threshold'] ?? 0.5,
                    'include_attributes' => $options['include_attributes'] ?? true,
                    'include_relationships' => $options['include_relationships'] ?? true,
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/identify-objects", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $objects = $responseData['objects'] ?? [];

            return [
                'success' => true,
                'object_identification' => [
                    'image_url' => $imageUrl,
                    'object_categories' => $objectCategories,
                    'detected_objects' => array_map(fn ($obj) => [
                        'object_name' => $obj['name'] ?? '',
                        'category' => $obj['category'] ?? '',
                        'confidence' => $obj['confidence'] ?? 0.0,
                        'bounding_box' => [
                            'x' => $obj['bbox']['x'] ?? 0,
                            'y' => $obj['bbox']['y'] ?? 0,
                            'width' => $obj['bbox']['width'] ?? 0,
                            'height' => $obj['bbox']['height'] ?? 0,
                        ],
                        'attributes' => $obj['attributes'] ?? [],
                        'relationships' => $obj['relationships'] ?? [],
                    ], $objects),
                    'object_count' => \count($objects),
                    'category_distribution' => $this->countCategories($objects),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'object_identification' => [
                    'image_url' => $imageUrl,
                    'object_categories' => $objectCategories,
                    'detected_objects' => [],
                    'object_count' => 0,
                    'category_distribution' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze object relationships.
     *
     * @param string               $imageUrl          URL or base64 encoded image
     * @param array<string, mixed> $relationshipTypes Types of relationships to analyze
     * @param array<string, mixed> $options           Analysis options
     *
     * @return array{
     *     success: bool,
     *     relationship_analysis: array{
     *         image_url: string,
     *         relationship_types: array<string, mixed>,
     *         relationships: array<int, array{
     *             subject: string,
     *             predicate: string,
     *             object: string,
     *             confidence: float,
     *             relationship_type: string,
     *             spatial_info: array{
     *                 distance: string,
     *                 direction: string,
     *                 relative_position: string,
     *             },
     *         }>,
     *         relationship_graph: array<string, array<string, string>>,
     *         dominant_relationships: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function analyzeRelationships(
        string $imageUrl,
        array $relationshipTypes = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'relationship_types' => $relationshipTypes ?: ['spatial', 'functional', 'temporal', 'social'],
                'options' => array_merge([
                    'include_spatial' => $options['include_spatial'] ?? true,
                    'include_functional' => $options['include_functional'] ?? true,
                    'confidence_threshold' => $options['confidence_threshold'] ?? 0.5,
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/analyze-relationships", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'relationship_analysis' => [
                    'image_url' => $imageUrl,
                    'relationship_types' => $relationshipTypes,
                    'relationships' => array_map(fn ($rel) => [
                        'subject' => $rel['subject'] ?? '',
                        'predicate' => $rel['predicate'] ?? '',
                        'object' => $rel['object'] ?? '',
                        'confidence' => $rel['confidence'] ?? 0.0,
                        'relationship_type' => $rel['type'] ?? '',
                        'spatial_info' => [
                            'distance' => $rel['spatial']['distance'] ?? '',
                            'direction' => $rel['spatial']['direction'] ?? '',
                            'relative_position' => $rel['spatial']['position'] ?? '',
                        ],
                    ], $responseData['relationships'] ?? []),
                    'relationship_graph' => $responseData['graph'] ?? [],
                    'dominant_relationships' => $responseData['dominant'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'relationship_analysis' => [
                    'image_url' => $imageUrl,
                    'relationship_types' => $relationshipTypes,
                    'relationships' => [],
                    'relationship_graph' => [],
                    'dominant_relationships' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Detect activities in scenes.
     *
     * @param string               $imageUrl      URL or base64 encoded image
     * @param array<string>        $activityTypes Activity types to detect
     * @param array<string, mixed> $options       Detection options
     *
     * @return array{
     *     success: bool,
     *     activity_detection: array{
     *         image_url: string,
     *         activity_types: array<string>,
     *         detected_activities: array<int, array{
     *             activity_name: string,
     *             confidence: float,
     *             participants: array<int, string>,
     *             location: string,
     *             duration_estimate: string,
     *             intensity_level: string,
     *         }>,
     *         activity_summary: string,
     *         primary_activity: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function detectActivities(
        string $imageUrl,
        array $activityTypes = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'activity_types' => $activityTypes ?: ['sports', 'work', 'leisure', 'social', 'transportation'],
                'options' => array_merge([
                    'confidence_threshold' => $options['confidence_threshold'] ?? 0.5,
                    'include_participants' => $options['include_participants'] ?? true,
                    'include_context' => $options['include_context'] ?? true,
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/detect-activities", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'activity_detection' => [
                    'image_url' => $imageUrl,
                    'activity_types' => $activityTypes,
                    'detected_activities' => array_map(fn ($activity) => [
                        'activity_name' => $activity['name'] ?? '',
                        'confidence' => $activity['confidence'] ?? 0.0,
                        'participants' => $activity['participants'] ?? [],
                        'location' => $activity['location'] ?? '',
                        'duration_estimate' => $activity['duration'] ?? '',
                        'intensity_level' => $activity['intensity'] ?? '',
                    ], $responseData['activities'] ?? []),
                    'activity_summary' => $responseData['summary'] ?? '',
                    'primary_activity' => $responseData['primary_activity'] ?? '',
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'activity_detection' => [
                    'image_url' => $imageUrl,
                    'activity_types' => $activityTypes,
                    'detected_activities' => [],
                    'activity_summary' => '',
                    'primary_activity' => '',
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze scene context.
     *
     * @param string               $imageUrl     URL or base64 encoded image
     * @param array<string, mixed> $contextTypes Context types to analyze
     * @param array<string, mixed> $options      Analysis options
     *
     * @return array{
     *     success: bool,
     *     context_analysis: array{
     *         image_url: string,
     *         context_types: array<string, mixed>,
     *         scene_context: array{
     *             location: array{
     *                 type: string,
     *                 name: string,
     *                 characteristics: array<int, string>,
     *             },
     *             temporal_context: array{
     *                 time_of_day: string,
     *                 season: string,
     *                 weather: string,
     *             },
     *             social_context: array{
     *                 number_of_people: int,
     *                 social_setting: string,
     *                 formality_level: string,
     *             },
     *             cultural_context: array{
     *                 cultural_indicators: array<int, string>,
     *                 language_signs: array<int, string>,
     *             },
     *         },
     *         context_confidence: array<string, float>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function analyzeContext(
        string $imageUrl,
        array $contextTypes = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'context_types' => $contextTypes ?: ['spatial', 'temporal', 'social', 'cultural'],
                'options' => array_merge([
                    'include_cultural_analysis' => $options['include_cultural'] ?? true,
                    'include_social_analysis' => $options['include_social'] ?? true,
                    'confidence_threshold' => $options['confidence_threshold'] ?? 0.5,
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/analyze-context", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'context_analysis' => [
                    'image_url' => $imageUrl,
                    'context_types' => $contextTypes,
                    'scene_context' => [
                        'location' => [
                            'type' => $responseData['location']['type'] ?? '',
                            'name' => $responseData['location']['name'] ?? '',
                            'characteristics' => $responseData['location']['characteristics'] ?? [],
                        ],
                        'temporal_context' => [
                            'time_of_day' => $responseData['temporal']['time_of_day'] ?? '',
                            'season' => $responseData['temporal']['season'] ?? '',
                            'weather' => $responseData['temporal']['weather'] ?? '',
                        ],
                        'social_context' => [
                            'number_of_people' => $responseData['social']['people_count'] ?? 0,
                            'social_setting' => $responseData['social']['setting'] ?? '',
                            'formality_level' => $responseData['social']['formality'] ?? '',
                        ],
                        'cultural_context' => [
                            'cultural_indicators' => $responseData['cultural']['indicators'] ?? [],
                            'language_signs' => $responseData['cultural']['language_signs'] ?? [],
                        ],
                    ],
                    'context_confidence' => $responseData['confidence_scores'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'context_analysis' => [
                    'image_url' => $imageUrl,
                    'context_types' => $contextTypes,
                    'scene_context' => [
                        'location' => [
                            'type' => '',
                            'name' => '',
                            'characteristics' => [],
                        ],
                        'temporal_context' => [
                            'time_of_day' => '',
                            'season' => '',
                            'weather' => '',
                        ],
                        'social_context' => [
                            'number_of_people' => 0,
                            'social_setting' => '',
                            'formality_level' => '',
                        ],
                        'cultural_context' => [
                            'cultural_indicators' => [],
                            'language_signs' => [],
                        ],
                    ],
                    'context_confidence' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate stories from scenes.
     *
     * @param string               $imageUrl     URL or base64 encoded image
     * @param string               $storyStyle   Style of story (narrative, descriptive, creative)
     * @param array<string, mixed> $storyOptions Story generation options
     *
     * @return array{
     *     success: bool,
     *     story_generation: array{
     *         image_url: string,
     *         story_style: string,
     *         story: string,
     *         story_elements: array{
     *             characters: array<int, string>,
     *             setting: string,
     *             plot_points: array<int, string>,
     *             themes: array<int, string>,
     *         },
     *         story_metadata: array{
     *             word_count: int,
     *             reading_time: float,
     *             complexity_level: string,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function generateStory(
        string $imageUrl,
        string $storyStyle = 'narrative',
        array $storyOptions = [],
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'story_style' => $storyStyle,
                'options' => array_merge([
                    'include_characters' => $storyOptions['include_characters'] ?? true,
                    'include_setting' => $storyOptions['include_setting'] ?? true,
                    'story_length' => $storyOptions['length'] ?? 'medium',
                    'target_audience' => $storyOptions['audience'] ?? 'general',
                ], $storyOptions),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/generate-story", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'story_generation' => [
                    'image_url' => $imageUrl,
                    'story_style' => $storyStyle,
                    'story' => $responseData['story'] ?? '',
                    'story_elements' => [
                        'characters' => $responseData['elements']['characters'] ?? [],
                        'setting' => $responseData['elements']['setting'] ?? '',
                        'plot_points' => $responseData['elements']['plot_points'] ?? [],
                        'themes' => $responseData['elements']['themes'] ?? [],
                    ],
                    'story_metadata' => [
                        'word_count' => $responseData['metadata']['word_count'] ?? 0,
                        'reading_time' => $responseData['metadata']['reading_time'] ?? 0.0,
                        'complexity_level' => $responseData['metadata']['complexity'] ?? '',
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'story_generation' => [
                    'image_url' => $imageUrl,
                    'story_style' => $storyStyle,
                    'story' => '',
                    'story_elements' => [
                        'characters' => [],
                        'setting' => '',
                        'plot_points' => [],
                        'themes' => [],
                    ],
                    'story_metadata' => [
                        'word_count' => 0,
                        'reading_time' => 0.0,
                        'complexity_level' => '',
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Answer questions about scenes.
     *
     * @param string               $imageUrl URL or base64 encoded image
     * @param string               $question Question about the scene
     * @param array<string, mixed> $options  Question answering options
     *
     * @return array{
     *     success: bool,
     *     question_answering: array{
     *         image_url: string,
     *         question: string,
     *         answer: string,
     *         confidence: float,
     *         supporting_evidence: array<int, string>,
     *         answer_type: string,
     *         related_questions: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function answerQuestions(
        string $imageUrl,
        string $question,
        array $options = [],
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'question' => $question,
                'options' => array_merge([
                    'include_evidence' => $options['include_evidence'] ?? true,
                    'confidence_threshold' => $options['confidence_threshold'] ?? 0.5,
                    'answer_detail_level' => $options['detail_level'] ?? 'medium',
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/answer-question", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'question_answering' => [
                    'image_url' => $imageUrl,
                    'question' => $question,
                    'answer' => $responseData['answer'] ?? '',
                    'confidence' => $responseData['confidence'] ?? 0.0,
                    'supporting_evidence' => $responseData['evidence'] ?? [],
                    'answer_type' => $responseData['answer_type'] ?? '',
                    'related_questions' => $responseData['related_questions'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'question_answering' => [
                    'image_url' => $imageUrl,
                    'question' => $question,
                    'answer' => '',
                    'confidence' => 0.0,
                    'supporting_evidence' => [],
                    'answer_type' => '',
                    'related_questions' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Helper methods.
     */
    private function countCategories(array $objects): array
    {
        $categories = [];
        foreach ($objects as $object) {
            $category = $object['category'] ?? 'unknown';
            $categories[$category] = ($categories[$category] ?? 0) + 1;
        }

        return $categories;
    }
}

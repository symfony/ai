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
#[AsTool('azure_cognitive_analyze_image', 'Tool that analyzes images using Azure Cognitive Services')]
#[AsTool('azure_cognitive_recognize_text', 'Tool that recognizes text in images', method: 'recognizeText')]
#[AsTool('azure_cognitive_detect_faces', 'Tool that detects faces in images', method: 'detectFaces')]
#[AsTool('azure_cognitive_analyze_content', 'Tool that analyzes content for moderation', method: 'analyzeContent')]
#[AsTool('azure_cognitive_translate_text', 'Tool that translates text using Azure Translator', method: 'translateText')]
#[AsTool('azure_cognitive_speech_to_text', 'Tool that converts speech to text', method: 'speechToText')]
#[AsTool('azure_cognitive_text_to_speech', 'Tool that converts text to speech', method: 'textToSpeech')]
#[AsTool('azure_cognitive_search_web', 'Tool that searches the web using Bing Search', method: 'searchWeb')]
final readonly class AzureCognitive
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $subscriptionKey,
        private string $endpoint,
        private string $region = 'westus2',
        private array $options = [],
    ) {
    }

    /**
     * Analyze images using Azure Cognitive Services.
     *
     * @param string               $imageUrl URL or base64 encoded image
     * @param array<string, mixed> $features Features to analyze
     * @param array<string, mixed> $details  Details to include
     * @param array<string, mixed> $options  Analysis options
     *
     * @return array{
     *     success: bool,
     *     image_analysis: array{
     *         image_url: string,
     *         features: array<string, mixed>,
     *         details: array<string, mixed>,
     *         analysis_results: array{
     *             categories: array<int, array{
     *                 name: string,
     *                 score: float,
     *                 detail: array<string, mixed>,
     *             }>,
     *             tags: array<int, array{
     *                 name: string,
     *                 confidence: float,
     *             }>,
     *             objects: array<int, array{
     *                 object: string,
     *                 confidence: float,
     *                 rectangle: array{
     *                     x: int,
     *                     y: int,
     *                     w: int,
     *                     h: int,
     *                 },
     *             }>,
     *             faces: array<int, array{
     *                 age: int,
     *                 gender: string,
     *                 face_rectangle: array{
     *                     left: int,
     *                     top: int,
     *                     width: int,
     *                     height: int,
     *                 },
     *                 emotion: array<string, float>,
     *             }>,
     *             adult: array{
     *                 is_adult_content: bool,
     *                 is_racy_content: bool,
     *                 adult_score: float,
     *                 racy_score: float,
     *             },
     *             color: array{
     *                 dominant_color_foreground: string,
     *                 dominant_color_background: string,
     *                 dominant_colors: array<int, string>,
     *                 accent_color: string,
     *             },
     *             description: array{
     *                 tags: array<int, string>,
     *                 captions: array<int, array{
     *                     text: string,
     *                     confidence: float,
     *                 }>,
     *             },
     *         },
     *         insights: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $imageUrl,
        array $features = [],
        array $details = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'url' => $imageUrl,
                'visualFeatures' => $features ?: ['Categories', 'Tags', 'Objects', 'Faces', 'Adult', 'Color', 'Description'],
                'details' => $details ?: ['Celebrities', 'Landmarks'],
                'language' => $options['language'] ?? 'en',
            ];

            $response = $this->httpClient->request('POST', "{$this->endpoint}/vision/v3.2/analyze", [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'image_analysis' => [
                    'image_url' => $imageUrl,
                    'features' => $features,
                    'details' => $details,
                    'analysis_results' => [
                        'categories' => array_map(fn ($category) => [
                            'name' => $category['name'] ?? '',
                            'score' => $category['score'] ?? 0.0,
                            'detail' => $category['detail'] ?? [],
                        ], $responseData['categories'] ?? []),
                        'tags' => array_map(fn ($tag) => [
                            'name' => $tag['name'] ?? '',
                            'confidence' => $tag['confidence'] ?? 0.0,
                        ], $responseData['tags'] ?? []),
                        'objects' => array_map(fn ($object) => [
                            'object' => $object['object'] ?? '',
                            'confidence' => $object['confidence'] ?? 0.0,
                            'rectangle' => [
                                'x' => $object['rectangle']['x'] ?? 0,
                                'y' => $object['rectangle']['y'] ?? 0,
                                'w' => $object['rectangle']['w'] ?? 0,
                                'h' => $object['rectangle']['h'] ?? 0,
                            ],
                        ], $responseData['objects'] ?? []),
                        'faces' => array_map(fn ($face) => [
                            'age' => $face['age'] ?? 0,
                            'gender' => $face['gender'] ?? '',
                            'face_rectangle' => [
                                'left' => $face['faceRectangle']['left'] ?? 0,
                                'top' => $face['faceRectangle']['top'] ?? 0,
                                'width' => $face['faceRectangle']['width'] ?? 0,
                                'height' => $face['faceRectangle']['height'] ?? 0,
                            ],
                            'emotion' => $face['emotion'] ?? [],
                        ], $responseData['faces'] ?? []),
                        'adult' => [
                            'is_adult_content' => $responseData['adult']['isAdultContent'] ?? false,
                            'is_racy_content' => $responseData['adult']['isRacyContent'] ?? false,
                            'adult_score' => $responseData['adult']['adultScore'] ?? 0.0,
                            'racy_score' => $responseData['adult']['racyScore'] ?? 0.0,
                        ],
                        'color' => [
                            'dominant_color_foreground' => $responseData['color']['dominantColorForeground'] ?? '',
                            'dominant_color_background' => $responseData['color']['dominantColorBackground'] ?? '',
                            'dominant_colors' => $responseData['color']['dominantColors'] ?? [],
                            'accent_color' => $responseData['color']['accentColor'] ?? '',
                        ],
                        'description' => [
                            'tags' => $responseData['description']['tags'] ?? [],
                            'captions' => array_map(fn ($caption) => [
                                'text' => $caption['text'] ?? '',
                                'confidence' => $caption['confidence'] ?? 0.0,
                            ], $responseData['description']['captions'] ?? []),
                        ],
                    ],
                    'insights' => $this->generateImageInsights($responseData),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'image_analysis' => [
                    'image_url' => $imageUrl,
                    'features' => $features,
                    'details' => $details,
                    'analysis_results' => [
                        'categories' => [],
                        'tags' => [],
                        'objects' => [],
                        'faces' => [],
                        'adult' => [
                            'is_adult_content' => false,
                            'is_racy_content' => false,
                            'adult_score' => 0.0,
                            'racy_score' => 0.0,
                        ],
                        'color' => [
                            'dominant_color_foreground' => '',
                            'dominant_color_background' => '',
                            'dominant_colors' => [],
                            'accent_color' => '',
                        ],
                        'description' => [
                            'tags' => [],
                            'captions' => [],
                        ],
                    ],
                    'insights' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Recognize text in images using OCR.
     *
     * @param string               $imageUrl URL or base64 encoded image
     * @param string               $language Language hint
     * @param array<string, mixed> $options  Recognition options
     *
     * @return array{
     *     success: bool,
     *     text_recognition: array{
     *         image_url: string,
     *         language: string,
     *         recognized_text: string,
     *         text_regions: array<int, array{
     *             text: string,
     *             bounding_box: array<int, array{
     *                 x: float,
     *                 y: float,
     *             }>,
     *             confidence: float,
     *         }>,
     *         words: array<int, array{
     *             text: string,
     *             bounding_box: array<int, array{
     *                 x: float,
     *                 y: float,
     *             }>,
     *             confidence: float,
     *         }>,
     *         lines: array<int, array{
     *             text: string,
     *             bounding_box: array<int, array{
     *                 x: float,
     *                 y: float,
     *             }>,
     *         }>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function recognizeText(
        string $imageUrl,
        string $language = 'en',
        array $options = [],
    ): array {
        try {
            $requestData = [
                'url' => $imageUrl,
                'language' => $language,
                'detectOrientation' => $options['detect_orientation'] ?? true,
            ];

            $response = $this->httpClient->request('POST', "{$this->endpoint}/vision/v3.2/ocr", [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $regions = $responseData['regions'] ?? [];
            $allText = [];

            foreach ($regions as $region) {
                foreach ($region['lines'] ?? [] as $line) {
                    $allText[] = $line['text'] ?? '';
                }
            }

            return [
                'success' => true,
                'text_recognition' => [
                    'image_url' => $imageUrl,
                    'language' => $language,
                    'recognized_text' => implode(' ', $allText),
                    'text_regions' => array_map(fn ($region) => [
                        'text' => implode(' ', array_map(fn ($line) => $line['text'] ?? '', $region['lines'] ?? [])),
                        'bounding_box' => array_map(fn ($point) => [
                            'x' => $point[0] ?? 0.0,
                            'y' => $point[1] ?? 0.0,
                        ], $region['boundingBox'] ?? []),
                        'confidence' => $region['confidence'] ?? 0.0,
                    ], $regions),
                    'words' => $this->extractWords($regions),
                    'lines' => $this->extractLines($regions),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'text_recognition' => [
                    'image_url' => $imageUrl,
                    'language' => $language,
                    'recognized_text' => '',
                    'text_regions' => [],
                    'words' => [],
                    'lines' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Detect faces in images.
     *
     * @param string               $imageUrl   URL or base64 encoded image
     * @param array<string, mixed> $attributes Face attributes to detect
     * @param array<string, mixed> $options    Detection options
     *
     * @return array{
     *     success: bool,
     *     face_detection: array{
     *         image_url: string,
     *         attributes: array<string, mixed>,
     *         detected_faces: array<int, array{
     *             face_id: string,
     *             face_rectangle: array{
     *                 left: int,
     *                 top: int,
     *                 width: int,
     *                 height: int,
     *             },
     *             face_attributes: array{
     *                 age: float,
     *                 gender: string,
     *                 smile: float,
     *                 facial_hair: array{
     *                     moustache: float,
     *                     beard: float,
     *                     sideburns: float,
     *                 },
     *                 glasses: string,
     *                 head_pose: array{
     *                     pitch: float,
     *                     roll: float,
     *                     yaw: float,
     *                 },
     *                 emotion: array<string, float>,
     *                 makeup: array{
     *                     eye_makeup: bool,
     *                     lip_makeup: bool,
     *                 },
     *                 accessories: array<int, array{
     *                     type: string,
     *                     confidence: float,
     *                 }>,
     *                 blur: array{
     *                     blur_level: string,
     *                     value: float,
     *                 },
     *                 exposure: array{
     *                     exposure_level: string,
     *                     value: float,
     *                 },
     *                 noise: array{
     *                     noise_level: string,
     *                     value: float,
     *                 },
     *             },
     *         }>,
     *         total_faces: int,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function detectFaces(
        string $imageUrl,
        array $attributes = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'url' => $imageUrl,
                'returnFaceAttributes' => implode(',', $attributes ?: [
                    'age', 'gender', 'smile', 'facialHair', 'glasses', 'emotion',
                    'hair', 'makeup', 'accessories', 'blur', 'exposure', 'noise',
                ]),
                'returnFaceId' => $options['return_face_id'] ?? true,
                'returnFaceLandmarks' => $options['return_landmarks'] ?? false,
            ];

            $response = $this->httpClient->request('POST', "{$this->endpoint}/face/v1.0/detect", [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'face_detection' => [
                    'image_url' => $imageUrl,
                    'attributes' => $attributes,
                    'detected_faces' => array_map(fn ($face) => [
                        'face_id' => $face['faceId'] ?? '',
                        'face_rectangle' => [
                            'left' => $face['faceRectangle']['left'] ?? 0,
                            'top' => $face['faceRectangle']['top'] ?? 0,
                            'width' => $face['faceRectangle']['width'] ?? 0,
                            'height' => $face['faceRectangle']['height'] ?? 0,
                        ],
                        'face_attributes' => [
                            'age' => $face['faceAttributes']['age'] ?? 0.0,
                            'gender' => $face['faceAttributes']['gender'] ?? '',
                            'smile' => $face['faceAttributes']['smile'] ?? 0.0,
                            'facial_hair' => [
                                'moustache' => $face['faceAttributes']['facialHair']['moustache'] ?? 0.0,
                                'beard' => $face['faceAttributes']['facialHair']['beard'] ?? 0.0,
                                'sideburns' => $face['faceAttributes']['facialHair']['sideburns'] ?? 0.0,
                            ],
                            'glasses' => $face['faceAttributes']['glasses'] ?? '',
                            'head_pose' => [
                                'pitch' => $face['faceAttributes']['headPose']['pitch'] ?? 0.0,
                                'roll' => $face['faceAttributes']['headPose']['roll'] ?? 0.0,
                                'yaw' => $face['faceAttributes']['headPose']['yaw'] ?? 0.0,
                            ],
                            'emotion' => $face['faceAttributes']['emotion'] ?? [],
                            'makeup' => [
                                'eye_makeup' => $face['faceAttributes']['makeup']['eyeMakeup'] ?? false,
                                'lip_makeup' => $face['faceAttributes']['makeup']['lipMakeup'] ?? false,
                            ],
                            'accessories' => array_map(fn ($accessory) => [
                                'type' => $accessory['type'] ?? '',
                                'confidence' => $accessory['confidence'] ?? 0.0,
                            ], $face['faceAttributes']['accessories'] ?? []),
                            'blur' => [
                                'blur_level' => $face['faceAttributes']['blur']['blurLevel'] ?? '',
                                'value' => $face['faceAttributes']['blur']['value'] ?? 0.0,
                            ],
                            'exposure' => [
                                'exposure_level' => $face['faceAttributes']['exposure']['exposureLevel'] ?? '',
                                'value' => $face['faceAttributes']['exposure']['value'] ?? 0.0,
                            ],
                            'noise' => [
                                'noise_level' => $face['faceAttributes']['noise']['noiseLevel'] ?? '',
                                'value' => $face['faceAttributes']['noise']['value'] ?? 0.0,
                            ],
                        ],
                    ], $responseData),
                    'total_faces' => \count($responseData),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'face_detection' => [
                    'image_url' => $imageUrl,
                    'attributes' => $attributes,
                    'detected_faces' => [],
                    'total_faces' => 0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze content for moderation.
     *
     * @param string               $content     Content to analyze (text or image URL)
     * @param string               $contentType Type of content (text/image)
     * @param array<string, mixed> $options     Moderation options
     *
     * @return array{
     *     success: bool,
     *     content_moderation: array{
     *         content: string,
     *         content_type: string,
     *         moderation_results: array{
     *             is_adult_content: bool,
     *             is_racy_content: bool,
     *             adult_score: float,
     *             racy_score: float,
     *             categories: array<string, bool>,
     *             category_scores: array<string, float>,
     *             terms: array<int, array{
     *                 term: string,
     *                 index: int,
     *                 list_id: int,
     *             }>,
     *         },
     *         recommendations: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function analyzeContent(
        string $content,
        string $contentType = 'text',
        array $options = [],
    ): array {
        try {
            if ('image' === $contentType) {
                $requestData = ['DataRepresentation' => 'URL', 'Value' => $content];
                $endpoint = "{$this->endpoint}/contentmoderator/moderate/v1.0/ProcessImage/Evaluate";
            } else {
                $requestData = ['Text' => $content];
                $endpoint = "{$this->endpoint}/contentmoderator/moderate/v1.0/ProcessText/Screen";
            }

            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'content_moderation' => [
                    'content' => $content,
                    'content_type' => $contentType,
                    'moderation_results' => [
                        'is_adult_content' => $responseData['IsImageAdultClassified'] ?? false,
                        'is_racy_content' => $responseData['IsImageRacyClassified'] ?? false,
                        'adult_score' => $responseData['AdultClassificationScore'] ?? 0.0,
                        'racy_score' => $responseData['RacyClassificationScore'] ?? 0.0,
                        'categories' => $responseData['Categories'] ?? [],
                        'category_scores' => $responseData['CategoryScores'] ?? [],
                        'terms' => array_map(fn ($term) => [
                            'term' => $term['Term'] ?? '',
                            'index' => $term['Index'] ?? 0,
                            'list_id' => $term['ListId'] ?? 0,
                        ], $responseData['Terms'] ?? []),
                    ],
                    'recommendations' => $this->generateModerationRecommendations($responseData),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'content_moderation' => [
                    'content' => $content,
                    'content_type' => $contentType,
                    'moderation_results' => [
                        'is_adult_content' => false,
                        'is_racy_content' => false,
                        'adult_score' => 0.0,
                        'racy_score' => 0.0,
                        'categories' => [],
                        'category_scores' => [],
                        'terms' => [],
                    ],
                    'recommendations' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Translate text using Azure Translator.
     *
     * @param string               $text           Text to translate
     * @param string               $targetLanguage Target language code
     * @param string               $sourceLanguage Source language code (optional)
     * @param array<string, mixed> $options        Translation options
     *
     * @return array{
     *     success: bool,
     *     translation: array{
     *         text: string,
     *         source_language: string,
     *         target_language: string,
     *         translated_text: string,
     *         detected_language: array{
     *             language: string,
     *             score: float,
     *         },
     *         alternatives: array<int, array{
     *             text: string,
     *             confidence: float,
     *         }>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function translateText(
        string $text,
        string $targetLanguage = 'en',
        string $sourceLanguage = '',
        array $options = [],
    ): array {
        try {
            $requestData = [
                [
                    'Text' => $text,
                ],
            ];

            $query = ['api-version' => '3.0', 'to' => $targetLanguage];
            if ($sourceLanguage) {
                $query['from'] = $sourceLanguage;
            }

            $response = $this->httpClient->request('POST', "{$this->endpoint}/translator/text/v3.0/translate", [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
                'query' => $query,
            ] + $this->options);

            $responseData = $response->toArray();
            $translation = $responseData[0]['translations'][0] ?? [];

            return [
                'success' => true,
                'translation' => [
                    'text' => $text,
                    'source_language' => $sourceLanguage ?: $translation['detectedLanguage']['language'] ?? '',
                    'target_language' => $targetLanguage,
                    'translated_text' => $translation['text'] ?? '',
                    'detected_language' => [
                        'language' => $translation['detectedLanguage']['language'] ?? '',
                        'score' => $translation['detectedLanguage']['score'] ?? 0.0,
                    ],
                    'alternatives' => array_map(fn ($alt) => [
                        'text' => $alt['text'] ?? '',
                        'confidence' => $alt['confidenceScore'] ?? 0.0,
                    ], $translation['alternatives'] ?? []),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'translation' => [
                    'text' => $text,
                    'source_language' => $sourceLanguage,
                    'target_language' => $targetLanguage,
                    'translated_text' => '',
                    'detected_language' => [
                        'language' => '',
                        'score' => 0.0,
                    ],
                    'alternatives' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Convert speech to text.
     *
     * @param string               $audioData Base64 encoded audio data
     * @param string               $language  Language code
     * @param array<string, mixed> $options   Speech recognition options
     *
     * @return array{
     *     success: bool,
     *     speech_recognition: array{
     *         audio_data: string,
     *         language: string,
     *         recognized_text: string,
     *         confidence: float,
     *         duration: float,
     *         alternatives: array<int, array{
     *             text: string,
     *             confidence: float,
     *         }>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function speechToText(
        string $audioData,
        string $language = 'en-US',
        array $options = [],
    ): array {
        try {
            $response = $this->httpClient->request('POST', "{$this->endpoint}/speech/recognition/conversation/cognitiveservices/v1", [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                    'Content-Type' => 'audio/wav',
                    'Accept' => 'application/json',
                ],
                'body' => base64_decode($audioData),
                'query' => [
                    'language' => $language,
                    'format' => $options['format'] ?? 'detailed',
                ],
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'speech_recognition' => [
                    'audio_data' => $audioData,
                    'language' => $language,
                    'recognized_text' => $responseData['DisplayText'] ?? '',
                    'confidence' => $responseData['Confidence'] ?? 0.0,
                    'duration' => $responseData['Duration'] ?? 0.0,
                    'alternatives' => array_map(fn ($alt) => [
                        'text' => $alt['DisplayText'] ?? '',
                        'confidence' => $alt['Confidence'] ?? 0.0,
                    ], $responseData['NBest'] ?? []),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'speech_recognition' => [
                    'audio_data' => $audioData,
                    'language' => $language,
                    'recognized_text' => '',
                    'confidence' => 0.0,
                    'duration' => 0.0,
                    'alternatives' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Convert text to speech.
     *
     * @param string               $text    Text to convert to speech
     * @param string               $voice   Voice to use
     * @param array<string, mixed> $options Speech synthesis options
     *
     * @return array{
     *     success: bool,
     *     speech_synthesis: array{
     *         text: string,
     *         voice: string,
     *         audio_data: string,
     *         audio_format: string,
     *         duration: float,
     *         word_timing: array<int, array{
     *             word: string,
     *             offset: float,
     *             duration: float,
     *         }>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function textToSpeech(
        string $text,
        string $voice = 'en-US-AriaNeural',
        array $options = [],
    ): array {
        try {
            $ssml = $this->createSsml($text, $voice, $options);

            $response = $this->httpClient->request('POST', "{$this->endpoint}/cognitiveservices/v1", [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                    'Content-Type' => 'application/ssml+xml',
                    'X-Microsoft-OutputFormat' => $options['audio_format'] ?? 'riff-16khz-16bit-mono-pcm',
                ],
                'body' => $ssml,
            ] + $this->options);

            $audioData = base64_encode($response->getContent());

            return [
                'success' => true,
                'speech_synthesis' => [
                    'text' => $text,
                    'voice' => $voice,
                    'audio_data' => $audioData,
                    'audio_format' => $options['audio_format'] ?? 'riff-16khz-16bit-mono-pcm',
                    'duration' => $this->estimateAudioDuration($text),
                    'word_timing' => [],
                ],
                'processingTime' => 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'speech_synthesis' => [
                    'text' => $text,
                    'voice' => $voice,
                    'audio_data' => '',
                    'audio_format' => $options['audio_format'] ?? 'riff-16khz-16bit-mono-pcm',
                    'duration' => 0.0,
                    'word_timing' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search the web using Bing Search.
     *
     * @param string               $query   Search query
     * @param int                  $count   Number of results to return
     * @param array<string, mixed> $options Search options
     *
     * @return array{
     *     success: bool,
     *     web_search: array{
     *         query: string,
     *         count: int,
     *         search_results: array<int, array{
     *             name: string,
     *             url: string,
     *             display_url: string,
     *             snippet: string,
     *             date_published: string,
     *         }>,
     *         total_results: int,
     *         search_suggestions: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function searchWeb(
        string $query,
        int $count = 10,
        array $options = [],
    ): array {
        try {
            $response = $this->httpClient->request('GET', "{$this->endpoint}/bing/v7.0/search", [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                ],
                'query' => [
                    'q' => $query,
                    'count' => min($count, 50),
                    'offset' => $options['offset'] ?? 0,
                    'mkt' => $options['market'] ?? 'en-US',
                    'safesearch' => $options['safe_search'] ?? 'Moderate',
                ],
            ] + $this->options);

            $responseData = $response->toArray();
            $webPages = $responseData['webPages']['value'] ?? [];

            return [
                'success' => true,
                'web_search' => [
                    'query' => $query,
                    'count' => $count,
                    'search_results' => array_map(fn ($result) => [
                        'name' => $result['name'] ?? '',
                        'url' => $result['url'] ?? '',
                        'display_url' => $result['displayUrl'] ?? '',
                        'snippet' => $result['snippet'] ?? '',
                        'date_published' => $result['dateLastCrawled'] ?? '',
                    ], \array_slice($webPages, 0, $count)),
                    'total_results' => $responseData['webPages']['totalEstimatedMatches'] ?? 0,
                    'search_suggestions' => array_map(fn ($suggestion) => $suggestion['text'] ?? '', $responseData['queryContext']['alteredQuery'] ?? []),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'web_search' => [
                    'query' => $query,
                    'count' => $count,
                    'search_results' => [],
                    'total_results' => 0,
                    'search_suggestions' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Helper methods.
     */
    private function generateImageInsights(array $responseData): array
    {
        $insights = [];

        if (!empty($responseData['faces'])) {
            $insights[] = 'Image contains '.\count($responseData['faces']).' face(s)';
        }

        if (!empty($responseData['objects'])) {
            $insights[] = 'Detected objects: '.implode(', ', array_map(fn ($obj) => $obj['object'], $responseData['objects']));
        }

        return $insights;
    }

    private function extractWords(array $regions): array
    {
        $words = [];
        foreach ($regions as $region) {
            foreach ($region['lines'] ?? [] as $line) {
                foreach ($line['words'] ?? [] as $word) {
                    $words[] = [
                        'text' => $word['text'] ?? '',
                        'bounding_box' => array_map(fn ($point) => [
                            'x' => $point[0] ?? 0.0,
                            'y' => $point[1] ?? 0.0,
                        ], $word['boundingBox'] ?? []),
                        'confidence' => $word['confidence'] ?? 0.0,
                    ];
                }
            }
        }

        return $words;
    }

    private function extractLines(array $regions): array
    {
        $lines = [];
        foreach ($regions as $region) {
            foreach ($region['lines'] ?? [] as $line) {
                $lines[] = [
                    'text' => $line['text'] ?? '',
                    'bounding_box' => array_map(fn ($point) => [
                        'x' => $point[0] ?? 0.0,
                        'y' => $point[1] ?? 0.0,
                    ], $line['boundingBox'] ?? []),
                ];
            }
        }

        return $lines;
    }

    private function generateModerationRecommendations(array $responseData): array
    {
        $recommendations = [];

        if (($responseData['AdultClassificationScore'] ?? 0) > 0.5) {
            $recommendations[] = 'Content may contain adult material';
        }

        if (($responseData['RacyClassificationScore'] ?? 0) > 0.5) {
            $recommendations[] = 'Content may contain racy material';
        }

        return $recommendations;
    }

    private function createSsml(string $text, string $voice, array $options): string
    {
        $rate = $options['rate'] ?? 'medium';
        $pitch = $options['pitch'] ?? 'medium';
        $volume = $options['volume'] ?? 'medium';

        return <<<SSML
<speak version='1.0' xmlns='http://www.w3.org/2001/10/synthesis' xml:lang='en-US'>
    <voice name='{$voice}'>
        <prosody rate='{$rate}' pitch='{$pitch}' volume='{$volume}'>
            {$text}
        </prosody>
    </voice>
</speak>
SSML;
    }

    private function estimateAudioDuration(string $text): float
    {
        // Rough estimate: 150 words per minute
        $wordCount = str_word_count($text);

        return ($wordCount / 150) * 60;
    }
}

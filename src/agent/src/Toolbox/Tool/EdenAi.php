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
#[AsTool('edenai_text_generation', 'Tool that generates text using EdenAI')]
#[AsTool('edenai_text_to_speech', 'Tool that converts text to speech using EdenAI', method: 'textToSpeech')]
#[AsTool('edenai_speech_to_text', 'Tool that converts speech to text using EdenAI', method: 'speechToText')]
#[AsTool('edenai_image_generation', 'Tool that generates images using EdenAI', method: 'imageGeneration')]
#[AsTool('edenai_image_analysis', 'Tool that analyzes images using EdenAI', method: 'imageAnalysis')]
#[AsTool('edenai_translation', 'Tool that translates text using EdenAI', method: 'translation')]
#[AsTool('edenai_question_answer', 'Tool that answers questions using EdenAI', method: 'questionAnswer')]
#[AsTool('edenai_text_analysis', 'Tool that analyzes text using EdenAI', method: 'textAnalysis')]
final readonly class EdenAi
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.edenai.co/v2',
        private array $options = [],
    ) {
    }

    /**
     * Generate text using EdenAI.
     *
     * @param string               $text        Input text or prompt
     * @param string               $provider    Provider (openai, anthropic, google, etc.)
     * @param string               $model       Model name
     * @param int                  $maxTokens   Maximum tokens to generate
     * @param float                $temperature Temperature for generation
     * @param array<string, mixed> $parameters  Additional parameters
     *
     * @return array{
     *     success: bool,
     *     text: string,
     *     provider: string,
     *     model: string,
     *     usage: array{
     *         promptTokens: int,
     *         completionTokens: int,
     *         totalTokens: int,
     *     },
     *     cost: float,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $text,
        string $provider = 'openai',
        string $model = 'gpt-3.5-turbo',
        int $maxTokens = 1000,
        float $temperature = 0.7,
        array $parameters = [],
    ): array {
        try {
            $requestData = [
                'providers' => $provider,
                'text' => $text,
                'max_tokens' => max(1, min($maxTokens, 4000)),
                'temperature' => max(0.0, min($temperature, 2.0)),
                'parameters' => $parameters,
            ];

            if ('gpt-3.5-turbo' !== $model) {
                $requestData['settings'] = [
                    $provider => [
                        'model' => $model,
                    ],
                ];
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/text/generation", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $providerData = $data[$provider] ?? [];

            return [
                'success' => true,
                'text' => $providerData['generated_text'] ?? '',
                'provider' => $provider,
                'model' => $model,
                'usage' => [
                    'promptTokens' => $providerData['usage']['prompt_tokens'] ?? 0,
                    'completionTokens' => $providerData['usage']['completion_tokens'] ?? 0,
                    'totalTokens' => $providerData['usage']['total_tokens'] ?? 0,
                ],
                'cost' => $providerData['cost'] ?? 0.0,
                'processingTime' => $providerData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'text' => '',
                'provider' => $provider,
                'model' => $model,
                'usage' => ['promptTokens' => 0, 'completionTokens' => 0, 'totalTokens' => 0],
                'cost' => 0.0,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Convert text to speech using EdenAI.
     *
     * @param string $text         Text to convert
     * @param string $provider     Provider (amazon, google, microsoft, etc.)
     * @param string $language     Language code
     * @param string $voice        Voice name
     * @param string $outputFormat Output format (mp3, wav, ogg)
     *
     * @return array{
     *     success: bool,
     *     audioUrl: string,
     *     provider: string,
     *     voice: string,
     *     language: string,
     *     duration: float,
     *     fileSize: int,
     *     cost: float,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function textToSpeech(
        string $text,
        string $provider = 'amazon',
        string $language = 'en-US',
        string $voice = '',
        string $outputFormat = 'mp3',
    ): array {
        try {
            $requestData = [
                'providers' => $provider,
                'text' => $text,
                'language' => $language,
                'option' => $outputFormat,
            ];

            if ($voice) {
                $requestData['settings'] = [
                    $provider => [
                        'voice' => $voice,
                    ],
                ];
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/audio/text_to_speech", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $providerData = $data[$provider] ?? [];

            return [
                'success' => true,
                'audioUrl' => $providerData['audio_resource_url'] ?? '',
                'provider' => $provider,
                'voice' => $providerData['voice'] ?? $voice,
                'language' => $language,
                'duration' => $providerData['duration'] ?? 0.0,
                'fileSize' => $providerData['file_size'] ?? 0,
                'cost' => $providerData['cost'] ?? 0.0,
                'processingTime' => $providerData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'audioUrl' => '',
                'provider' => $provider,
                'voice' => $voice,
                'language' => $language,
                'duration' => 0.0,
                'fileSize' => 0,
                'cost' => 0.0,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Convert speech to text using EdenAI.
     *
     * @param string $audioUrl           URL to audio file
     * @param string $provider           Provider (google, amazon, microsoft, etc.)
     * @param string $language           Language code
     * @param bool   $punctuation        Enable punctuation
     * @param bool   $speakerDiarization Enable speaker diarization
     *
     * @return array{
     *     success: bool,
     *     text: string,
     *     provider: string,
     *     language: string,
     *     confidence: float,
     *     duration: float,
     *     speakers: array<int, array{
     *         speaker: string,
     *         text: string,
     *         startTime: float,
     *         endTime: float,
     *     }>,
     *     cost: float,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function speechToText(
        string $audioUrl,
        string $provider = 'google',
        string $language = 'en-US',
        bool $punctuation = true,
        bool $speakerDiarization = false,
    ): array {
        try {
            $requestData = [
                'providers' => $provider,
                'file_url' => $audioUrl,
                'language' => $language,
                'option' => 'punctuation',
            ];

            $settings = [];

            if ($punctuation) {
                $settings['punctuation'] = true;
            }

            if ($speakerDiarization) {
                $settings['speaker_diarization'] = true;
            }

            if (!empty($settings)) {
                $requestData['settings'] = [
                    $provider => $settings,
                ];
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/audio/speech_to_text_async", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $providerData = $data[$provider] ?? [];

            return [
                'success' => true,
                'text' => $providerData['text'] ?? '',
                'provider' => $provider,
                'language' => $language,
                'confidence' => $providerData['confidence'] ?? 0.0,
                'duration' => $providerData['duration'] ?? 0.0,
                'speakers' => array_map(fn ($speaker) => [
                    'speaker' => $speaker['speaker'] ?? '',
                    'text' => $speaker['text'] ?? '',
                    'startTime' => $speaker['start_time'] ?? 0.0,
                    'endTime' => $speaker['end_time'] ?? 0.0,
                ], $providerData['speakers'] ?? []),
                'cost' => $providerData['cost'] ?? 0.0,
                'processingTime' => $providerData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'text' => '',
                'provider' => $provider,
                'language' => $language,
                'confidence' => 0.0,
                'duration' => 0.0,
                'speakers' => [],
                'cost' => 0.0,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate images using EdenAI.
     *
     * @param string $prompt     Image generation prompt
     * @param string $provider   Provider (openai, stabilityai, replicate, etc.)
     * @param string $resolution Image resolution
     * @param int    $numImages  Number of images to generate
     * @param string $style      Image style
     *
     * @return array{
     *     success: bool,
     *     images: array<int, array{
     *         url: string,
     *         width: int,
     *         height: int,
     *         format: string,
     *     }>,
     *     provider: string,
     *     prompt: string,
     *     cost: float,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function imageGeneration(
        string $prompt,
        string $provider = 'openai',
        string $resolution = '1024x1024',
        int $numImages = 1,
        string $style = '',
    ): array {
        try {
            $requestData = [
                'providers' => $provider,
                'text' => $prompt,
                'resolution' => $resolution,
                'num_images' => max(1, min($numImages, 4)),
            ];

            if ($style) {
                $requestData['settings'] = [
                    $provider => [
                        'style' => $style,
                    ],
                ];
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/image/generation", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $providerData = $data[$provider] ?? [];

            return [
                'success' => true,
                'images' => array_map(fn ($image) => [
                    'url' => $image['image_resource_url'] ?? '',
                    'width' => $image['width'] ?? 1024,
                    'height' => $image['height'] ?? 1024,
                    'format' => $image['format'] ?? 'png',
                ], $providerData['items'] ?? []),
                'provider' => $provider,
                'prompt' => $prompt,
                'cost' => $providerData['cost'] ?? 0.0,
                'processingTime' => $providerData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'images' => [],
                'provider' => $provider,
                'prompt' => $prompt,
                'cost' => 0.0,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze images using EdenAI.
     *
     * @param string               $imageUrl URL to image file
     * @param string               $provider Provider (google, amazon, microsoft, etc.)
     * @param array<string, mixed> $features Features to extract
     *
     * @return array{
     *     success: bool,
     *     analysis: array{
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
     *         labels: array<int, array{
     *             name: string,
     *             confidence: float,
     *         }>,
     *     },
     *     provider: string,
     *     cost: float,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function imageAnalysis(
        string $imageUrl,
        string $provider = 'google',
        array $features = ['objects', 'faces', 'text', 'labels'],
    ): array {
        try {
            $requestData = [
                'providers' => $provider,
                'file_url' => $imageUrl,
                'features' => implode(',', $features),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/image/object_detection", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $providerData = $data[$provider] ?? [];

            return [
                'success' => true,
                'analysis' => [
                    'objects' => array_map(fn ($object) => [
                        'name' => $object['name'] ?? '',
                        'confidence' => $object['confidence'] ?? 0.0,
                        'boundingBox' => [
                            'x' => $object['bounding_box']['x'] ?? 0.0,
                            'y' => $object['bounding_box']['y'] ?? 0.0,
                            'width' => $object['bounding_box']['width'] ?? 0.0,
                            'height' => $object['bounding_box']['height'] ?? 0.0,
                        ],
                    ], $providerData['items'] ?? []),
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
                    ], $providerData['faces'] ?? []),
                    'text' => array_map(fn ($text) => [
                        'text' => $text['text'] ?? '',
                        'confidence' => $text['confidence'] ?? 0.0,
                        'boundingBox' => [
                            'x' => $text['bounding_box']['x'] ?? 0.0,
                            'y' => $text['bounding_box']['y'] ?? 0.0,
                            'width' => $text['bounding_box']['width'] ?? 0.0,
                            'height' => $text['bounding_box']['height'] ?? 0.0,
                        ],
                    ], $providerData['text'] ?? []),
                    'labels' => array_map(fn ($label) => [
                        'name' => $label['name'] ?? '',
                        'confidence' => $label['confidence'] ?? 0.0,
                    ], $providerData['labels'] ?? []),
                ],
                'provider' => $provider,
                'cost' => $providerData['cost'] ?? 0.0,
                'processingTime' => $providerData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'analysis' => [
                    'objects' => [],
                    'faces' => [],
                    'text' => [],
                    'labels' => [],
                ],
                'provider' => $provider,
                'cost' => 0.0,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Translate text using EdenAI.
     *
     * @param string $text           Text to translate
     * @param string $sourceLanguage Source language code
     * @param string $targetLanguage Target language code
     * @param string $provider       Provider (google, amazon, microsoft, etc.)
     *
     * @return array{
     *     success: bool,
     *     translatedText: string,
     *     sourceLanguage: string,
     *     targetLanguage: string,
     *     provider: string,
     *     confidence: float,
     *     cost: float,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function translation(
        string $text,
        string $sourceLanguage,
        string $targetLanguage,
        string $provider = 'google',
    ): array {
        try {
            $requestData = [
                'providers' => $provider,
                'text' => $text,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/translation/automatic_translation", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $providerData = $data[$provider] ?? [];

            return [
                'success' => true,
                'translatedText' => $providerData['text'] ?? '',
                'sourceLanguage' => $sourceLanguage,
                'targetLanguage' => $targetLanguage,
                'provider' => $provider,
                'confidence' => $providerData['confidence'] ?? 0.0,
                'cost' => $providerData['cost'] ?? 0.0,
                'processingTime' => $providerData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'translatedText' => '',
                'sourceLanguage' => $sourceLanguage,
                'targetLanguage' => $targetLanguage,
                'provider' => $provider,
                'confidence' => 0.0,
                'cost' => 0.0,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Answer questions using EdenAI.
     *
     * @param string $question Question to answer
     * @param string $context  Context for answering
     * @param string $provider Provider (openai, google, anthropic, etc.)
     * @param string $model    Model name
     *
     * @return array{
     *     success: bool,
     *     answer: string,
     *     confidence: float,
     *     provider: string,
     *     model: string,
     *     source: string,
     *     cost: float,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function questionAnswer(
        string $question,
        string $context,
        string $provider = 'openai',
        string $model = 'gpt-3.5-turbo',
    ): array {
        try {
            $requestData = [
                'providers' => $provider,
                'question' => $question,
                'context' => $context,
            ];

            if ('gpt-3.5-turbo' !== $model) {
                $requestData['settings'] = [
                    $provider => [
                        'model' => $model,
                    ],
                ];
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/text/question_answer", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $providerData = $data[$provider] ?? [];

            return [
                'success' => true,
                'answer' => $providerData['answer'] ?? '',
                'confidence' => $providerData['confidence'] ?? 0.0,
                'provider' => $provider,
                'model' => $model,
                'source' => $providerData['source'] ?? '',
                'cost' => $providerData['cost'] ?? 0.0,
                'processingTime' => $providerData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'answer' => '',
                'confidence' => 0.0,
                'provider' => $provider,
                'model' => $model,
                'source' => '',
                'cost' => 0.0,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze text using EdenAI.
     *
     * @param string               $text     Text to analyze
     * @param string               $provider Provider (google, amazon, microsoft, etc.)
     * @param array<string, mixed> $features Features to extract
     *
     * @return array{
     *     success: bool,
     *     analysis: array{
     *         sentiment: array{
     *             general: string,
     *             general_score: float,
     *             emotions: array<string, float>,
     *         },
     *         entities: array<int, array{
     *             text: string,
     *             type: string,
     *             confidence: float,
     *         }>,
     *         keywords: array<int, string>,
     *         language: string,
     *         languageConfidence: float,
     *     },
     *     provider: string,
     *     cost: float,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function textAnalysis(
        string $text,
        string $provider = 'google',
        array $features = ['sentiment', 'entities', 'keywords', 'language'],
    ): array {
        try {
            $requestData = [
                'providers' => $provider,
                'text' => $text,
                'features' => implode(',', $features),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/text/sentiment_analysis", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $providerData = $data[$provider] ?? [];

            return [
                'success' => true,
                'analysis' => [
                    'sentiment' => [
                        'general' => $providerData['sentiment'] ?? '',
                        'general_score' => $providerData['sentiment_score'] ?? 0.0,
                        'emotions' => $providerData['emotions'] ?? [],
                    ],
                    'entities' => array_map(fn ($entity) => [
                        'text' => $entity['text'] ?? '',
                        'type' => $entity['type'] ?? '',
                        'confidence' => $entity['confidence'] ?? 0.0,
                    ], $providerData['entities'] ?? []),
                    'keywords' => $providerData['keywords'] ?? [],
                    'language' => $providerData['language'] ?? '',
                    'languageConfidence' => $providerData['language_confidence'] ?? 0.0,
                ],
                'provider' => $provider,
                'cost' => $providerData['cost'] ?? 0.0,
                'processingTime' => $providerData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'analysis' => [
                    'sentiment' => ['general' => '', 'general_score' => 0.0, 'emotions' => []],
                    'entities' => [],
                    'keywords' => [],
                    'language' => '',
                    'languageConfidence' => 0.0,
                ],
                'provider' => $provider,
                'cost' => 0.0,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}

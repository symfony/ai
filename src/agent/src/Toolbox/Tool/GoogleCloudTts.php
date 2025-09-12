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
#[AsTool('google_cloud_tts_synthesize', 'Tool that synthesizes speech using Google Cloud Text-to-Speech')]
#[AsTool('google_cloud_tts_list_voices', 'Tool that lists available voices', method: 'listVoices')]
#[AsTool('google_cloud_tts_get_voice', 'Tool that gets details of a specific voice', method: 'getVoice')]
#[AsTool('google_cloud_tts_create_custom_voice', 'Tool that creates custom voice models', method: 'createCustomVoice')]
#[AsTool('google_cloud_tts_batch_synthesize', 'Tool that performs batch speech synthesis', method: 'batchSynthesize')]
#[AsTool('google_cloud_tts_ssml_synthesize', 'Tool that synthesizes SSML content', method: 'ssmlSynthesize')]
#[AsTool('google_cloud_tts_get_supported_languages', 'Tool that gets supported languages', method: 'getSupportedLanguages')]
#[AsTool('google_cloud_tts_estimate_cost', 'Tool that estimates synthesis cost', method: 'estimateCost')]
final readonly class GoogleCloudTts
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $projectId,
        private string $apiKey,
        private string $region = 'us-central1',
        private array $options = [],
    ) {
    }

    /**
     * Synthesize speech using Google Cloud Text-to-Speech.
     *
     * @param string               $text         Text to synthesize
     * @param string               $voiceName    Voice to use
     * @param string               $languageCode Language code
     * @param array<string, mixed> $audioConfig  Audio configuration
     * @param array<string, mixed> $options      Synthesis options
     *
     * @return array{
     *     success: bool,
     *     synthesis: array{
     *         text: string,
     *         voice_name: string,
     *         language_code: string,
     *         audio_config: array<string, mixed>,
     *         audio_content: string,
     *         audio_format: string,
     *         sample_rate: int,
     *         duration: float,
     *         character_count: int,
     *         synthesis_settings: array{
     *             pitch: float,
     *             speaking_rate: float,
     *             volume_gain_db: float,
     *             effects_profile_id: array<int, string>,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $text,
        string $voiceName = 'en-US-Wavenet-D',
        string $languageCode = 'en-US',
        array $audioConfig = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'input' => [
                    'text' => $text,
                ],
                'voice' => [
                    'languageCode' => $languageCode,
                    'name' => $voiceName,
                    'ssmlGender' => $options['ssml_gender'] ?? 'NEUTRAL',
                ],
                'audioConfig' => array_merge([
                    'audioEncoding' => $audioConfig['audio_encoding'] ?? 'MP3',
                    'sampleRateHertz' => $audioConfig['sample_rate'] ?? 22050,
                    'speakingRate' => $audioConfig['speaking_rate'] ?? 1.0,
                    'pitch' => $audioConfig['pitch'] ?? 0.0,
                    'volumeGainDb' => $audioConfig['volume_gain_db'] ?? 0.0,
                    'effectsProfileId' => $audioConfig['effects_profile_id'] ?? [],
                ], $audioConfig),
            ];

            $response = $this->httpClient->request('POST', "https://texttospeech.googleapis.com/v1/text:synthesize?key={$this->apiKey}", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $audioContent = $responseData['audioContent'] ?? '';

            return [
                'success' => !empty($audioContent),
                'synthesis' => [
                    'text' => $text,
                    'voice_name' => $voiceName,
                    'language_code' => $languageCode,
                    'audio_config' => $audioConfig,
                    'audio_content' => $audioContent,
                    'audio_format' => $audioConfig['audio_encoding'] ?? 'MP3',
                    'sample_rate' => $audioConfig['sample_rate'] ?? 22050,
                    'duration' => $this->estimateAudioDuration($text, $audioConfig['speaking_rate'] ?? 1.0),
                    'character_count' => \strlen($text),
                    'synthesis_settings' => [
                        'pitch' => $audioConfig['pitch'] ?? 0.0,
                        'speaking_rate' => $audioConfig['speaking_rate'] ?? 1.0,
                        'volume_gain_db' => $audioConfig['volume_gain_db'] ?? 0.0,
                        'effects_profile_id' => $audioConfig['effects_profile_id'] ?? [],
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'synthesis' => [
                    'text' => $text,
                    'voice_name' => $voiceName,
                    'language_code' => $languageCode,
                    'audio_config' => $audioConfig,
                    'audio_content' => '',
                    'audio_format' => $audioConfig['audio_encoding'] ?? 'MP3',
                    'sample_rate' => $audioConfig['sample_rate'] ?? 22050,
                    'duration' => 0.0,
                    'character_count' => \strlen($text),
                    'synthesis_settings' => [
                        'pitch' => 0.0,
                        'speaking_rate' => 1.0,
                        'volume_gain_db' => 0.0,
                        'effects_profile_id' => [],
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List available voices.
     *
     * @param string               $languageCode Language code filter
     * @param array<string, mixed> $options      List options
     *
     * @return array{
     *     success: bool,
     *     voices: array{
     *         language_code: string,
     *         available_voices: array<int, array{
     *             name: string,
     *             language_codes: array<int, string>,
     *             gender: string,
     *             natural_sample_rate_hertz: int,
     *             voice_type: string,
     *         }>,
     *         total_voices: int,
     *         supported_languages: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function listVoices(
        string $languageCode = '',
        array $options = [],
    ): array {
        try {
            $query = ['key' => $this->apiKey];
            if ($languageCode) {
                $query['languageCode'] = $languageCode;
            }

            $response = $this->httpClient->request('GET', 'https://texttospeech.googleapis.com/v1/voices', [
                'query' => $query,
            ] + $this->options);

            $responseData = $response->toArray();
            $voices = $responseData['voices'] ?? [];

            return [
                'success' => true,
                'voices' => [
                    'language_code' => $languageCode,
                    'available_voices' => array_map(fn ($voice) => [
                        'name' => $voice['name'] ?? '',
                        'language_codes' => $voice['languageCodes'] ?? [],
                        'gender' => $voice['ssmlGender'] ?? '',
                        'natural_sample_rate_hertz' => $voice['naturalSampleRateHertz'] ?? 22050,
                        'voice_type' => $voice['name'] ? (str_contains($voice['name'], 'Wavenet') ? 'Wavenet' : 'Standard') : 'Standard',
                    ], $voices),
                    'total_voices' => \count($voices),
                    'supported_languages' => array_unique(array_merge(...array_map(fn ($voice) => $voice['languageCodes'] ?? [], $voices))),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'voices' => [
                    'language_code' => $languageCode,
                    'available_voices' => [],
                    'total_voices' => 0,
                    'supported_languages' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get details of a specific voice.
     *
     * @param string               $voiceName Voice name
     * @param array<string, mixed> $options   Voice options
     *
     * @return array{
     *     success: bool,
     *     voice_details: array{
     *         voice_name: string,
     *         language_codes: array<int, string>,
     *         gender: string,
     *         natural_sample_rate_hertz: int,
     *         voice_type: string,
     *         supported_audio_formats: array<int, string>,
     *         supported_effects: array<int, string>,
     *         characteristics: array{
     *             pitch_range: array{
     *                 min: float,
     *                 max: float,
     *             },
     *             speaking_rate_range: array{
     *                 min: float,
     *                 max: float,
     *             },
     *             volume_gain_range: array{
     *                 min: float,
     *                 max: float,
     *             },
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function getVoice(
        string $voiceName,
        array $options = [],
    ): array {
        try {
            $response = $this->httpClient->request('GET', "https://texttospeech.googleapis.com/v1/voices/{$voiceName}", [
                'query' => ['key' => $this->apiKey],
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'voice_details' => [
                    'voice_name' => $voiceName,
                    'language_codes' => $responseData['languageCodes'] ?? [],
                    'gender' => $responseData['ssmlGender'] ?? '',
                    'natural_sample_rate_hertz' => $responseData['naturalSampleRateHertz'] ?? 22050,
                    'voice_type' => str_contains($voiceName, 'Wavenet') ? 'Wavenet' : 'Standard',
                    'supported_audio_formats' => ['MP3', 'LINEAR16', 'OGG_OPUS', 'MULAW', 'ALAW'],
                    'supported_effects' => ['headphone-class-device', 'large-automotive-class-device', 'medium-bluetooth-speaker-class-device'],
                    'characteristics' => [
                        'pitch_range' => [
                            'min' => -20.0,
                            'max' => 20.0,
                        ],
                        'speaking_rate_range' => [
                            'min' => 0.25,
                            'max' => 4.0,
                        ],
                        'volume_gain_range' => [
                            'min' => -96.0,
                            'max' => 16.0,
                        ],
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'voice_details' => [
                    'voice_name' => $voiceName,
                    'language_codes' => [],
                    'gender' => '',
                    'natural_sample_rate_hertz' => 0,
                    'voice_type' => '',
                    'supported_audio_formats' => [],
                    'supported_effects' => [],
                    'characteristics' => [
                        'pitch_range' => ['min' => 0.0, 'max' => 0.0],
                        'speaking_rate_range' => ['min' => 0.0, 'max' => 0.0],
                        'volume_gain_range' => ['min' => 0.0, 'max' => 0.0],
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create custom voice models.
     *
     * @param string               $voiceName    Custom voice name
     * @param array<string, mixed> $trainingData Training data configuration
     * @param array<string, mixed> $options      Custom voice options
     *
     * @return array{
     *     success: bool,
     *     custom_voice: array{
     *         voice_name: string,
     *         training_data: array<string, mixed>,
     *         status: string,
     *         voice_id: string,
     *         creation_time: string,
     *         training_progress: array{
     *             percentage: float,
     *             current_step: string,
     *             estimated_completion: string,
     *         },
     *         voice_characteristics: array{
     *             language_code: string,
     *             gender: string,
     *             sample_rate: int,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function createCustomVoice(
        string $voiceName,
        array $trainingData = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'name' => $voiceName,
                'languageCode' => $trainingData['language_code'] ?? 'en-US',
                'ssmlGender' => $trainingData['gender'] ?? 'NEUTRAL',
                'naturalSampleRateHertz' => $trainingData['sample_rate'] ?? 22050,
                'trainingData' => [
                    'audioData' => $trainingData['audio_data'] ?? '',
                    'textData' => $trainingData['text_data'] ?? '',
                ],
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "https://texttospeech.googleapis.com/v1/projects/{$this->projectId}/locations/{$this->region}/customVoices", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$this->apiKey}",
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'custom_voice' => [
                    'voice_name' => $voiceName,
                    'training_data' => $trainingData,
                    'status' => $responseData['state'] ?? 'PENDING',
                    'voice_id' => $responseData['name'] ?? '',
                    'creation_time' => $responseData['createTime'] ?? date('c'),
                    'training_progress' => [
                        'percentage' => $responseData['progressPercentage'] ?? 0.0,
                        'current_step' => $responseData['currentStep'] ?? 'Initializing',
                        'estimated_completion' => $responseData['estimatedCompletionTime'] ?? '',
                    ],
                    'voice_characteristics' => [
                        'language_code' => $trainingData['language_code'] ?? 'en-US',
                        'gender' => $trainingData['gender'] ?? 'NEUTRAL',
                        'sample_rate' => $trainingData['sample_rate'] ?? 22050,
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'custom_voice' => [
                    'voice_name' => $voiceName,
                    'training_data' => $trainingData,
                    'status' => 'FAILED',
                    'voice_id' => '',
                    'creation_time' => '',
                    'training_progress' => [
                        'percentage' => 0.0,
                        'current_step' => 'Failed',
                        'estimated_completion' => '',
                    ],
                    'voice_characteristics' => [
                        'language_code' => 'en-US',
                        'gender' => 'NEUTRAL',
                        'sample_rate' => 22050,
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Perform batch speech synthesis.
     *
     * @param array<int, string>   $texts        Array of texts to synthesize
     * @param string               $voiceName    Voice to use
     * @param string               $languageCode Language code
     * @param array<string, mixed> $audioConfig  Audio configuration
     * @param array<string, mixed> $options      Batch options
     *
     * @return array{
     *     success: bool,
     *     batch_synthesis: array{
     *         texts: array<int, string>,
     *         voice_name: string,
     *         language_code: string,
     *         audio_config: array<string, mixed>,
     *         synthesized_audios: array<int, array{
     *             text: string,
     *             audio_content: string,
     *             duration: float,
     *             character_count: int,
     *         }>,
     *         total_duration: float,
     *         total_characters: int,
     *         processing_summary: array{
     *             successful: int,
     *             failed: int,
     *             total: int,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function batchSynthesize(
        array $texts,
        string $voiceName = 'en-US-Wavenet-D',
        string $languageCode = 'en-US',
        array $audioConfig = [],
        array $options = [],
    ): array {
        try {
            $synthesizedAudios = [];
            $successful = 0;
            $failed = 0;

            foreach ($texts as $index => $text) {
                $result = $this->__invoke($text, $voiceName, $languageCode, $audioConfig, $options);

                if ($result['success']) {
                    $synthesizedAudios[] = [
                        'text' => $text,
                        'audio_content' => $result['synthesis']['audio_content'],
                        'duration' => $result['synthesis']['duration'],
                        'character_count' => $result['synthesis']['character_count'],
                    ];
                    ++$successful;
                } else {
                    ++$failed;
                }
            }

            $totalDuration = array_sum(array_map(fn ($audio) => $audio['duration'], $synthesizedAudios));
            $totalCharacters = array_sum(array_map(fn ($audio) => $audio['character_count'], $synthesizedAudios));

            return [
                'success' => $successful > 0,
                'batch_synthesis' => [
                    'texts' => $texts,
                    'voice_name' => $voiceName,
                    'language_code' => $languageCode,
                    'audio_config' => $audioConfig,
                    'synthesized_audios' => $synthesizedAudios,
                    'total_duration' => $totalDuration,
                    'total_characters' => $totalCharacters,
                    'processing_summary' => [
                        'successful' => $successful,
                        'failed' => $failed,
                        'total' => \count($texts),
                    ],
                ],
                'processingTime' => 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'batch_synthesis' => [
                    'texts' => $texts,
                    'voice_name' => $voiceName,
                    'language_code' => $languageCode,
                    'audio_config' => $audioConfig,
                    'synthesized_audios' => [],
                    'total_duration' => 0.0,
                    'total_characters' => 0,
                    'processing_summary' => [
                        'successful' => 0,
                        'failed' => \count($texts),
                        'total' => \count($texts),
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Synthesize SSML content.
     *
     * @param string               $ssmlContent SSML content to synthesize
     * @param string               $voiceName   Voice to use
     * @param array<string, mixed> $audioConfig Audio configuration
     * @param array<string, mixed> $options     SSML options
     *
     * @return array{
     *     success: bool,
     *     ssml_synthesis: array{
     *         ssml_content: string,
     *         voice_name: string,
     *         audio_content: string,
     *         audio_format: string,
     *         duration: float,
     *         character_count: int,
     *         ssml_elements: array<int, string>,
     *         synthesis_settings: array{
     *             pitch: float,
     *             speaking_rate: float,
     *             volume_gain_db: float,
     *             effects_profile_id: array<int, string>,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function ssmlSynthesize(
        string $ssmlContent,
        string $voiceName = 'en-US-Wavenet-D',
        array $audioConfig = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'input' => [
                    'ssml' => $ssmlContent,
                ],
                'voice' => [
                    'languageCode' => $options['language_code'] ?? 'en-US',
                    'name' => $voiceName,
                    'ssmlGender' => $options['ssml_gender'] ?? 'NEUTRAL',
                ],
                'audioConfig' => array_merge([
                    'audioEncoding' => $audioConfig['audio_encoding'] ?? 'MP3',
                    'sampleRateHertz' => $audioConfig['sample_rate'] ?? 22050,
                    'speakingRate' => $audioConfig['speaking_rate'] ?? 1.0,
                    'pitch' => $audioConfig['pitch'] ?? 0.0,
                    'volumeGainDb' => $audioConfig['volume_gain_db'] ?? 0.0,
                    'effectsProfileId' => $audioConfig['effects_profile_id'] ?? [],
                ], $audioConfig),
            ];

            $response = $this->httpClient->request('POST', "https://texttospeech.googleapis.com/v1/text:synthesize?key={$this->apiKey}", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $audioContent = $responseData['audioContent'] ?? '';

            return [
                'success' => !empty($audioContent),
                'ssml_synthesis' => [
                    'ssml_content' => $ssmlContent,
                    'voice_name' => $voiceName,
                    'audio_content' => $audioContent,
                    'audio_format' => $audioConfig['audio_encoding'] ?? 'MP3',
                    'duration' => $this->estimateSsmlDuration($ssmlContent, $audioConfig['speaking_rate'] ?? 1.0),
                    'character_count' => \strlen(strip_tags($ssmlContent)),
                    'ssml_elements' => $this->extractSsmlElements($ssmlContent),
                    'synthesis_settings' => [
                        'pitch' => $audioConfig['pitch'] ?? 0.0,
                        'speaking_rate' => $audioConfig['speaking_rate'] ?? 1.0,
                        'volume_gain_db' => $audioConfig['volume_gain_db'] ?? 0.0,
                        'effects_profile_id' => $audioConfig['effects_profile_id'] ?? [],
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'ssml_synthesis' => [
                    'ssml_content' => $ssmlContent,
                    'voice_name' => $voiceName,
                    'audio_content' => '',
                    'audio_format' => $audioConfig['audio_encoding'] ?? 'MP3',
                    'duration' => 0.0,
                    'character_count' => 0,
                    'ssml_elements' => [],
                    'synthesis_settings' => [
                        'pitch' => 0.0,
                        'speaking_rate' => 1.0,
                        'volume_gain_db' => 0.0,
                        'effects_profile_id' => [],
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get supported languages.
     *
     * @param array<string, mixed> $options Language options
     *
     * @return array{
     *     success: bool,
     *     supported_languages: array{
     *         languages: array<int, array{
     *             language_code: string,
     *             language_name: string,
     *             available_voices: int,
     *             supported_features: array<int, string>,
     *         }>,
     *         total_languages: int,
     *         regions: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function getSupportedLanguages(
        array $options = [],
    ): array {
        try {
            $voicesResult = $this->listVoices('', $options);

            if (!$voicesResult['success']) {
                throw new \Exception('Failed to fetch voices.');
            }

            $languages = [];
            $languageMap = $voicesResult['voices']['supported_languages'];

            foreach ($languageMap as $languageCode) {
                $languageVoices = array_filter($voicesResult['voices']['available_voices'],
                    fn ($voice) => \in_array($languageCode, $voice['language_codes']));

                $languages[] = [
                    'language_code' => $languageCode,
                    'language_name' => $this->getLanguageName($languageCode),
                    'available_voices' => \count($languageVoices),
                    'supported_features' => ['neural_voices', 'standard_voices', 'ssml', 'custom_voices'],
                ];
            }

            return [
                'success' => true,
                'supported_languages' => [
                    'languages' => $languages,
                    'total_languages' => \count($languages),
                    'regions' => array_unique(array_map(fn ($lang) => substr($lang, -2), $languageMap)),
                ],
                'processingTime' => $voicesResult['processingTime'],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'supported_languages' => [
                    'languages' => [],
                    'total_languages' => 0,
                    'regions' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Estimate synthesis cost.
     *
     * @param string               $text      Text to estimate cost for
     * @param string               $voiceType Voice type (Standard/Wavenet)
     * @param array<string, mixed> $options   Cost estimation options
     *
     * @return array{
     *     success: bool,
     *     cost_estimation: array{
     *         text: string,
     *         voice_type: string,
     *         character_count: int,
     *         estimated_cost: array{
     *             standard_voice_cost: float,
     *             wavenet_voice_cost: float,
     *             neural_voice_cost: float,
     *             selected_voice_cost: float,
     *         },
     *         pricing_tiers: array{
     *             free_tier_limit: int,
     *             paid_tier_rate: float,
     *             currency: string,
     *         },
     *         recommendations: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function estimateCost(
        string $text,
        string $voiceType = 'Wavenet',
        array $options = [],
    ): array {
        try {
            $characterCount = \strlen($text);

            // Google Cloud TTS pricing (as of 2024)
            $standardVoiceRate = 4.00 / 1000000; // $4 per 1M characters
            $wavenetVoiceRate = 16.00 / 1000000; // $16 per 1M characters
            $neuralVoiceRate = 16.00 / 1000000; // $16 per 1M characters

            $freeTierLimit = 1000000; // 1M characters per month free
            $currency = 'USD';

            $costs = [
                'standard_voice_cost' => max(0, ($characterCount - $freeTierLimit) * $standardVoiceRate),
                'wavenet_voice_cost' => max(0, ($characterCount - $freeTierLimit) * $wavenetVoiceRate),
                'neural_voice_cost' => max(0, ($characterCount - $freeTierLimit) * $neuralVoiceRate),
            ];

            $selectedCost = match ($voiceType) {
                'Standard' => $costs['standard_voice_cost'],
                'Wavenet', 'Neural' => $costs['wavenet_voice_cost'],
                default => $costs['wavenet_voice_cost'],
            };

            $recommendations = [];
            if ($characterCount > $freeTierLimit) {
                $recommendations[] = 'Consider using Standard voice for cost savings';
                $recommendations[] = 'Batch multiple texts together for efficiency';
            }

            return [
                'success' => true,
                'cost_estimation' => [
                    'text' => $text,
                    'voice_type' => $voiceType,
                    'character_count' => $characterCount,
                    'estimated_cost' => array_merge($costs, ['selected_voice_cost' => $selectedCost]),
                    'pricing_tiers' => [
                        'free_tier_limit' => $freeTierLimit,
                        'paid_tier_rate' => $wavenetVoiceRate,
                        'currency' => $currency,
                    ],
                    'recommendations' => $recommendations,
                ],
                'processingTime' => 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'cost_estimation' => [
                    'text' => $text,
                    'voice_type' => $voiceType,
                    'character_count' => 0,
                    'estimated_cost' => [
                        'standard_voice_cost' => 0.0,
                        'wavenet_voice_cost' => 0.0,
                        'neural_voice_cost' => 0.0,
                        'selected_voice_cost' => 0.0,
                    ],
                    'pricing_tiers' => [
                        'free_tier_limit' => 0,
                        'paid_tier_rate' => 0.0,
                        'currency' => 'USD',
                    ],
                    'recommendations' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Helper methods.
     */
    private function estimateAudioDuration(string $text, float $speakingRate = 1.0): float
    {
        // Rough estimate: 150 words per minute at normal rate
        $wordCount = str_word_count($text);
        $baseDuration = ($wordCount / 150) * 60; // seconds

        return $baseDuration / $speakingRate;
    }

    private function estimateSsmlDuration(string $ssml, float $speakingRate = 1.0): float
    {
        $text = strip_tags($ssml);

        return $this->estimateAudioDuration($text, $speakingRate);
    }

    private function extractSsmlElements(string $ssml): array
    {
        preg_match_all('/<(\w+)[^>]*>/', $ssml, $matches);

        return array_unique($matches[1]);
    }

    private function getLanguageName(string $languageCode): string
    {
        $languageNames = [
            'en-US' => 'English (US)',
            'en-GB' => 'English (UK)',
            'es-ES' => 'Spanish (Spain)',
            'fr-FR' => 'French (France)',
            'de-DE' => 'German (Germany)',
            'it-IT' => 'Italian (Italy)',
            'pt-BR' => 'Portuguese (Brazil)',
            'ja-JP' => 'Japanese (Japan)',
            'ko-KR' => 'Korean (South Korea)',
            'zh-CN' => 'Chinese (Simplified)',
            'zh-TW' => 'Chinese (Traditional)',
            'ar-XA' => 'Arabic (Gulf)',
            'hi-IN' => 'Hindi (India)',
            'ru-RU' => 'Russian (Russia)',
            'nl-NL' => 'Dutch (Netherlands)',
            'sv-SE' => 'Swedish (Sweden)',
            'no-NO' => 'Norwegian (Norway)',
            'da-DK' => 'Danish (Denmark)',
            'fi-FI' => 'Finnish (Finland)',
            'pl-PL' => 'Polish (Poland)',
        ];

        return $languageNames[$languageCode] ?? $languageCode;
    }
}

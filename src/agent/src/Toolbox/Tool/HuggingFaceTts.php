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
#[AsTool('huggingface_tts', 'Tool that converts text to speech using HuggingFace TTS')]
#[AsTool('huggingface_voice_clone', 'Tool that clones voices', method: 'voiceClone')]
#[AsTool('huggingface_voice_synthesis', 'Tool that synthesizes speech', method: 'voiceSynthesis')]
#[AsTool('huggingface_emotion_control', 'Tool that controls emotion in speech', method: 'emotionControl')]
#[AsTool('huggingface_speed_control', 'Tool that controls speech speed', method: 'speedControl')]
#[AsTool('huggingface_pitch_control', 'Tool that controls pitch', method: 'pitchControl')]
#[AsTool('huggingface_batch_tts', 'Tool that processes batch TTS', method: 'batchTts')]
#[AsTool('huggingface_voice_analysis', 'Tool that analyzes voice characteristics', method: 'voiceAnalysis')]
final readonly class HuggingFaceTts
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api-inference.huggingface.co/models',
        private array $options = [],
    ) {
    }

    /**
     * Convert text to speech using HuggingFace TTS.
     *
     * @param string               $text     Text to convert to speech
     * @param string               $voice    Voice model to use
     * @param string               $language Language of the text
     * @param array<string, mixed> $options  TTS options
     *
     * @return array{
     *     success: bool,
     *     tts_result: array{
     *         text: string,
     *         voice: string,
     *         language: string,
     *         audio_url: string,
     *         audio_format: string,
     *         duration: float,
     *         sample_rate: int,
     *         bit_rate: int,
     *         file_size: int,
     *         metadata: array{
     *             model_used: string,
     *             generation_time: float,
     *             quality_score: float,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $text,
        string $voice = 'default',
        string $language = 'en',
        array $options = [],
    ): array {
        try {
            $requestData = [
                'inputs' => $text,
                'parameters' => array_merge([
                    'voice' => $voice,
                    'language' => $language,
                ], $options),
            ];

            $modelEndpoint = $this->getModelEndpoint($voice, $language);
            $response = $this->httpClient->request('POST', "{$this->baseUrl}/{$modelEndpoint}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $audioData = $responseData[0] ?? [];

            return [
                'success' => true,
                'tts_result' => [
                    'text' => $text,
                    'voice' => $voice,
                    'language' => $language,
                    'audio_url' => $audioData['audio_url'] ?? '',
                    'audio_format' => $audioData['audio_format'] ?? 'mp3',
                    'duration' => $audioData['duration'] ?? 0.0,
                    'sample_rate' => $audioData['sample_rate'] ?? 22050,
                    'bit_rate' => $audioData['bit_rate'] ?? 128,
                    'file_size' => $audioData['file_size'] ?? 0,
                    'metadata' => [
                        'model_used' => $modelEndpoint,
                        'generation_time' => $responseData['generation_time'] ?? 0.0,
                        'quality_score' => $audioData['quality_score'] ?? 0.0,
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'tts_result' => [
                    'text' => $text,
                    'voice' => $voice,
                    'language' => $language,
                    'audio_url' => '',
                    'audio_format' => 'mp3',
                    'duration' => 0.0,
                    'sample_rate' => 22050,
                    'bit_rate' => 128,
                    'file_size' => 0,
                    'metadata' => [
                        'model_used' => '',
                        'generation_time' => 0.0,
                        'quality_score' => 0.0,
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clone voices.
     *
     * @param string               $referenceAudio Reference audio file URL or base64
     * @param string               $targetText     Text to synthesize with cloned voice
     * @param array<string, mixed> $options        Voice cloning options
     *
     * @return array{
     *     success: bool,
     *     voice_clone: array{
     *         reference_audio: string,
     *         target_text: string,
     *         cloned_audio_url: string,
     *         similarity_score: float,
     *         voice_characteristics: array{
     *             pitch: float,
     *             speed: float,
     *             tone: string,
     *             accent: string,
     *         },
     *         processing_metadata: array{
     *             model_used: string,
     *             processing_time: float,
     *             quality_metrics: array<string, float>,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function voiceClone(
        string $referenceAudio,
        string $targetText,
        array $options = [],
    ): array {
        try {
            $requestData = [
                'reference_audio' => $referenceAudio,
                'target_text' => $targetText,
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/voice-clone", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $voiceClone = $responseData['voice_clone'] ?? [];

            return [
                'success' => true,
                'voice_clone' => [
                    'reference_audio' => $referenceAudio,
                    'target_text' => $targetText,
                    'cloned_audio_url' => $voiceClone['cloned_audio_url'] ?? '',
                    'similarity_score' => $voiceClone['similarity_score'] ?? 0.0,
                    'voice_characteristics' => [
                        'pitch' => $voiceClone['voice_characteristics']['pitch'] ?? 0.0,
                        'speed' => $voiceClone['voice_characteristics']['speed'] ?? 1.0,
                        'tone' => $voiceClone['voice_characteristics']['tone'] ?? 'neutral',
                        'accent' => $voiceClone['voice_characteristics']['accent'] ?? 'neutral',
                    ],
                    'processing_metadata' => [
                        'model_used' => $voiceClone['processing_metadata']['model_used'] ?? '',
                        'processing_time' => $voiceClone['processing_metadata']['processing_time'] ?? 0.0,
                        'quality_metrics' => $voiceClone['processing_metadata']['quality_metrics'] ?? [],
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'voice_clone' => [
                    'reference_audio' => $referenceAudio,
                    'target_text' => $targetText,
                    'cloned_audio_url' => '',
                    'similarity_score' => 0.0,
                    'voice_characteristics' => [
                        'pitch' => 0.0,
                        'speed' => 1.0,
                        'tone' => 'neutral',
                        'accent' => 'neutral',
                    ],
                    'processing_metadata' => [
                        'model_used' => '',
                        'processing_time' => 0.0,
                        'quality_metrics' => [],
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Synthesize speech with advanced options.
     *
     * @param string               $text          Text to synthesize
     * @param array<string, mixed> $voiceSettings Voice settings
     * @param array<string, mixed> $audioSettings Audio settings
     *
     * @return array{
     *     success: bool,
     *     voice_synthesis: array{
     *         text: string,
     *         voice_settings: array<string, mixed>,
     *         audio_settings: array<string, mixed>,
     *         synthesized_audio: array{
     *             audio_url: string,
     *             format: string,
     *             duration: float,
     *             sample_rate: int,
     *             channels: int,
     *             bit_rate: int,
     *         },
     *         synthesis_metrics: array{
     *             naturalness_score: float,
     *             intelligibility_score: float,
     *             emotion_accuracy: float,
     *             pronunciation_accuracy: float,
     *         },
     *         processing_info: array{
     *             model_version: string,
     *             processing_time: float,
     *             memory_usage: float,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function voiceSynthesis(
        string $text,
        array $voiceSettings = [],
        array $audioSettings = [],
    ): array {
        try {
            $requestData = [
                'text' => $text,
                'voice_settings' => $voiceSettings,
                'audio_settings' => $audioSettings,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/voice-synthesis", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $voiceSynthesis = $responseData['voice_synthesis'] ?? [];

            return [
                'success' => true,
                'voice_synthesis' => [
                    'text' => $text,
                    'voice_settings' => $voiceSettings,
                    'audio_settings' => $audioSettings,
                    'synthesized_audio' => [
                        'audio_url' => $voiceSynthesis['synthesized_audio']['audio_url'] ?? '',
                        'format' => $voiceSynthesis['synthesized_audio']['format'] ?? 'mp3',
                        'duration' => $voiceSynthesis['synthesized_audio']['duration'] ?? 0.0,
                        'sample_rate' => $voiceSynthesis['synthesized_audio']['sample_rate'] ?? 22050,
                        'channels' => $voiceSynthesis['synthesized_audio']['channels'] ?? 1,
                        'bit_rate' => $voiceSynthesis['synthesized_audio']['bit_rate'] ?? 128,
                    ],
                    'synthesis_metrics' => [
                        'naturalness_score' => $voiceSynthesis['synthesis_metrics']['naturalness_score'] ?? 0.0,
                        'intelligibility_score' => $voiceSynthesis['synthesis_metrics']['intelligibility_score'] ?? 0.0,
                        'emotion_accuracy' => $voiceSynthesis['synthesis_metrics']['emotion_accuracy'] ?? 0.0,
                        'pronunciation_accuracy' => $voiceSynthesis['synthesis_metrics']['pronunciation_accuracy'] ?? 0.0,
                    ],
                    'processing_info' => [
                        'model_version' => $voiceSynthesis['processing_info']['model_version'] ?? '',
                        'processing_time' => $voiceSynthesis['processing_info']['processing_time'] ?? 0.0,
                        'memory_usage' => $voiceSynthesis['processing_info']['memory_usage'] ?? 0.0,
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'voice_synthesis' => [
                    'text' => $text,
                    'voice_settings' => $voiceSettings,
                    'audio_settings' => $audioSettings,
                    'synthesized_audio' => [
                        'audio_url' => '',
                        'format' => 'mp3',
                        'duration' => 0.0,
                        'sample_rate' => 22050,
                        'channels' => 1,
                        'bit_rate' => 128,
                    ],
                    'synthesis_metrics' => [
                        'naturalness_score' => 0.0,
                        'intelligibility_score' => 0.0,
                        'emotion_accuracy' => 0.0,
                        'pronunciation_accuracy' => 0.0,
                    ],
                    'processing_info' => [
                        'model_version' => '',
                        'processing_time' => 0.0,
                        'memory_usage' => 0.0,
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Control emotion in speech.
     *
     * @param string               $text      Text to synthesize
     * @param string               $emotion   Emotion to apply
     * @param float                $intensity Emotion intensity (0.0 to 1.0)
     * @param array<string, mixed> $options   Emotion control options
     *
     * @return array{
     *     success: bool,
     *     emotion_control: array{
     *         text: string,
     *         emotion: string,
     *         intensity: float,
     *         emotional_audio: array{
     *             audio_url: string,
     *             format: string,
     *             duration: float,
     *             emotion_markers: array<int, array{
     *                 timestamp: float,
     *                 emotion: string,
     *                 confidence: float,
     *             }>,
     *         },
     *         emotion_analysis: array{
     *             detected_emotions: array<string, float>,
     *             emotion_transitions: array<int, array{
     *                 from_emotion: string,
     *                 to_emotion: string,
     *                 transition_time: float,
     *             }>,
     *             overall_sentiment: string,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function emotionControl(
        string $text,
        string $emotion = 'neutral',
        float $intensity = 0.5,
        array $options = [],
    ): array {
        try {
            $requestData = [
                'text' => $text,
                'emotion' => $emotion,
                'intensity' => max(0.0, min($intensity, 1.0)),
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/emotion-control", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $emotionControl = $responseData['emotion_control'] ?? [];

            return [
                'success' => true,
                'emotion_control' => [
                    'text' => $text,
                    'emotion' => $emotion,
                    'intensity' => $intensity,
                    'emotional_audio' => [
                        'audio_url' => $emotionControl['emotional_audio']['audio_url'] ?? '',
                        'format' => $emotionControl['emotional_audio']['format'] ?? 'mp3',
                        'duration' => $emotionControl['emotional_audio']['duration'] ?? 0.0,
                        'emotion_markers' => array_map(fn ($marker) => [
                            'timestamp' => $marker['timestamp'] ?? 0.0,
                            'emotion' => $marker['emotion'] ?? '',
                            'confidence' => $marker['confidence'] ?? 0.0,
                        ], $emotionControl['emotional_audio']['emotion_markers'] ?? []),
                    ],
                    'emotion_analysis' => [
                        'detected_emotions' => $emotionControl['emotion_analysis']['detected_emotions'] ?? [],
                        'emotion_transitions' => array_map(fn ($transition) => [
                            'from_emotion' => $transition['from_emotion'] ?? '',
                            'to_emotion' => $transition['to_emotion'] ?? '',
                            'transition_time' => $transition['transition_time'] ?? 0.0,
                        ], $emotionControl['emotion_analysis']['emotion_transitions'] ?? []),
                        'overall_sentiment' => $emotionControl['emotion_analysis']['overall_sentiment'] ?? 'neutral',
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'emotion_control' => [
                    'text' => $text,
                    'emotion' => $emotion,
                    'intensity' => $intensity,
                    'emotional_audio' => [
                        'audio_url' => '',
                        'format' => 'mp3',
                        'duration' => 0.0,
                        'emotion_markers' => [],
                    ],
                    'emotion_analysis' => [
                        'detected_emotions' => [],
                        'emotion_transitions' => [],
                        'overall_sentiment' => 'neutral',
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Control speech speed.
     *
     * @param string               $text    Text to synthesize
     * @param float                $speed   Speech speed (0.5 to 2.0)
     * @param array<string, mixed> $options Speed control options
     *
     * @return array{
     *     success: bool,
     *     speed_control: array{
     *         text: string,
     *         speed: float,
     *         speed_controlled_audio: array{
     *             audio_url: string,
     *             format: string,
     *             duration: float,
     *             original_duration: float,
     *             speed_factor: float,
     *         },
     *         timing_analysis: array{
     *             word_timings: array<int, array{
     *                 word: string,
     *                 start_time: float,
     *                 end_time: float,
     *                 duration: float,
     *             }>,
     *             pause_durations: array<int, float>,
     *             rhythm_pattern: array<string, mixed>,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function speedControl(
        string $text,
        float $speed = 1.0,
        array $options = [],
    ): array {
        try {
            $requestData = [
                'text' => $text,
                'speed' => max(0.5, min($speed, 2.0)),
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/speed-control", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $speedControl = $responseData['speed_control'] ?? [];

            return [
                'success' => true,
                'speed_control' => [
                    'text' => $text,
                    'speed' => $speed,
                    'speed_controlled_audio' => [
                        'audio_url' => $speedControl['speed_controlled_audio']['audio_url'] ?? '',
                        'format' => $speedControl['speed_controlled_audio']['format'] ?? 'mp3',
                        'duration' => $speedControl['speed_controlled_audio']['duration'] ?? 0.0,
                        'original_duration' => $speedControl['speed_controlled_audio']['original_duration'] ?? 0.0,
                        'speed_factor' => $speedControl['speed_controlled_audio']['speed_factor'] ?? 1.0,
                    ],
                    'timing_analysis' => [
                        'word_timings' => array_map(fn ($timing) => [
                            'word' => $timing['word'] ?? '',
                            'start_time' => $timing['start_time'] ?? 0.0,
                            'end_time' => $timing['end_time'] ?? 0.0,
                            'duration' => $timing['duration'] ?? 0.0,
                        ], $speedControl['timing_analysis']['word_timings'] ?? []),
                        'pause_durations' => $speedControl['timing_analysis']['pause_durations'] ?? [],
                        'rhythm_pattern' => $speedControl['timing_analysis']['rhythm_pattern'] ?? [],
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'speed_control' => [
                    'text' => $text,
                    'speed' => $speed,
                    'speed_controlled_audio' => [
                        'audio_url' => '',
                        'format' => 'mp3',
                        'duration' => 0.0,
                        'original_duration' => 0.0,
                        'speed_factor' => 1.0,
                    ],
                    'timing_analysis' => [
                        'word_timings' => [],
                        'pause_durations' => [],
                        'rhythm_pattern' => [],
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Control pitch.
     *
     * @param string               $text    Text to synthesize
     * @param float                $pitch   Pitch adjustment (-12 to +12 semitones)
     * @param array<string, mixed> $options Pitch control options
     *
     * @return array{
     *     success: bool,
     *     pitch_control: array{
     *         text: string,
     *         pitch: float,
     *         pitch_controlled_audio: array{
     *             audio_url: string,
     *             format: string,
     *             duration: float,
     *             original_pitch: float,
     *             new_pitch: float,
     *         },
     *         pitch_analysis: array{
     *             fundamental_frequency: array<float>,
     *             pitch_contour: array<int, array{
     *                 timestamp: float,
     *                 frequency: float,
     *                 note: string,
     *             }>,
     *             pitch_variability: float,
     *             average_pitch: float,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function pitchControl(
        string $text,
        float $pitch = 0.0,
        array $options = [],
    ): array {
        try {
            $requestData = [
                'text' => $text,
                'pitch' => max(-12.0, min($pitch, 12.0)),
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/pitch-control", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $pitchControl = $responseData['pitch_control'] ?? [];

            return [
                'success' => true,
                'pitch_control' => [
                    'text' => $text,
                    'pitch' => $pitch,
                    'pitch_controlled_audio' => [
                        'audio_url' => $pitchControl['pitch_controlled_audio']['audio_url'] ?? '',
                        'format' => $pitchControl['pitch_controlled_audio']['format'] ?? 'mp3',
                        'duration' => $pitchControl['pitch_controlled_audio']['duration'] ?? 0.0,
                        'original_pitch' => $pitchControl['pitch_controlled_audio']['original_pitch'] ?? 0.0,
                        'new_pitch' => $pitchControl['pitch_controlled_audio']['new_pitch'] ?? 0.0,
                    ],
                    'pitch_analysis' => [
                        'fundamental_frequency' => $pitchControl['pitch_analysis']['fundamental_frequency'] ?? [],
                        'pitch_contour' => array_map(fn ($contour) => [
                            'timestamp' => $contour['timestamp'] ?? 0.0,
                            'frequency' => $contour['frequency'] ?? 0.0,
                            'note' => $contour['note'] ?? '',
                        ], $pitchControl['pitch_analysis']['pitch_contour'] ?? []),
                        'pitch_variability' => $pitchControl['pitch_analysis']['pitch_variability'] ?? 0.0,
                        'average_pitch' => $pitchControl['pitch_analysis']['average_pitch'] ?? 0.0,
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'pitch_control' => [
                    'text' => $text,
                    'pitch' => $pitch,
                    'pitch_controlled_audio' => [
                        'audio_url' => '',
                        'format' => 'mp3',
                        'duration' => 0.0,
                        'original_pitch' => 0.0,
                        'new_pitch' => 0.0,
                    ],
                    'pitch_analysis' => [
                        'fundamental_frequency' => [],
                        'pitch_contour' => [],
                        'pitch_variability' => 0.0,
                        'average_pitch' => 0.0,
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process batch TTS.
     *
     * @param array<int, string>   $texts   Array of texts to convert
     * @param array<string, mixed> $options Batch processing options
     *
     * @return array{
     *     success: bool,
     *     batch_tts: array{
     *         texts: array<int, string>,
     *         batch_results: array<int, array{
     *             text: string,
     *             audio_url: string,
     *             duration: float,
     *             success: bool,
     *             error: string,
     *         }>,
     *         batch_summary: array{
     *             total_texts: int,
     *             successful: int,
     *             failed: int,
     *             total_duration: float,
     *             processing_time: float,
     *         },
     *         batch_audio_url: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function batchTts(
        array $texts,
        array $options = [],
    ): array {
        try {
            $requestData = [
                'texts' => $texts,
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/batch-tts", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $batchTts = $responseData['batch_tts'] ?? [];

            return [
                'success' => true,
                'batch_tts' => [
                    'texts' => $texts,
                    'batch_results' => array_map(fn ($result) => [
                        'text' => $result['text'] ?? '',
                        'audio_url' => $result['audio_url'] ?? '',
                        'duration' => $result['duration'] ?? 0.0,
                        'success' => $result['success'] ?? false,
                        'error' => $result['error'] ?? '',
                    ], $batchTts['batch_results'] ?? []),
                    'batch_summary' => [
                        'total_texts' => $batchTts['batch_summary']['total_texts'] ?? \count($texts),
                        'successful' => $batchTts['batch_summary']['successful'] ?? 0,
                        'failed' => $batchTts['batch_summary']['failed'] ?? 0,
                        'total_duration' => $batchTts['batch_summary']['total_duration'] ?? 0.0,
                        'processing_time' => $batchTts['batch_summary']['processing_time'] ?? 0.0,
                    ],
                    'batch_audio_url' => $batchTts['batch_audio_url'] ?? '',
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'batch_tts' => [
                    'texts' => $texts,
                    'batch_results' => [],
                    'batch_summary' => [
                        'total_texts' => \count($texts),
                        'successful' => 0,
                        'failed' => \count($texts),
                        'total_duration' => 0.0,
                        'processing_time' => 0.0,
                    ],
                    'batch_audio_url' => '',
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze voice characteristics.
     *
     * @param string               $audioUrl        Audio file URL to analyze
     * @param array<string, mixed> $analysisOptions Analysis options
     *
     * @return array{
     *     success: bool,
     *     voice_analysis: array{
     *         audio_url: string,
     *         voice_characteristics: array{
     *             fundamental_frequency: float,
     *             formants: array<float>,
     *             jitter: float,
     *             shimmer: float,
     *             hnr: float,
     *             mfcc: array<float>,
     *         },
     *         prosody_analysis: array{
     *             speaking_rate: float,
     *             pause_frequency: float,
     *             pitch_range: float,
     *             intensity_variation: float,
     *             rhythm_pattern: array<string, mixed>,
     *         },
     *         emotion_analysis: array{
     *             detected_emotions: array<string, float>,
     *             emotion_intensity: float,
     *             valence: float,
     *             arousal: float,
     *         },
     *         quality_metrics: array{
     *             clarity_score: float,
     *             naturalness_score: float,
     *             intelligibility_score: float,
     *             noise_level: float,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function voiceAnalysis(
        string $audioUrl,
        array $analysisOptions = [],
    ): array {
        try {
            $requestData = [
                'audio_url' => $audioUrl,
                'analysis_options' => $analysisOptions,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/voice-analysis", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $voiceAnalysis = $responseData['voice_analysis'] ?? [];

            return [
                'success' => true,
                'voice_analysis' => [
                    'audio_url' => $audioUrl,
                    'voice_characteristics' => [
                        'fundamental_frequency' => $voiceAnalysis['voice_characteristics']['fundamental_frequency'] ?? 0.0,
                        'formants' => $voiceAnalysis['voice_characteristics']['formants'] ?? [],
                        'jitter' => $voiceAnalysis['voice_characteristics']['jitter'] ?? 0.0,
                        'shimmer' => $voiceAnalysis['voice_characteristics']['shimmer'] ?? 0.0,
                        'hnr' => $voiceAnalysis['voice_characteristics']['hnr'] ?? 0.0,
                        'mfcc' => $voiceAnalysis['voice_characteristics']['mfcc'] ?? [],
                    ],
                    'prosody_analysis' => [
                        'speaking_rate' => $voiceAnalysis['prosody_analysis']['speaking_rate'] ?? 0.0,
                        'pause_frequency' => $voiceAnalysis['prosody_analysis']['pause_frequency'] ?? 0.0,
                        'pitch_range' => $voiceAnalysis['prosody_analysis']['pitch_range'] ?? 0.0,
                        'intensity_variation' => $voiceAnalysis['prosody_analysis']['intensity_variation'] ?? 0.0,
                        'rhythm_pattern' => $voiceAnalysis['prosody_analysis']['rhythm_pattern'] ?? [],
                    ],
                    'emotion_analysis' => [
                        'detected_emotions' => $voiceAnalysis['emotion_analysis']['detected_emotions'] ?? [],
                        'emotion_intensity' => $voiceAnalysis['emotion_analysis']['emotion_intensity'] ?? 0.0,
                        'valence' => $voiceAnalysis['emotion_analysis']['valence'] ?? 0.0,
                        'arousal' => $voiceAnalysis['emotion_analysis']['arousal'] ?? 0.0,
                    ],
                    'quality_metrics' => [
                        'clarity_score' => $voiceAnalysis['quality_metrics']['clarity_score'] ?? 0.0,
                        'naturalness_score' => $voiceAnalysis['quality_metrics']['naturalness_score'] ?? 0.0,
                        'intelligibility_score' => $voiceAnalysis['quality_metrics']['intelligibility_score'] ?? 0.0,
                        'noise_level' => $voiceAnalysis['quality_metrics']['noise_level'] ?? 0.0,
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'voice_analysis' => [
                    'audio_url' => $audioUrl,
                    'voice_characteristics' => [
                        'fundamental_frequency' => 0.0,
                        'formants' => [],
                        'jitter' => 0.0,
                        'shimmer' => 0.0,
                        'hnr' => 0.0,
                        'mfcc' => [],
                    ],
                    'prosody_analysis' => [
                        'speaking_rate' => 0.0,
                        'pause_frequency' => 0.0,
                        'pitch_range' => 0.0,
                        'intensity_variation' => 0.0,
                        'rhythm_pattern' => [],
                    ],
                    'emotion_analysis' => [
                        'detected_emotions' => [],
                        'emotion_intensity' => 0.0,
                        'valence' => 0.0,
                        'arousal' => 0.0,
                    ],
                    'quality_metrics' => [
                        'clarity_score' => 0.0,
                        'naturalness_score' => 0.0,
                        'intelligibility_score' => 0.0,
                        'noise_level' => 0.0,
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get model endpoint based on voice and language.
     */
    private function getModelEndpoint(string $voice, string $language): string
    {
        $modelMap = [
            'default' => [
                'en' => 'facebook/tts-models',
                'es' => 'facebook/tts-models-es',
                'fr' => 'facebook/tts-models-fr',
                'de' => 'facebook/tts-models-de',
            ],
            'female' => [
                'en' => 'facebook/tts-female-en',
                'es' => 'facebook/tts-female-es',
                'fr' => 'facebook/tts-female-fr',
            ],
            'male' => [
                'en' => 'facebook/tts-male-en',
                'es' => 'facebook/tts-male-es',
                'fr' => 'facebook/tts-male-fr',
            ],
        ];

        return $modelMap[$voice][$language] ?? $modelMap['default']['en'];
    }
}

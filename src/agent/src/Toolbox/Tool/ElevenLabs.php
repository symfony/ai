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
#[AsTool('eleven_labs_text2speech', 'Tool that converts text to speech using ElevenLabs API')]
#[AsTool('eleven_labs_voices', 'Tool that gets available voices from ElevenLabs', method: 'getVoices')]
#[AsTool('eleven_labs_stream', 'Tool that streams text to speech', method: 'streamSpeech')]
final readonly class ElevenLabs
{
    public const MODEL_MULTI_LINGUAL = 'eleven_multilingual_v2';
    public const MODEL_MULTI_LINGUAL_FLASH = 'eleven_flash_v2_5';
    public const MODEL_MONO_LINGUAL = 'eleven_flash_v2';

    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private string $model = self::MODEL_MULTI_LINGUAL,
        private string $voiceId = 'JBFqnCBsd6RMkjVDRZzb', // Default voice ID
        private string $outputFormat = 'mp3_44100_128',
        private array $options = [],
    ) {
    }

    /**
     * Convert text to speech using ElevenLabs API.
     *
     * @param string $text      Text to convert to speech
     * @param string $voiceId   Voice ID to use (optional, uses default if not provided)
     * @param string $model     Model to use (optional, uses default if not provided)
     * @param string $outputDir Directory to save the audio file
     *
     * @return array{
     *     file_path: string,
     *     file_size: int,
     *     duration: float,
     *     voice_used: string,
     *     model_used: string,
     * }|string
     */
    public function __invoke(
        #[With(maximum: 5000)]
        string $text,
        string $voiceId = '',
        string $model = '',
        string $outputDir = '/tmp',
    ): array|string {
        try {
            $voiceId = $voiceId ?: $this->voiceId;
            $model = $model ?: $this->model;

            $response = $this->httpClient->request('POST', 'https://api.elevenlabs.io/v1/text-to-speech/'.$voiceId, [
                'headers' => [
                    'Accept' => 'audio/mpeg',
                    'Content-Type' => 'application/json',
                    'xi-api-key' => $this->apiKey,
                ],
                'json' => [
                    'text' => $text,
                    'model_id' => $model,
                    'voice_settings' => [
                        'stability' => 0.5,
                        'similarity_boost' => 0.5,
                    ],
                ],
                'buffer' => false,
            ]);

            if (200 !== $response->getStatusCode()) {
                return 'Error: Failed to generate speech - '.$response->getContent(false);
            }

            // Save the audio file
            $filename = 'elevenlabs_'.uniqid().'.mp3';
            $filePath = rtrim($outputDir, '/').'/'.$filename;

            file_put_contents($filePath, $response->getContent());

            $fileSize = filesize($filePath);

            return [
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'duration' => $this->estimateDuration($text),
                'voice_used' => $voiceId,
                'model_used' => $model,
            ];
        } catch (\Exception $e) {
            return 'Error converting text to speech: '.$e->getMessage();
        }
    }

    /**
     * Get available voices from ElevenLabs.
     *
     * @return array<int, array{
     *     voice_id: string,
     *     name: string,
     *     category: string,
     *     description: string,
     *     labels: array<string, string>,
     *     preview_url: string,
     *     available_for_tiers: array<int, string>,
     *     settings: array<string, mixed>,
     * }>
     */
    public function getVoices(): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.elevenlabs.io/v1/voices', [
                'headers' => [
                    'Accept' => 'application/json',
                    'xi-api-key' => $this->apiKey,
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['voices'])) {
                return [];
            }

            $results = [];
            foreach ($data['voices'] as $voice) {
                $results[] = [
                    'voice_id' => $voice['voice_id'],
                    'name' => $voice['name'],
                    'category' => $voice['category'],
                    'description' => $voice['description'] ?? '',
                    'labels' => $voice['labels'] ?? [],
                    'preview_url' => $voice['preview_url'] ?? '',
                    'available_for_tiers' => $voice['available_for_tiers'] ?? [],
                    'settings' => $voice['settings'] ?? [],
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [
                [
                    'voice_id' => 'error',
                    'name' => 'Error',
                    'category' => '',
                    'description' => 'Unable to get voices: '.$e->getMessage(),
                    'labels' => [],
                    'preview_url' => '',
                    'available_for_tiers' => [],
                    'settings' => [],
                ],
            ];
        }
    }

    /**
     * Stream text to speech (returns streaming response).
     *
     * @param string $text    Text to convert to speech
     * @param string $voiceId Voice ID to use (optional)
     * @param string $model   Model to use (optional)
     */
    public function streamSpeech(
        #[With(maximum: 5000)]
        string $text,
        string $voiceId = '',
        string $model = '',
    ): string {
        try {
            $voiceId = $voiceId ?: $this->voiceId;
            $model = $model ?: $this->model;

            $response = $this->httpClient->request('POST', 'https://api.elevenlabs.io/v1/text-to-speech/'.$voiceId.'/stream', [
                'headers' => [
                    'Accept' => 'audio/mpeg',
                    'Content-Type' => 'application/json',
                    'xi-api-key' => $this->apiKey,
                ],
                'json' => [
                    'text' => $text,
                    'model_id' => $model,
                    'voice_settings' => [
                        'stability' => 0.5,
                        'similarity_boost' => 0.5,
                    ],
                ],
            ]);

            if (200 !== $response->getStatusCode()) {
                return 'Error: Failed to stream speech - '.$response->getContent(false);
            }

            return 'Speech streaming started successfully. Audio data available in response.';
        } catch (\Exception $e) {
            return 'Error streaming speech: '.$e->getMessage();
        }
    }

    /**
     * Get voice settings for a specific voice.
     *
     * @param string $voiceId Voice ID
     *
     * @return array{
     *     stability: float,
     *     similarity_boost: float,
     *     style: float,
     *     use_speaker_boost: bool,
     * }|string
     */
    public function getVoiceSettings(string $voiceId): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.elevenlabs.io/v1/voices/{$voiceId}/settings", [
                'headers' => [
                    'Accept' => 'application/json',
                    'xi-api-key' => $this->apiKey,
                ],
            ]);

            $data = $response->toArray();

            return [
                'stability' => $data['stability'],
                'similarity_boost' => $data['similarity_boost'],
                'style' => $data['style'] ?? 0.0,
                'use_speaker_boost' => $data['use_speaker_boost'] ?? true,
            ];
        } catch (\Exception $e) {
            return 'Error getting voice settings: '.$e->getMessage();
        }
    }

    /**
     * Update voice settings for a specific voice.
     *
     * @param string $voiceId      Voice ID
     * @param float  $stability    Stability setting (0.0 to 1.0)
     * @param float  $similarity   Similarity boost setting (0.0 to 1.0)
     * @param float  $style        Style setting (0.0 to 1.0)
     * @param bool   $speakerBoost Use speaker boost
     */
    public function updateVoiceSettings(
        string $voiceId,
        float $stability = 0.5,
        float $similarity = 0.5,
        float $style = 0.0,
        bool $speakerBoost = true,
    ): string {
        try {
            $response = $this->httpClient->request('POST', "https://api.elevenlabs.io/v1/voices/{$voiceId}/settings", [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'xi-api-key' => $this->apiKey,
                ],
                'json' => [
                    'stability' => $stability,
                    'similarity_boost' => $similarity,
                    'style' => $style,
                    'use_speaker_boost' => $speakerBoost,
                ],
            ]);

            if (200 === $response->getStatusCode()) {
                return "Voice settings updated successfully for voice {$voiceId}";
            } else {
                return 'Failed to update voice settings';
            }
        } catch (\Exception $e) {
            return 'Error updating voice settings: '.$e->getMessage();
        }
    }

    /**
     * Estimate speech duration based on text length.
     */
    private function estimateDuration(string $text): float
    {
        // Rough estimation: average reading speed is about 150-200 words per minute
        $wordCount = str_word_count($text);
        $minutes = $wordCount / 175; // Using 175 WPM as average

        return round($minutes * 60, 2); // Convert to seconds
    }
}

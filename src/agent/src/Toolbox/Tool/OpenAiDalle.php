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
#[AsTool('openai_dalle_generate_image', 'Tool that generates images using OpenAI DALL-E')]
#[AsTool('openai_dalle_create_variation', 'Tool that creates image variations using DALL-E', method: 'createVariation')]
#[AsTool('openai_dalle_edit_image', 'Tool that edits images using DALL-E', method: 'editImage')]
#[AsTool('openai_dalle_download_image', 'Tool that downloads generated images', method: 'downloadImage')]
final readonly class OpenAiDalle
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.openai.com/v1',
        private array $options = [],
    ) {
    }

    /**
     * Generate image using OpenAI DALL-E.
     *
     * @param string $prompt         Image generation prompt
     * @param int    $n              Number of images to generate (1-10)
     * @param string $size           Image size (256x256, 512x512, 1024x1024, 1792x1024, 1024x1792)
     * @param string $quality        Image quality (standard, hd)
     * @param string $style          Image style (vivid, natural)
     * @param string $responseFormat Response format (url, b64_json)
     *
     * @return array{
     *     success: bool,
     *     images: array<int, array{
     *         url: string,
     *         revisedPrompt: string,
     *         b64Json: string,
     *     }>,
     *     created: int,
     *     usage: array{
     *         promptTokens: int,
     *         completionTokens: int,
     *         totalTokens: int,
     *     },
     *     error: string,
     * }
     */
    public function __invoke(
        string $prompt,
        int $n = 1,
        string $size = '1024x1024',
        string $quality = 'standard',
        string $style = 'vivid',
        string $responseFormat = 'url',
    ): array {
        try {
            $requestData = [
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => max(1, min($n, 10)),
                'size' => $size,
                'quality' => $quality,
                'style' => $style,
                'response_format' => $responseFormat,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/images/generations", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'images' => array_map(fn ($image) => [
                    'url' => $image['url'] ?? '',
                    'revisedPrompt' => $image['revised_prompt'] ?? $prompt,
                    'b64Json' => $image['b64_json'] ?? '',
                ], $data['data'] ?? []),
                'created' => $data['created'] ?? time(),
                'usage' => [
                    'promptTokens' => $data['usage']['prompt_tokens'] ?? 0,
                    'completionTokens' => $data['usage']['completion_tokens'] ?? 0,
                    'totalTokens' => $data['usage']['total_tokens'] ?? 0,
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'images' => [],
                'created' => 0,
                'usage' => ['promptTokens' => 0, 'completionTokens' => 0, 'totalTokens' => 0],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create image variation using DALL-E.
     *
     * @param string $imagePath      Path to the source image
     * @param int    $n              Number of variations to generate (1-10)
     * @param string $size           Image size (256x256, 512x512, 1024x1024, 1792x1024, 1024x1792)
     * @param string $responseFormat Response format (url, b64_json)
     *
     * @return array{
     *     success: bool,
     *     images: array<int, array{
     *         url: string,
     *         revisedPrompt: string,
     *         b64Json: string,
     *     }>,
     *     created: int,
     *     usage: array{
     *         promptTokens: int,
     *         completionTokens: int,
     *         totalTokens: int,
     *     },
     *     error: string,
     * }
     */
    public function createVariation(
        string $imagePath,
        int $n = 1,
        string $size = '1024x1024',
        string $responseFormat = 'url',
    ): array {
        try {
            if (!file_exists($imagePath)) {
                throw new \InvalidArgumentException("Image file not found: {$imagePath}.");
            }

            $imageData = base64_encode(file_get_contents($imagePath));

            $requestData = [
                'model' => 'dall-e-2',
                'image' => "data:image/jpeg;base64,{$imageData}",
                'n' => max(1, min($n, 10)),
                'size' => $size,
                'response_format' => $responseFormat,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/images/variations", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'images' => array_map(fn ($image) => [
                    'url' => $image['url'] ?? '',
                    'revisedPrompt' => $image['revised_prompt'] ?? '',
                    'b64Json' => $image['b64_json'] ?? '',
                ], $data['data'] ?? []),
                'created' => $data['created'] ?? time(),
                'usage' => [
                    'promptTokens' => $data['usage']['prompt_tokens'] ?? 0,
                    'completionTokens' => $data['usage']['completion_tokens'] ?? 0,
                    'totalTokens' => $data['usage']['total_tokens'] ?? 0,
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'images' => [],
                'created' => 0,
                'usage' => ['promptTokens' => 0, 'completionTokens' => 0, 'totalTokens' => 0],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Edit image using DALL-E.
     *
     * @param string $imagePath      Path to the source image
     * @param string $maskPath       Path to the mask image (optional)
     * @param string $prompt         Edit instruction prompt
     * @param int    $n              Number of edited images to generate (1-10)
     * @param string $size           Image size (256x256, 512x512, 1024x1024, 1792x1024, 1024x1792)
     * @param string $responseFormat Response format (url, b64_json)
     *
     * @return array{
     *     success: bool,
     *     images: array<int, array{
     *         url: string,
     *         revisedPrompt: string,
     *         b64Json: string,
     *     }>,
     *     created: int,
     *     usage: array{
     *         promptTokens: int,
     *         completionTokens: int,
     *         totalTokens: int,
     *     },
     *     error: string,
     * }
     */
    public function editImage(
        string $imagePath,
        string $maskPath = '',
        string $prompt = '',
        int $n = 1,
        string $size = '1024x1024',
        string $responseFormat = 'url',
    ): array {
        try {
            if (!file_exists($imagePath)) {
                throw new \InvalidArgumentException("Image file not found: {$imagePath}.");
            }

            $imageData = base64_encode(file_get_contents($imagePath));

            $requestData = [
                'model' => 'dall-e-2',
                'image' => "data:image/jpeg;base64,{$imageData}",
                'prompt' => $prompt,
                'n' => max(1, min($n, 10)),
                'size' => $size,
                'response_format' => $responseFormat,
            ];

            if ($maskPath && file_exists($maskPath)) {
                $maskData = base64_encode(file_get_contents($maskPath));
                $requestData['mask'] = "data:image/jpeg;base64,{$maskData}";
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/images/edits", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'images' => array_map(fn ($image) => [
                    'url' => $image['url'] ?? '',
                    'revisedPrompt' => $image['revised_prompt'] ?? $prompt,
                    'b64Json' => $image['b64_json'] ?? '',
                ], $data['data'] ?? []),
                'created' => $data['created'] ?? time(),
                'usage' => [
                    'promptTokens' => $data['usage']['prompt_tokens'] ?? 0,
                    'completionTokens' => $data['usage']['completion_tokens'] ?? 0,
                    'totalTokens' => $data['usage']['total_tokens'] ?? 0,
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'images' => [],
                'created' => 0,
                'usage' => ['promptTokens' => 0, 'completionTokens' => 0, 'totalTokens' => 0],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Download generated image.
     *
     * @param string $imageUrl   URL of the generated image
     * @param string $outputPath Path to save the downloaded image
     *
     * @return array{
     *     success: bool,
     *     filePath: string,
     *     fileSize: int,
     *     mimeType: string,
     *     error: string,
     * }
     */
    public function downloadImage(
        string $imageUrl,
        string $outputPath,
    ): array {
        try {
            $response = $this->httpClient->request('GET', $imageUrl, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ],
            ]);

            $imageData = $response->getContent();
            $headers = $response->getHeaders(false);

            // Create directory if it doesn't exist
            $outputDir = \dirname($outputPath);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Save image to file
            file_put_contents($outputPath, $imageData);

            // Determine MIME type
            $mimeType = 'application/octet-stream';
            if (isset($headers['content-type'][0])) {
                $mimeType = $headers['content-type'][0];
            }

            return [
                'success' => true,
                'filePath' => $outputPath,
                'fileSize' => \strlen($imageData),
                'mimeType' => $mimeType,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'filePath' => '',
                'fileSize' => 0,
                'mimeType' => '',
                'error' => $e->getMessage(),
            ];
        }
    }
}

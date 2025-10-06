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
#[AsTool('steamship_image_generate', 'Tool that generates images using Steamship Image Generation')]
#[AsTool('steamship_image_upscale', 'Tool that upscales images', method: 'upscaleImage')]
#[AsTool('steamship_image_edit', 'Tool that edits images', method: 'editImage')]
#[AsTool('steamship_image_variate', 'Tool that creates image variations', method: 'variateImage')]
#[AsTool('steamship_image_remove_background', 'Tool that removes image backgrounds', method: 'removeBackground')]
#[AsTool('steamship_image_style_transfer', 'Tool that applies style transfer', method: 'styleTransfer')]
#[AsTool('steamship_image_inpaint', 'Tool that performs image inpainting', method: 'inpaintImage')]
#[AsTool('steamship_image_outpaint', 'Tool that performs image outpainting', method: 'outpaintImage')]
final readonly class SteamshipImage
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $workspaceId,
        private string $baseUrl = 'https://api.steamship.com',
        private array $options = [],
    ) {
    }

    /**
     * Generate images using Steamship Image Generation.
     *
     * @param string               $prompt     Text prompt for image generation
     * @param int                  $width      Image width
     * @param int                  $height     Image height
     * @param string               $model      Model to use
     * @param array<string, mixed> $parameters Generation parameters
     * @param array<string, mixed> $options    Generation options
     *
     * @return array{
     *     success: bool,
     *     image_generation: array{
     *         prompt: string,
     *         width: int,
     *         height: int,
     *         model: string,
     *         parameters: array<string, mixed>,
     *         generated_images: array<int, array{
     *             image_url: string,
     *             image_id: string,
     *             seed: int,
     *             steps: int,
     *             guidance_scale: float,
     *             negative_prompt: string,
     *             generation_time: float,
     *         }>,
     *         generation_settings: array{
     *             batch_size: int,
     *             quality: string,
     *             style: string,
     *             artist: string,
     *         },
     *         usage_stats: array{
     *             tokens_used: int,
     *             credits_consumed: float,
     *             estimated_cost: float,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $prompt,
        int $width = 512,
        int $height = 512,
        string $model = 'stable-diffusion-xl',
        array $parameters = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'prompt' => $prompt,
                'width' => $width,
                'height' => $height,
                'model' => $model,
                'parameters' => array_merge([
                    'num_inference_steps' => $parameters['steps'] ?? 20,
                    'guidance_scale' => $parameters['guidance_scale'] ?? 7.5,
                    'seed' => $parameters['seed'] ?? random_int(0, 2147483647),
                    'negative_prompt' => $parameters['negative_prompt'] ?? '',
                    'scheduler' => $parameters['scheduler'] ?? 'DPMSolverMultistepScheduler',
                ], $parameters),
                'options' => array_merge([
                    'batch_size' => $options['batch_size'] ?? 1,
                    'quality' => $options['quality'] ?? 'standard',
                    'style' => $options['style'] ?? 'realistic',
                    'artist' => $options['artist'] ?? '',
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/workspace/{$this->workspaceId}/image/generate", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $images = $responseData['images'] ?? [];

            return [
                'success' => !empty($images),
                'image_generation' => [
                    'prompt' => $prompt,
                    'width' => $width,
                    'height' => $height,
                    'model' => $model,
                    'parameters' => $parameters,
                    'generated_images' => array_map(fn ($image, $index) => [
                        'image_url' => $image['url'] ?? '',
                        'image_id' => $image['id'] ?? "generated_{$index}",
                        'seed' => $image['seed'] ?? $parameters['seed'],
                        'steps' => $image['steps'] ?? $parameters['steps'] ?? 20,
                        'guidance_scale' => $image['guidance_scale'] ?? $parameters['guidance_scale'] ?? 7.5,
                        'negative_prompt' => $image['negative_prompt'] ?? $parameters['negative_prompt'] ?? '',
                        'generation_time' => $image['generation_time'] ?? 0.0,
                    ], $images, array_keys($images)),
                    'generation_settings' => [
                        'batch_size' => $options['batch_size'] ?? 1,
                        'quality' => $options['quality'] ?? 'standard',
                        'style' => $options['style'] ?? 'realistic',
                        'artist' => $options['artist'] ?? '',
                    ],
                    'usage_stats' => [
                        'tokens_used' => $responseData['usage']['tokens'] ?? 0,
                        'credits_consumed' => $responseData['usage']['credits'] ?? 0.0,
                        'estimated_cost' => $responseData['usage']['cost'] ?? 0.0,
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'image_generation' => [
                    'prompt' => $prompt,
                    'width' => $width,
                    'height' => $height,
                    'model' => $model,
                    'parameters' => $parameters,
                    'generated_images' => [],
                    'generation_settings' => [
                        'batch_size' => 1,
                        'quality' => 'standard',
                        'style' => 'realistic',
                        'artist' => '',
                    ],
                    'usage_stats' => [
                        'tokens_used' => 0,
                        'credits_consumed' => 0.0,
                        'estimated_cost' => 0.0,
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upscale images.
     *
     * @param string               $imageUrl     URL of image to upscale
     * @param int                  $scaleFactor  Scale factor (2x, 4x, 8x)
     * @param string               $upscaleModel Upscaling model to use
     * @param array<string, mixed> $options      Upscaling options
     *
     * @return array{
     *     success: bool,
     *     image_upscaling: array{
     *         image_url: string,
     *         scale_factor: int,
     *         upscale_model: string,
     *         upscaled_image: array{
     *             image_url: string,
     *             original_width: int,
     *             original_height: int,
     *             upscaled_width: int,
     *             upscaled_height: int,
     *             upscaling_time: float,
     *             quality_improvement: float,
     *         },
     *         processing_options: array<string, mixed>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function upscaleImage(
        string $imageUrl,
        int $scaleFactor = 4,
        string $upscaleModel = 'real-esrgan',
        array $options = [],
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'scale_factor' => $scaleFactor,
                'upscale_model' => $upscaleModel,
                'options' => array_merge([
                    'preserve_details' => $options['preserve_details'] ?? true,
                    'enhance_faces' => $options['enhance_faces'] ?? true,
                    'remove_noise' => $options['remove_noise'] ?? true,
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/workspace/{$this->workspaceId}/image/upscale", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $upscaledImage = $responseData['upscaled_image'] ?? [];

            return [
                'success' => !empty($upscaledImage['url']),
                'image_upscaling' => [
                    'image_url' => $imageUrl,
                    'scale_factor' => $scaleFactor,
                    'upscale_model' => $upscaleModel,
                    'upscaled_image' => [
                        'image_url' => $upscaledImage['url'] ?? '',
                        'original_width' => $upscaledImage['original_width'] ?? 0,
                        'original_height' => $upscaledImage['original_height'] ?? 0,
                        'upscaled_width' => $upscaledImage['upscaled_width'] ?? 0,
                        'upscaled_height' => $upscaledImage['upscaled_height'] ?? 0,
                        'upscaling_time' => $upscaledImage['processing_time'] ?? 0.0,
                        'quality_improvement' => $upscaledImage['quality_improvement'] ?? 0.0,
                    ],
                    'processing_options' => $options,
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'image_upscaling' => [
                    'image_url' => $imageUrl,
                    'scale_factor' => $scaleFactor,
                    'upscale_model' => $upscaleModel,
                    'upscaled_image' => [
                        'image_url' => '',
                        'original_width' => 0,
                        'original_height' => 0,
                        'upscaled_width' => 0,
                        'upscaled_height' => 0,
                        'upscaling_time' => 0.0,
                        'quality_improvement' => 0.0,
                    ],
                    'processing_options' => $options,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Edit images.
     *
     * @param string               $imageUrl   URL of image to edit
     * @param string               $editPrompt Text prompt describing the edit
     * @param string               $editType   Type of edit (replace, add, modify)
     * @param array<string, mixed> $editParams Edit parameters
     * @param array<string, mixed> $options    Edit options
     *
     * @return array{
     *     success: bool,
     *     image_editing: array{
     *         image_url: string,
     *         edit_prompt: string,
     *         edit_type: string,
     *         edit_params: array<string, mixed>,
     *         edited_image: array{
     *             image_url: string,
     *             edit_mask: string,
     *             confidence_score: float,
     *             edit_region: array{
     *                 x: int,
     *                 y: int,
     *                 width: int,
     *                 height: int,
     *             },
     *             before_after_comparison: array{
     *                 similarity_score: float,
     *                 changes_detected: array<int, string>,
     *             },
     *         },
     *         editing_options: array<string, mixed>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function editImage(
        string $imageUrl,
        string $editPrompt,
        string $editType = 'modify',
        array $editParams = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'edit_prompt' => $editPrompt,
                'edit_type' => $editType,
                'edit_params' => array_merge([
                    'strength' => $editParams['strength'] ?? 0.8,
                    'guidance_scale' => $editParams['guidance_scale'] ?? 7.5,
                    'num_inference_steps' => $editParams['steps'] ?? 20,
                    'seed' => $editParams['seed'] ?? random_int(0, 2147483647),
                ], $editParams),
                'options' => array_merge([
                    'preserve_original' => $options['preserve_original'] ?? true,
                    'auto_mask' => $options['auto_mask'] ?? true,
                    'blend_edges' => $options['blend_edges'] ?? true,
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/workspace/{$this->workspaceId}/image/edit", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $editedImage = $responseData['edited_image'] ?? [];

            return [
                'success' => !empty($editedImage['url']),
                'image_editing' => [
                    'image_url' => $imageUrl,
                    'edit_prompt' => $editPrompt,
                    'edit_type' => $editType,
                    'edit_params' => $editParams,
                    'edited_image' => [
                        'image_url' => $editedImage['url'] ?? '',
                        'edit_mask' => $editedImage['mask_url'] ?? '',
                        'confidence_score' => $editedImage['confidence'] ?? 0.0,
                        'edit_region' => [
                            'x' => $editedImage['edit_region']['x'] ?? 0,
                            'y' => $editedImage['edit_region']['y'] ?? 0,
                            'width' => $editedImage['edit_region']['width'] ?? 0,
                            'height' => $editedImage['edit_region']['height'] ?? 0,
                        ],
                        'before_after_comparison' => [
                            'similarity_score' => $editedImage['similarity_score'] ?? 0.0,
                            'changes_detected' => $editedImage['changes'] ?? [],
                        ],
                    ],
                    'editing_options' => $options,
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'image_editing' => [
                    'image_url' => $imageUrl,
                    'edit_prompt' => $editPrompt,
                    'edit_type' => $editType,
                    'edit_params' => $editParams,
                    'edited_image' => [
                        'image_url' => '',
                        'edit_mask' => '',
                        'confidence_score' => 0.0,
                        'edit_region' => [
                            'x' => 0,
                            'y' => 0,
                            'width' => 0,
                            'height' => 0,
                        ],
                        'before_after_comparison' => [
                            'similarity_score' => 0.0,
                            'changes_detected' => [],
                        ],
                    ],
                    'editing_options' => $options,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create image variations.
     *
     * @param string               $imageUrl          URL of image to vary
     * @param int                  $numVariations     Number of variations to create
     * @param float                $variationStrength Strength of variation
     * @param array<string, mixed> $options           Variation options
     *
     * @return array{
     *     success: bool,
     *     image_variations: array{
     *         image_url: string,
     *         num_variations: int,
     *         variation_strength: float,
     *         variations: array<int, array{
     *             image_url: string,
     *             variation_id: string,
     *             similarity_score: float,
     *             variation_type: string,
     *             seed: int,
     *         }>,
     *         variation_options: array<string, mixed>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function variateImage(
        string $imageUrl,
        int $numVariations = 4,
        float $variationStrength = 0.7,
        array $options = [],
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'num_variations' => min($numVariations, 8),
                'variation_strength' => $variationStrength,
                'options' => array_merge([
                    'preserve_style' => $options['preserve_style'] ?? true,
                    'preserve_composition' => $options['preserve_composition'] ?? true,
                    'random_seed' => $options['random_seed'] ?? true,
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/workspace/{$this->workspaceId}/image/variate", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $variations = $responseData['variations'] ?? [];

            return [
                'success' => !empty($variations),
                'image_variations' => [
                    'image_url' => $imageUrl,
                    'num_variations' => $numVariations,
                    'variation_strength' => $variationStrength,
                    'variations' => array_map(fn ($variation, $index) => [
                        'image_url' => $variation['url'] ?? '',
                        'variation_id' => $variation['id'] ?? "variation_{$index}",
                        'similarity_score' => $variation['similarity'] ?? 0.0,
                        'variation_type' => $variation['type'] ?? 'style',
                        'seed' => $variation['seed'] ?? random_int(0, 2147483647),
                    ], $variations, array_keys($variations)),
                    'variation_options' => $options,
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'image_variations' => [
                    'image_url' => $imageUrl,
                    'num_variations' => $numVariations,
                    'variation_strength' => $variationStrength,
                    'variations' => [],
                    'variation_options' => $options,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove image backgrounds.
     *
     * @param string               $imageUrl         URL of image to process
     * @param string               $backgroundType   Type of background (transparent, white, custom)
     * @param string               $customBackground Custom background color/URL
     * @param array<string, mixed> $options          Background removal options
     *
     * @return array{
     *     success: bool,
     *     background_removal: array{
     *         image_url: string,
     *         background_type: string,
     *         custom_background: string,
     *         processed_image: array{
     *             image_url: string,
     *             mask_url: string,
     *             foreground_url: string,
     *             background_removal_confidence: float,
     *             edge_quality: string,
     *         },
     *         removal_options: array<string, mixed>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function removeBackground(
        string $imageUrl,
        string $backgroundType = 'transparent',
        string $customBackground = '',
        array $options = [],
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'background_type' => $backgroundType,
                'custom_background' => $customBackground,
                'options' => array_merge([
                    'edge_smoothing' => $options['edge_smoothing'] ?? true,
                    'hair_detection' => $options['hair_detection'] ?? true,
                    'fine_details' => $options['fine_details'] ?? true,
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/workspace/{$this->workspaceId}/image/remove-background", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $processedImage = $responseData['processed_image'] ?? [];

            return [
                'success' => !empty($processedImage['url']),
                'background_removal' => [
                    'image_url' => $imageUrl,
                    'background_type' => $backgroundType,
                    'custom_background' => $customBackground,
                    'processed_image' => [
                        'image_url' => $processedImage['url'] ?? '',
                        'mask_url' => $processedImage['mask_url'] ?? '',
                        'foreground_url' => $processedImage['foreground_url'] ?? '',
                        'background_removal_confidence' => $processedImage['confidence'] ?? 0.0,
                        'edge_quality' => $processedImage['edge_quality'] ?? 'high',
                    ],
                    'removal_options' => $options,
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'background_removal' => [
                    'image_url' => $imageUrl,
                    'background_type' => $backgroundType,
                    'custom_background' => $customBackground,
                    'processed_image' => [
                        'image_url' => '',
                        'mask_url' => '',
                        'foreground_url' => '',
                        'background_removal_confidence' => 0.0,
                        'edge_quality' => '',
                    ],
                    'removal_options' => $options,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Apply style transfer.
     *
     * @param string               $contentImage  URL of content image
     * @param string               $styleImage    URL of style image
     * @param float                $styleStrength Strength of style transfer
     * @param array<string, mixed> $options       Style transfer options
     *
     * @return array{
     *     success: bool,
     *     style_transfer: array{
     *         content_image: string,
     *         style_image: string,
     *         style_strength: float,
     *         styled_image: array{
     *             image_url: string,
     *             style_similarity: float,
     *             content_preservation: float,
     *             transfer_quality: string,
     *         },
     *         transfer_options: array<string, mixed>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function styleTransfer(
        string $contentImage,
        string $styleImage,
        float $styleStrength = 0.8,
        array $options = [],
    ): array {
        try {
            $requestData = [
                'content_image' => $contentImage,
                'style_image' => $styleImage,
                'style_strength' => $styleStrength,
                'options' => array_merge([
                    'preserve_content' => $options['preserve_content'] ?? true,
                    'preserve_colors' => $options['preserve_colors'] ?? false,
                    'blend_mode' => $options['blend_mode'] ?? 'normal',
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/workspace/{$this->workspaceId}/image/style-transfer", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $styledImage = $responseData['styled_image'] ?? [];

            return [
                'success' => !empty($styledImage['url']),
                'style_transfer' => [
                    'content_image' => $contentImage,
                    'style_image' => $styleImage,
                    'style_strength' => $styleStrength,
                    'styled_image' => [
                        'image_url' => $styledImage['url'] ?? '',
                        'style_similarity' => $styledImage['style_similarity'] ?? 0.0,
                        'content_preservation' => $styledImage['content_preservation'] ?? 0.0,
                        'transfer_quality' => $styledImage['quality'] ?? 'high',
                    ],
                    'transfer_options' => $options,
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'style_transfer' => [
                    'content_image' => $contentImage,
                    'style_image' => $styleImage,
                    'style_strength' => $styleStrength,
                    'styled_image' => [
                        'image_url' => '',
                        'style_similarity' => 0.0,
                        'content_preservation' => 0.0,
                        'transfer_quality' => '',
                    ],
                    'transfer_options' => $options,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Perform image inpainting.
     *
     * @param string               $imageUrl      URL of image to inpaint
     * @param string               $maskUrl       URL of mask image
     * @param string               $inpaintPrompt Text prompt for inpainting
     * @param array<string, mixed> $options       Inpainting options
     *
     * @return array{
     *     success: bool,
     *     image_inpainting: array{
     *         image_url: string,
     *         mask_url: string,
     *         inpaint_prompt: string,
     *         inpainted_image: array{
     *             image_url: string,
     *             inpaint_confidence: float,
     *             seamless_blend: bool,
     *             inpaint_region: array{
     *                 x: int,
     *                 y: int,
     *                 width: int,
     *                 height: int,
     *             },
     *         },
     *         inpainting_options: array<string, mixed>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function inpaintImage(
        string $imageUrl,
        string $maskUrl,
        string $inpaintPrompt,
        array $options = [],
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'mask_url' => $maskUrl,
                'inpaint_prompt' => $inpaintPrompt,
                'options' => array_merge([
                    'strength' => $options['strength'] ?? 0.8,
                    'guidance_scale' => $options['guidance_scale'] ?? 7.5,
                    'num_inference_steps' => $options['steps'] ?? 20,
                    'seed' => $options['seed'] ?? random_int(0, 2147483647),
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/workspace/{$this->workspaceId}/image/inpaint", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $inpaintedImage = $responseData['inpainted_image'] ?? [];

            return [
                'success' => !empty($inpaintedImage['url']),
                'image_inpainting' => [
                    'image_url' => $imageUrl,
                    'mask_url' => $maskUrl,
                    'inpaint_prompt' => $inpaintPrompt,
                    'inpainted_image' => [
                        'image_url' => $inpaintedImage['url'] ?? '',
                        'inpaint_confidence' => $inpaintedImage['confidence'] ?? 0.0,
                        'seamless_blend' => $inpaintedImage['seamless'] ?? true,
                        'inpaint_region' => [
                            'x' => $inpaintedImage['region']['x'] ?? 0,
                            'y' => $inpaintedImage['region']['y'] ?? 0,
                            'width' => $inpaintedImage['region']['width'] ?? 0,
                            'height' => $inpaintedImage['region']['height'] ?? 0,
                        ],
                    ],
                    'inpainting_options' => $options,
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'image_inpainting' => [
                    'image_url' => $imageUrl,
                    'mask_url' => $maskUrl,
                    'inpaint_prompt' => $inpaintPrompt,
                    'inpainted_image' => [
                        'image_url' => '',
                        'inpaint_confidence' => 0.0,
                        'seamless_blend' => false,
                        'inpaint_region' => [
                            'x' => 0,
                            'y' => 0,
                            'width' => 0,
                            'height' => 0,
                        ],
                    ],
                    'inpainting_options' => $options,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Perform image outpainting.
     *
     * @param string               $imageUrl       URL of image to outpaint
     * @param string               $outpaintPrompt Text prompt for outpainting
     * @param array<string, mixed> $outpaintParams Outpainting parameters
     * @param array<string, mixed> $options        Outpainting options
     *
     * @return array{
     *     success: bool,
     *     image_outpainting: array{
     *         image_url: string,
     *         outpaint_prompt: string,
     *         outpaint_params: array<string, mixed>,
     *         outpainted_image: array{
     *             image_url: string,
     *             original_dimensions: array{
     *                 width: int,
     *                 height: int,
     *             },
     *             outpainted_dimensions: array{
     *                 width: int,
     *                 height: int,
     *             },
     *             expansion_ratio: float,
     *             seamless_continuation: bool,
     *         },
     *         outpainting_options: array<string, mixed>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function outpaintImage(
        string $imageUrl,
        string $outpaintPrompt,
        array $outpaintParams = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'outpaint_prompt' => $outpaintPrompt,
                'outpaint_params' => array_merge([
                    'expansion_ratio' => $outpaintParams['expansion_ratio'] ?? 1.5,
                    'direction' => $outpaintParams['direction'] ?? 'all',
                    'strength' => $outpaintParams['strength'] ?? 0.8,
                    'guidance_scale' => $outpaintParams['guidance_scale'] ?? 7.5,
                ], $outpaintParams),
                'options' => array_merge([
                    'preserve_original' => $options['preserve_original'] ?? true,
                    'seamless_blend' => $options['seamless_blend'] ?? true,
                    'auto_crop' => $options['auto_crop'] ?? false,
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/workspace/{$this->workspaceId}/image/outpaint", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $outpaintedImage = $responseData['outpainted_image'] ?? [];

            return [
                'success' => !empty($outpaintedImage['url']),
                'image_outpainting' => [
                    'image_url' => $imageUrl,
                    'outpaint_prompt' => $outpaintPrompt,
                    'outpaint_params' => $outpaintParams,
                    'outpainted_image' => [
                        'image_url' => $outpaintedImage['url'] ?? '',
                        'original_dimensions' => [
                            'width' => $outpaintedImage['original_width'] ?? 0,
                            'height' => $outpaintedImage['original_height'] ?? 0,
                        ],
                        'outpainted_dimensions' => [
                            'width' => $outpaintedImage['outpainted_width'] ?? 0,
                            'height' => $outpaintedImage['outpainted_height'] ?? 0,
                        ],
                        'expansion_ratio' => $outpaintedImage['expansion_ratio'] ?? 1.5,
                        'seamless_continuation' => $outpaintedImage['seamless'] ?? true,
                    ],
                    'outpainting_options' => $options,
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'image_outpainting' => [
                    'image_url' => $imageUrl,
                    'outpaint_prompt' => $outpaintPrompt,
                    'outpaint_params' => $outpaintParams,
                    'outpainted_image' => [
                        'image_url' => '',
                        'original_dimensions' => [
                            'width' => 0,
                            'height' => 0,
                        ],
                        'outpainted_dimensions' => [
                            'width' => 0,
                            'height' => 0,
                        ],
                        'expansion_ratio' => 0.0,
                        'seamless_continuation' => false,
                    ],
                    'outpainting_options' => $options,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}

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
#[AsTool('google_lens_analyze_image', 'Tool that analyzes images using Google Lens')]
#[AsTool('google_lens_text_recognition', 'Tool that recognizes text in images', method: 'textRecognition')]
#[AsTool('google_lens_object_detection', 'Tool that detects objects in images', method: 'objectDetection')]
#[AsTool('google_lens_landmark_recognition', 'Tool that recognizes landmarks in images', method: 'landmarkRecognition')]
#[AsTool('google_lens_product_search', 'Tool that searches for products in images', method: 'productSearch')]
#[AsTool('google_lens_plant_identification', 'Tool that identifies plants in images', method: 'plantIdentification')]
#[AsTool('google_lens_animal_identification', 'Tool that identifies animals in images', method: 'animalIdentification')]
#[AsTool('google_lens_food_identification', 'Tool that identifies food in images', method: 'foodIdentification')]
final readonly class GoogleLens
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://vision.googleapis.com/v1',
        private array $options = [],
    ) {
    }

    /**
     * Analyze image using Google Lens.
     *
     * @param string               $imageUrl       URL to image file
     * @param array<string, mixed> $features       Features to extract
     * @param string               $language       Language code
     * @param bool                 $includeRawData Include raw response data
     *
     * @return array{
     *     success: bool,
     *     analysis: array{
     *         textAnnotations: array<int, array{
     *             description: string,
     *             boundingPoly: array{
     *                 vertices: array<int, array{
     *                     x: int,
     *                     y: int,
     *                 }>,
     *             },
     *             locale: string,
     *         }>,
     *         faceAnnotations: array<int, array{
     *             boundingPoly: array{
     *                 vertices: array<int, array{
     *                     x: int,
     *                     y: int,
     *                 }>,
     *             },
     *             fdBounds: array{
     *                 vertices: array<int, array{
     *                     x: int,
     *                     y: int,
     *                 }>,
     *             },
     *             landmarks: array<int, array{
     *                 type: string,
     *                 position: array{
     *                     x: float,
     *                     y: float,
     *                     z: float,
     *                 },
     *             }>,
     *             rollAngle: float,
     *             panAngle: float,
     *             tiltAngle: float,
     *             detectionConfidence: float,
     *             landmarkingConfidence: float,
     *             joyLikelihood: string,
     *             sorrowLikelihood: string,
     *             angerLikelihood: string,
     *             surpriseLikelihood: string,
     *         }>,
     *         objectAnnotations: array<int, array{
     *             mid: string,
     *             name: string,
     *             score: float,
     *             boundingPoly: array{
     *                 normalizedVertices: array<int, array{
     *                     x: float,
     *                     y: float,
     *                 }>,
     *             },
     *         }>,
     *         logoAnnotations: array<int, array{
     *             mid: string,
     *             description: string,
     *             score: float,
     *             boundingPoly: array{
     *                 vertices: array<int, array{
     *                     x: int,
     *                     y: int,
     *                 }>,
     *             },
     *         }>,
     *         labelAnnotations: array<int, array{
     *             mid: string,
     *             description: string,
     *             score: float,
     *             topicality: float,
     *         }>,
     *         landmarkAnnotations: array<int, array{
     *             mid: string,
     *             description: string,
     *             score: float,
     *             locations: array<int, array{
     *                 latLng: array{
     *                     latitude: float,
     *                     longitude: float,
     *                 },
     *             }>,
     *         }>,
     *         cropHintsAnnotations: array{
     *             cropHints: array<int, array{
     *                 boundingPoly: array{
     *                     vertices: array<int, array{
     *                         x: int,
     *                         y: int,
     *                     }>,
     *                 },
     *                 confidence: float,
     *                 importanceFraction: float,
     *             }>,
     *         },
     *         webDetection: array{
     *             webEntities: array<int, array{
     *                 entityId: string,
     *                 score: float,
     *                 description: string,
     *             }>,
     *             fullMatchingImages: array<int, array{
     *                 url: string,
     *                 score: float,
     *             }>,
     *             partialMatchingImages: array<int, array{
     *                 url: string,
     *                 score: float,
     *             }>,
     *             pagesWithMatchingImages: array<int, array{
     *                 url: string,
     *                 pageTitle: string,
     *                 fullMatchingImages: array<int, array{
     *                     url: string,
     *                     score: float,
     *                 }>,
     *             }>,
     *         },
     *     },
     *     language: string,
     *     error: string,
     * }
     */
    public function __invoke(
        string $imageUrl,
        array $features = ['TEXT_DETECTION', 'FACE_DETECTION', 'OBJECT_LOCALIZATION', 'LOGO_DETECTION', 'LABEL_DETECTION', 'LANDMARK_DETECTION', 'CROP_HINTS', 'WEB_DETECTION'],
        string $language = 'en',
        bool $includeRawData = false,
    ): array {
        try {
            $requestData = [
                'requests' => [
                    [
                        'image' => [
                            'source' => [
                                'imageUri' => $imageUrl,
                            ],
                        ],
                        'features' => array_map(fn ($feature) => [
                            'type' => $feature,
                            'maxResults' => 50,
                        ], $features),
                        'imageContext' => [
                            'languageHints' => [$language],
                        ],
                    ],
                ],
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/images:annotate", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, ['key' => $this->apiKey]),
                'json' => $requestData,
            ]);

            $data = $response->toArray();
            $responses = $data['responses'][0] ?? [];

            return [
                'success' => true,
                'analysis' => [
                    'textAnnotations' => array_map(fn ($annotation) => [
                        'description' => $annotation['description'] ?? '',
                        'boundingPoly' => [
                            'vertices' => array_map(fn ($vertex) => [
                                'x' => $vertex['x'] ?? 0,
                                'y' => $vertex['y'] ?? 0,
                            ], $annotation['boundingPoly']['vertices'] ?? []),
                        ],
                        'locale' => $annotation['locale'] ?? $language,
                    ], $responses['textAnnotations'] ?? []),
                    'faceAnnotations' => array_map(fn ($annotation) => [
                        'boundingPoly' => [
                            'vertices' => array_map(fn ($vertex) => [
                                'x' => $vertex['x'] ?? 0,
                                'y' => $vertex['y'] ?? 0,
                            ], $annotation['boundingPoly']['vertices'] ?? []),
                        ],
                        'fdBounds' => [
                            'vertices' => array_map(fn ($vertex) => [
                                'x' => $vertex['x'] ?? 0,
                                'y' => $vertex['y'] ?? 0,
                            ], $annotation['fdBounds']['vertices'] ?? []),
                        ],
                        'landmarks' => array_map(fn ($landmark) => [
                            'type' => $landmark['type'] ?? '',
                            'position' => [
                                'x' => $landmark['position']['x'] ?? 0.0,
                                'y' => $landmark['position']['y'] ?? 0.0,
                                'z' => $landmark['position']['z'] ?? 0.0,
                            ],
                        ], $annotation['landmarks'] ?? []),
                        'rollAngle' => $annotation['rollAngle'] ?? 0.0,
                        'panAngle' => $annotation['panAngle'] ?? 0.0,
                        'tiltAngle' => $annotation['tiltAngle'] ?? 0.0,
                        'detectionConfidence' => $annotation['detectionConfidence'] ?? 0.0,
                        'landmarkingConfidence' => $annotation['landmarkingConfidence'] ?? 0.0,
                        'joyLikelihood' => $annotation['joyLikelihood'] ?? 'UNKNOWN',
                        'sorrowLikelihood' => $annotation['sorrowLikelihood'] ?? 'UNKNOWN',
                        'angerLikelihood' => $annotation['angerLikelihood'] ?? 'UNKNOWN',
                        'surpriseLikelihood' => $annotation['surpriseLikelihood'] ?? 'UNKNOWN',
                    ], $responses['faceAnnotations'] ?? []),
                    'objectAnnotations' => array_map(fn ($annotation) => [
                        'mid' => $annotation['mid'] ?? '',
                        'name' => $annotation['name'] ?? '',
                        'score' => $annotation['score'] ?? 0.0,
                        'boundingPoly' => [
                            'normalizedVertices' => array_map(fn ($vertex) => [
                                'x' => $vertex['x'] ?? 0.0,
                                'y' => $vertex['y'] ?? 0.0,
                            ], $annotation['boundingPoly']['normalizedVertices'] ?? []),
                        ],
                    ], $responses['localizedObjectAnnotations'] ?? []),
                    'logoAnnotations' => array_map(fn ($annotation) => [
                        'mid' => $annotation['mid'] ?? '',
                        'description' => $annotation['description'] ?? '',
                        'score' => $annotation['score'] ?? 0.0,
                        'boundingPoly' => [
                            'vertices' => array_map(fn ($vertex) => [
                                'x' => $vertex['x'] ?? 0,
                                'y' => $vertex['y'] ?? 0,
                            ], $annotation['boundingPoly']['vertices'] ?? []),
                        ],
                    ], $responses['logoAnnotations'] ?? []),
                    'labelAnnotations' => array_map(fn ($annotation) => [
                        'mid' => $annotation['mid'] ?? '',
                        'description' => $annotation['description'] ?? '',
                        'score' => $annotation['score'] ?? 0.0,
                        'topicality' => $annotation['topicality'] ?? 0.0,
                    ], $responses['labelAnnotations'] ?? []),
                    'landmarkAnnotations' => array_map(fn ($annotation) => [
                        'mid' => $annotation['mid'] ?? '',
                        'description' => $annotation['description'] ?? '',
                        'score' => $annotation['score'] ?? 0.0,
                        'locations' => array_map(fn ($location) => [
                            'latLng' => [
                                'latitude' => $location['latLng']['latitude'] ?? 0.0,
                                'longitude' => $location['latLng']['longitude'] ?? 0.0,
                            ],
                        ], $annotation['locations'] ?? []),
                    ], $responses['landmarkAnnotations'] ?? []),
                    'cropHintsAnnotations' => [
                        'cropHints' => array_map(fn ($hint) => [
                            'boundingPoly' => [
                                'vertices' => array_map(fn ($vertex) => [
                                    'x' => $vertex['x'] ?? 0,
                                    'y' => $vertex['y'] ?? 0,
                                ], $hint['boundingPoly']['vertices'] ?? []),
                            ],
                            'confidence' => $hint['confidence'] ?? 0.0,
                            'importanceFraction' => $hint['importanceFraction'] ?? 0.0,
                        ], $responses['cropHintsAnnotation']['cropHints'] ?? []),
                    ],
                    'webDetection' => [
                        'webEntities' => array_map(fn ($entity) => [
                            'entityId' => $entity['entityId'] ?? '',
                            'score' => $entity['score'] ?? 0.0,
                            'description' => $entity['description'] ?? '',
                        ], $responses['webDetection']['webEntities'] ?? []),
                        'fullMatchingImages' => array_map(fn ($image) => [
                            'url' => $image['url'] ?? '',
                            'score' => $image['score'] ?? 0.0,
                        ], $responses['webDetection']['fullMatchingImages'] ?? []),
                        'partialMatchingImages' => array_map(fn ($image) => [
                            'url' => $image['url'] ?? '',
                            'score' => $image['score'] ?? 0.0,
                        ], $responses['webDetection']['partialMatchingImages'] ?? []),
                        'pagesWithMatchingImages' => array_map(fn ($page) => [
                            'url' => $page['url'] ?? '',
                            'pageTitle' => $page['pageTitle'] ?? '',
                            'fullMatchingImages' => array_map(fn ($image) => [
                                'url' => $image['url'] ?? '',
                                'score' => $image['score'] ?? 0.0,
                            ], $page['fullMatchingImages'] ?? []),
                        ], $responses['webDetection']['pagesWithMatchingImages'] ?? []),
                    ],
                ],
                'language' => $language,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'analysis' => [
                    'textAnnotations' => [],
                    'faceAnnotations' => [],
                    'objectAnnotations' => [],
                    'logoAnnotations' => [],
                    'labelAnnotations' => [],
                    'landmarkAnnotations' => [],
                    'cropHintsAnnotations' => ['cropHints' => []],
                    'webDetection' => [
                        'webEntities' => [],
                        'fullMatchingImages' => [],
                        'partialMatchingImages' => [],
                        'pagesWithMatchingImages' => [],
                    ],
                ],
                'language' => $language,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Recognize text in images.
     *
     * @param string $imageUrl    URL to image file
     * @param string $language    Language code
     * @param bool   $extractText Extract plain text
     *
     * @return array{
     *     success: bool,
     *     text: string,
     *     annotations: array<int, array{
     *         description: string,
     *         boundingPoly: array{
     *             vertices: array<int, array{
     *                 x: int,
     *                 y: int,
     *             }>,
     *         },
     *         locale: string,
     *     }>,
     *     confidence: float,
     *     language: string,
     *     error: string,
     * }
     */
    public function textRecognition(
        string $imageUrl,
        string $language = 'en',
        bool $extractText = true,
    ): array {
        try {
            $requestData = [
                'requests' => [
                    [
                        'image' => [
                            'source' => [
                                'imageUri' => $imageUrl,
                            ],
                        ],
                        'features' => [
                            [
                                'type' => 'TEXT_DETECTION',
                                'maxResults' => 50,
                            ],
                        ],
                        'imageContext' => [
                            'languageHints' => [$language],
                        ],
                    ],
                ],
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/images:annotate", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, ['key' => $this->apiKey]),
                'json' => $requestData,
            ]);

            $data = $response->toArray();
            $responses = $data['responses'][0] ?? [];
            $textAnnotations = $responses['textAnnotations'] ?? [];

            $extractedText = '';
            if ($extractText && !empty($textAnnotations)) {
                $extractedText = $textAnnotations[0]['description'] ?? '';
            }

            $totalConfidence = 0.0;
            $annotationCount = \count($textAnnotations);
            if ($annotationCount > 0) {
                $totalConfidence = array_sum(array_column($textAnnotations, 'score')) / $annotationCount;
            }

            return [
                'success' => true,
                'text' => $extractedText,
                'annotations' => array_map(fn ($annotation) => [
                    'description' => $annotation['description'] ?? '',
                    'boundingPoly' => [
                        'vertices' => array_map(fn ($vertex) => [
                            'x' => $vertex['x'] ?? 0,
                            'y' => $vertex['y'] ?? 0,
                        ], $annotation['boundingPoly']['vertices'] ?? []),
                    ],
                    'locale' => $annotation['locale'] ?? $language,
                ], $textAnnotations),
                'confidence' => $totalConfidence,
                'language' => $language,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'text' => '',
                'annotations' => [],
                'confidence' => 0.0,
                'language' => $language,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Detect objects in images.
     *
     * @param string $imageUrl   URL to image file
     * @param int    $maxResults Maximum number of objects
     *
     * @return array{
     *     success: bool,
     *     objects: array<int, array{
     *         mid: string,
     *         name: string,
     *         score: float,
     *         boundingPoly: array{
     *             normalizedVertices: array<int, array{
     *                 x: float,
     *                 y: float,
     *             }>,
     *         },
     *     }>,
     *     totalObjects: int,
     *     averageConfidence: float,
     *     error: string,
     * }
     */
    public function objectDetection(
        string $imageUrl,
        int $maxResults = 20,
    ): array {
        try {
            $requestData = [
                'requests' => [
                    [
                        'image' => [
                            'source' => [
                                'imageUri' => $imageUrl,
                            ],
                        ],
                        'features' => [
                            [
                                'type' => 'OBJECT_LOCALIZATION',
                                'maxResults' => max(1, min($maxResults, 50)),
                            ],
                        ],
                    ],
                ],
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/images:annotate", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, ['key' => $this->apiKey]),
                'json' => $requestData,
            ]);

            $data = $response->toArray();
            $responses = $data['responses'][0] ?? [];
            $objectAnnotations = $responses['localizedObjectAnnotations'] ?? [];

            $averageConfidence = 0.0;
            if (!empty($objectAnnotations)) {
                $averageConfidence = array_sum(array_column($objectAnnotations, 'score')) / \count($objectAnnotations);
            }

            return [
                'success' => true,
                'objects' => array_map(fn ($annotation) => [
                    'mid' => $annotation['mid'] ?? '',
                    'name' => $annotation['name'] ?? '',
                    'score' => $annotation['score'] ?? 0.0,
                    'boundingPoly' => [
                        'normalizedVertices' => array_map(fn ($vertex) => [
                            'x' => $vertex['x'] ?? 0.0,
                            'y' => $vertex['y'] ?? 0.0,
                        ], $annotation['boundingPoly']['normalizedVertices'] ?? []),
                    ],
                ], $objectAnnotations),
                'totalObjects' => \count($objectAnnotations),
                'averageConfidence' => $averageConfidence,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'objects' => [],
                'totalObjects' => 0,
                'averageConfidence' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Recognize landmarks in images.
     *
     * @param string $imageUrl   URL to image file
     * @param int    $maxResults Maximum number of landmarks
     *
     * @return array{
     *     success: bool,
     *     landmarks: array<int, array{
     *         mid: string,
     *         description: string,
     *         score: float,
     *         locations: array<int, array{
     *             latLng: array{
     *                 latitude: float,
     *                 longitude: float,
     *             },
     *         }>,
     *     }>,
     *     totalLandmarks: int,
     *     error: string,
     * }
     */
    public function landmarkRecognition(
        string $imageUrl,
        int $maxResults = 10,
    ): array {
        try {
            $requestData = [
                'requests' => [
                    [
                        'image' => [
                            'source' => [
                                'imageUri' => $imageUrl,
                            ],
                        ],
                        'features' => [
                            [
                                'type' => 'LANDMARK_DETECTION',
                                'maxResults' => max(1, min($maxResults, 50)),
                            ],
                        ],
                    ],
                ],
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/images:annotate", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, ['key' => $this->apiKey]),
                'json' => $requestData,
            ]);

            $data = $response->toArray();
            $responses = $data['responses'][0] ?? [];
            $landmarkAnnotations = $responses['landmarkAnnotations'] ?? [];

            return [
                'success' => true,
                'landmarks' => array_map(fn ($annotation) => [
                    'mid' => $annotation['mid'] ?? '',
                    'description' => $annotation['description'] ?? '',
                    'score' => $annotation['score'] ?? 0.0,
                    'locations' => array_map(fn ($location) => [
                        'latLng' => [
                            'latitude' => $location['latLng']['latitude'] ?? 0.0,
                            'longitude' => $location['latLng']['longitude'] ?? 0.0,
                        ],
                    ], $annotation['locations'] ?? []),
                ], $landmarkAnnotations),
                'totalLandmarks' => \count($landmarkAnnotations),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'landmarks' => [],
                'totalLandmarks' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search for products in images.
     *
     * @param string $imageUrl   URL to image file
     * @param string $language   Language code
     * @param int    $maxResults Maximum number of results
     *
     * @return array{
     *     success: bool,
     *     products: array<int, array{
     *         entityId: string,
     *         description: string,
     *         score: float,
     *         boundingPoly: array{
     *             vertices: array<int, array{
     *                 x: int,
     *                 y: int,
     *             }>,
     *         },
     *     }>,
     *     totalProducts: int,
     *     averageScore: float,
     *     error: string,
     * }
     */
    public function productSearch(
        string $imageUrl,
        string $language = 'en',
        int $maxResults = 20,
    ): array {
        try {
            $requestData = [
                'requests' => [
                    [
                        'image' => [
                            'source' => [
                                'imageUri' => $imageUrl,
                            ],
                        ],
                        'features' => [
                            [
                                'type' => 'PRODUCT_SEARCH',
                                'maxResults' => max(1, min($maxResults, 50)),
                            ],
                        ],
                        'imageContext' => [
                            'languageHints' => [$language],
                        ],
                    ],
                ],
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/images:annotate", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, ['key' => $this->apiKey]),
                'json' => $requestData,
            ]);

            $data = $response->toArray();
            $responses = $data['responses'][0] ?? [];
            $productAnnotations = $responses['productSearchResults']['productGroupedResults'][0]['results'] ?? [];

            $averageScore = 0.0;
            if (!empty($productAnnotations)) {
                $averageScore = array_sum(array_column($productAnnotations, 'score')) / \count($productAnnotations);
            }

            return [
                'success' => true,
                'products' => array_map(fn ($product) => [
                    'entityId' => $product['product']['entityId'] ?? '',
                    'description' => $product['product']['description'] ?? '',
                    'score' => $product['score'] ?? 0.0,
                    'boundingPoly' => [
                        'vertices' => array_map(fn ($vertex) => [
                            'x' => $vertex['x'] ?? 0,
                            'y' => $vertex['y'] ?? 0,
                        ], $product['boundingPoly']['vertices'] ?? []),
                    ],
                ], $productAnnotations),
                'totalProducts' => \count($productAnnotations),
                'averageScore' => $averageScore,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'products' => [],
                'totalProducts' => 0,
                'averageScore' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Identify plants in images.
     *
     * @param string $imageUrl URL to image file
     * @param string $language Language code
     *
     * @return array{
     *     success: bool,
     *     plants: array<int, array{
     *         name: string,
     *         scientificName: string,
     *         description: string,
     *         confidence: float,
     *         careInstructions: array<string, mixed>,
     *         growthInfo: array<string, mixed>,
     *     }>,
     *     totalPlants: int,
     *     error: string,
     * }
     */
    public function plantIdentification(
        string $imageUrl,
        string $language = 'en',
    ): array {
        try {
            // This would typically use a specialized plant identification API
            // For now, we'll use Google Vision API with plant-specific labels
            $requestData = [
                'requests' => [
                    [
                        'image' => [
                            'source' => [
                                'imageUri' => $imageUrl,
                            ],
                        ],
                        'features' => [
                            [
                                'type' => 'LABEL_DETECTION',
                                'maxResults' => 50,
                            ],
                        ],
                        'imageContext' => [
                            'languageHints' => [$language],
                        ],
                    ],
                ],
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/images:annotate", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, ['key' => $this->apiKey]),
                'json' => $requestData,
            ]);

            $data = $response->toArray();
            $responses = $data['responses'][0] ?? [];
            $labelAnnotations = $responses['labelAnnotations'] ?? [];

            // Filter for plant-related labels
            $plantKeywords = ['plant', 'tree', 'flower', 'leaf', 'vegetation', 'flora', 'herb', 'shrub', 'cactus', 'succulent'];
            $plantLabels = array_filter($labelAnnotations, fn ($label) => \in_array(strtolower($label['description']), $plantKeywords)
                || str_contains(strtolower($label['description']), 'plant')
                || str_contains(strtolower($label['description']), 'tree')
                || str_contains(strtolower($label['description']), 'flower')
            );

            return [
                'success' => true,
                'plants' => array_map(fn ($label) => [
                    'name' => $label['description'] ?? '',
                    'scientificName' => '',
                    'description' => 'Plant identified using Google Vision API',
                    'confidence' => $label['score'] ?? 0.0,
                    'careInstructions' => [],
                    'growthInfo' => [],
                ], $plantLabels),
                'totalPlants' => \count($plantLabels),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'plants' => [],
                'totalPlants' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Identify animals in images.
     *
     * @param string $imageUrl URL to image file
     * @param string $language Language code
     *
     * @return array{
     *     success: bool,
     *     animals: array<int, array{
     *         name: string,
     *         scientificName: string,
     *         description: string,
     *         confidence: float,
     *         habitat: string,
     *         diet: string,
     *         behavior: string,
     *     }>,
     *     totalAnimals: int,
     *     error: string,
     * }
     */
    public function animalIdentification(
        string $imageUrl,
        string $language = 'en',
    ): array {
        try {
            $requestData = [
                'requests' => [
                    [
                        'image' => [
                            'source' => [
                                'imageUri' => $imageUrl,
                            ],
                        ],
                        'features' => [
                            [
                                'type' => 'LABEL_DETECTION',
                                'maxResults' => 50,
                            ],
                        ],
                        'imageContext' => [
                            'languageHints' => [$language],
                        ],
                    ],
                ],
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/images:annotate", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, ['key' => $this->apiKey]),
                'json' => $requestData,
            ]);

            $data = $response->toArray();
            $responses = $data['responses'][0] ?? [];
            $labelAnnotations = $responses['labelAnnotations'] ?? [];

            // Filter for animal-related labels
            $animalKeywords = ['animal', 'dog', 'cat', 'bird', 'fish', 'horse', 'cow', 'pig', 'sheep', 'goat', 'lion', 'tiger', 'bear', 'elephant', 'monkey', 'rabbit', 'hamster', 'snake', 'lizard', 'frog', 'turtle', 'spider', 'insect', 'butterfly', 'bee'];
            $animalLabels = array_filter($labelAnnotations, fn ($label) => \in_array(strtolower($label['description']), $animalKeywords)
                || str_contains(strtolower($label['description']), 'animal')
                || str_contains(strtolower($label['description']), 'pet')
                || str_contains(strtolower($label['description']), 'wildlife')
            );

            return [
                'success' => true,
                'animals' => array_map(fn ($label) => [
                    'name' => $label['description'] ?? '',
                    'scientificName' => '',
                    'description' => 'Animal identified using Google Vision API',
                    'confidence' => $label['score'] ?? 0.0,
                    'habitat' => '',
                    'diet' => '',
                    'behavior' => '',
                ], $animalLabels),
                'totalAnimals' => \count($animalLabels),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'animals' => [],
                'totalAnimals' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Identify food in images.
     *
     * @param string $imageUrl URL to image file
     * @param string $language Language code
     *
     * @return array{
     *     success: bool,
     *     foods: array<int, array{
     *         name: string,
     *         description: string,
     *         confidence: float,
     *         calories: int,
     *         nutritionInfo: array<string, mixed>,
     *         ingredients: array<int, string>,
     *     }>,
     *     totalFoods: int,
     *     error: string,
     * }
     */
    public function foodIdentification(
        string $imageUrl,
        string $language = 'en',
    ): array {
        try {
            $requestData = [
                'requests' => [
                    [
                        'image' => [
                            'source' => [
                                'imageUri' => $imageUrl,
                            ],
                        ],
                        'features' => [
                            [
                                'type' => 'LABEL_DETECTION',
                                'maxResults' => 50,
                            ],
                        ],
                        'imageContext' => [
                            'languageHints' => [$language],
                        ],
                    ],
                ],
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/images:annotate", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, ['key' => $this->apiKey]),
                'json' => $requestData,
            ]);

            $data = $response->toArray();
            $responses = $data['responses'][0] ?? [];
            $labelAnnotations = $responses['labelAnnotations'] ?? [];

            // Filter for food-related labels
            $foodKeywords = ['food', 'meal', 'dish', 'pizza', 'burger', 'sandwich', 'salad', 'soup', 'pasta', 'rice', 'bread', 'cake', 'cookie', 'fruit', 'vegetable', 'meat', 'fish', 'chicken', 'beef', 'pork', 'cheese', 'milk', 'yogurt', 'ice cream', 'chocolate', 'coffee', 'tea', 'juice', 'wine', 'beer'];
            $foodLabels = array_filter($labelAnnotations, fn ($label) => \in_array(strtolower($label['description']), $foodKeywords)
                || str_contains(strtolower($label['description']), 'food')
                || str_contains(strtolower($label['description']), 'meal')
                || str_contains(strtolower($label['description']), 'dish')
                || str_contains(strtolower($label['description']), 'cuisine')
            );

            return [
                'success' => true,
                'foods' => array_map(fn ($label) => [
                    'name' => $label['description'] ?? '',
                    'description' => 'Food identified using Google Vision API',
                    'confidence' => $label['score'] ?? 0.0,
                    'calories' => 0,
                    'nutritionInfo' => [],
                    'ingredients' => [],
                ], $foodLabels),
                'totalFoods' => \count($foodLabels),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'foods' => [],
                'totalFoods' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }
}

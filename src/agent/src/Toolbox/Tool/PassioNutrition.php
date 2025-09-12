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
#[AsTool('passio_nutrition_analyze_food', 'Tool that analyzes food using Passio Nutrition AI')]
#[AsTool('passio_nutrition_identify_food', 'Tool that identifies food items', method: 'identifyFood')]
#[AsTool('passio_nutrition_get_nutrition_facts', 'Tool that gets nutrition facts', method: 'getNutritionFacts')]
#[AsTool('passio_nutrition_calculate_calories', 'Tool that calculates calories', method: 'calculateCalories')]
#[AsTool('passio_nutrition_analyze_meal', 'Tool that analyzes complete meals', method: 'analyzeMeal')]
#[AsTool('passio_nutrition_suggest_alternatives', 'Tool that suggests food alternatives', method: 'suggestAlternatives')]
#[AsTool('passio_nutrition_track_intake', 'Tool that tracks nutritional intake', method: 'trackIntake')]
#[AsTool('passio_nutrition_generate_report', 'Tool that generates nutrition reports', method: 'generateReport')]
final readonly class PassioNutrition
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.passio.ai',
        private array $options = [],
    ) {
    }

    /**
     * Analyze food using Passio Nutrition AI.
     *
     * @param string               $imageUrl        URL or base64 encoded image
     * @param array<string, mixed> $analysisOptions Analysis options
     * @param array<string, mixed> $options         Analysis options
     *
     * @return array{
     *     success: bool,
     *     food_analysis: array{
     *         image_url: string,
     *         analysis_options: array<string, mixed>,
     *         detected_foods: array<int, array{
     *             food_name: string,
     *             confidence: float,
     *             portion_size: string,
     *             bounding_box: array{
     *                 x: int,
     *                 y: int,
     *                 width: int,
     *                 height: int,
     *             },
     *             nutrition_info: array{
     *                 calories: float,
     *                 protein: float,
     *                 carbs: float,
     *                 fat: float,
     *                 fiber: float,
     *                 sugar: float,
     *                 sodium: float,
     *             },
     *             ingredients: array<int, string>,
     *         }>,
     *         meal_summary: array{
     *             total_calories: float,
     *             total_protein: float,
     *             total_carbs: float,
     *             total_fat: float,
     *             health_score: float,
     *         },
     *         recommendations: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $imageUrl,
        array $analysisOptions = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'analysis_options' => array_merge([
                    'include_nutrition' => $analysisOptions['include_nutrition'] ?? true,
                    'include_ingredients' => $analysisOptions['include_ingredients'] ?? true,
                    'include_allergens' => $analysisOptions['include_allergens'] ?? true,
                    'confidence_threshold' => $analysisOptions['confidence_threshold'] ?? 0.7,
                ], $analysisOptions),
                'options' => array_merge([
                    'portion_estimation' => $options['portion_estimation'] ?? true,
                    'health_scoring' => $options['health_scoring'] ?? true,
                    'dietary_restrictions' => $options['dietary_restrictions'] ?? [],
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/nutrition/analyze", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $foods = $responseData['detected_foods'] ?? [];

            return [
                'success' => !empty($foods),
                'food_analysis' => [
                    'image_url' => $imageUrl,
                    'analysis_options' => $analysisOptions,
                    'detected_foods' => array_map(fn ($food) => [
                        'food_name' => $food['name'] ?? '',
                        'confidence' => $food['confidence'] ?? 0.0,
                        'portion_size' => $food['portion_size'] ?? '',
                        'bounding_box' => [
                            'x' => $food['bbox']['x'] ?? 0,
                            'y' => $food['bbox']['y'] ?? 0,
                            'width' => $food['bbox']['width'] ?? 0,
                            'height' => $food['bbox']['height'] ?? 0,
                        ],
                        'nutrition_info' => [
                            'calories' => $food['nutrition']['calories'] ?? 0.0,
                            'protein' => $food['nutrition']['protein'] ?? 0.0,
                            'carbs' => $food['nutrition']['carbs'] ?? 0.0,
                            'fat' => $food['nutrition']['fat'] ?? 0.0,
                            'fiber' => $food['nutrition']['fiber'] ?? 0.0,
                            'sugar' => $food['nutrition']['sugar'] ?? 0.0,
                            'sodium' => $food['nutrition']['sodium'] ?? 0.0,
                        ],
                        'ingredients' => $food['ingredients'] ?? [],
                    ], $foods),
                    'meal_summary' => [
                        'total_calories' => $responseData['meal_summary']['total_calories'] ?? 0.0,
                        'total_protein' => $responseData['meal_summary']['total_protein'] ?? 0.0,
                        'total_carbs' => $responseData['meal_summary']['total_carbs'] ?? 0.0,
                        'total_fat' => $responseData['meal_summary']['total_fat'] ?? 0.0,
                        'health_score' => $responseData['meal_summary']['health_score'] ?? 0.0,
                    ],
                    'recommendations' => $responseData['recommendations'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'food_analysis' => [
                    'image_url' => $imageUrl,
                    'analysis_options' => $analysisOptions,
                    'detected_foods' => [],
                    'meal_summary' => [
                        'total_calories' => 0.0,
                        'total_protein' => 0.0,
                        'total_carbs' => 0.0,
                        'total_fat' => 0.0,
                        'health_score' => 0.0,
                    ],
                    'recommendations' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Identify food items.
     *
     * @param string               $imageUrl       URL or base64 encoded image
     * @param array<string>        $foodCategories Food categories to detect
     * @param array<string, mixed> $options        Identification options
     *
     * @return array{
     *     success: bool,
     *     food_identification: array{
     *         image_url: string,
     *         food_categories: array<string>,
     *         identified_foods: array<int, array{
     *             food_name: string,
     *             category: string,
     *             confidence: float,
     *             portion_size: string,
     *             bounding_box: array{
     *                 x: int,
     *                 y: int,
     *                 width: int,
     *                 height: int,
     *             },
     *             food_type: string,
     *             preparation_method: string,
     *         }>,
     *         identification_confidence: float,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function identifyFood(
        string $imageUrl,
        array $foodCategories = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'food_categories' => $foodCategories ?: ['fruits', 'vegetables', 'grains', 'proteins', 'dairy', 'snacks'],
                'options' => array_merge([
                    'confidence_threshold' => $options['confidence_threshold'] ?? 0.7,
                    'include_preparation' => $options['include_preparation'] ?? true,
                    'include_brand_detection' => $options['include_brand'] ?? false,
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/nutrition/identify", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'food_identification' => [
                    'image_url' => $imageUrl,
                    'food_categories' => $foodCategories,
                    'identified_foods' => array_map(fn ($food) => [
                        'food_name' => $food['name'] ?? '',
                        'category' => $food['category'] ?? '',
                        'confidence' => $food['confidence'] ?? 0.0,
                        'portion_size' => $food['portion_size'] ?? '',
                        'bounding_box' => [
                            'x' => $food['bbox']['x'] ?? 0,
                            'y' => $food['bbox']['y'] ?? 0,
                            'width' => $food['bbox']['width'] ?? 0,
                            'height' => $food['bbox']['height'] ?? 0,
                        ],
                        'food_type' => $food['type'] ?? '',
                        'preparation_method' => $food['preparation'] ?? '',
                    ], $responseData['foods'] ?? []),
                    'identification_confidence' => $responseData['overall_confidence'] ?? 0.0,
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'food_identification' => [
                    'image_url' => $imageUrl,
                    'food_categories' => $foodCategories,
                    'identified_foods' => [],
                    'identification_confidence' => 0.0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get nutrition facts.
     *
     * @param string               $foodName    Name of the food
     * @param string               $portionSize Portion size
     * @param array<string, mixed> $options     Nutrition options
     *
     * @return array{
     *     success: bool,
     *     nutrition_facts: array{
     *         food_name: string,
     *         portion_size: string,
     *         nutrition_data: array{
     *             calories: float,
     *             macronutrients: array{
     *                 protein: array{
     *                     amount: float,
     *                     unit: string,
     *                     daily_value: float,
     *                 },
     *                 carbohydrates: array{
     *                     amount: float,
     *                     unit: string,
     *                     daily_value: float,
     *                 },
     *                 fat: array{
     *                     amount: float,
     *                     unit: string,
     *                     daily_value: float,
     *                 },
     *             },
     *             micronutrients: array<string, array{
     *                 amount: float,
     *                 unit: string,
     *                 daily_value: float,
     *             }>,
     *             fiber: float,
     *             sugar: float,
     *             sodium: float,
     *         },
     *         health_indicators: array<string, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function getNutritionFacts(
        string $foodName,
        string $portionSize = '100g',
        array $options = [],
    ): array {
        try {
            $requestData = [
                'food_name' => $foodName,
                'portion_size' => $portionSize,
                'options' => array_merge([
                    'include_micronutrients' => $options['include_micronutrients'] ?? true,
                    'include_daily_values' => $options['include_daily_values'] ?? true,
                    'database_source' => $options['database_source'] ?? 'usda',
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/nutrition/facts", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $nutrition = $responseData['nutrition'] ?? [];

            return [
                'success' => true,
                'nutrition_facts' => [
                    'food_name' => $foodName,
                    'portion_size' => $portionSize,
                    'nutrition_data' => [
                        'calories' => $nutrition['calories'] ?? 0.0,
                        'macronutrients' => [
                            'protein' => [
                                'amount' => $nutrition['protein']['amount'] ?? 0.0,
                                'unit' => $nutrition['protein']['unit'] ?? 'g',
                                'daily_value' => $nutrition['protein']['daily_value'] ?? 0.0,
                            ],
                            'carbohydrates' => [
                                'amount' => $nutrition['carbs']['amount'] ?? 0.0,
                                'unit' => $nutrition['carbs']['unit'] ?? 'g',
                                'daily_value' => $nutrition['carbs']['daily_value'] ?? 0.0,
                            ],
                            'fat' => [
                                'amount' => $nutrition['fat']['amount'] ?? 0.0,
                                'unit' => $nutrition['fat']['unit'] ?? 'g',
                                'daily_value' => $nutrition['fat']['daily_value'] ?? 0.0,
                            ],
                        ],
                        'micronutrients' => $nutrition['micronutrients'] ?? [],
                        'fiber' => $nutrition['fiber'] ?? 0.0,
                        'sugar' => $nutrition['sugar'] ?? 0.0,
                        'sodium' => $nutrition['sodium'] ?? 0.0,
                    ],
                    'health_indicators' => $responseData['health_indicators'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'nutrition_facts' => [
                    'food_name' => $foodName,
                    'portion_size' => $portionSize,
                    'nutrition_data' => [
                        'calories' => 0.0,
                        'macronutrients' => [
                            'protein' => ['amount' => 0.0, 'unit' => 'g', 'daily_value' => 0.0],
                            'carbohydrates' => ['amount' => 0.0, 'unit' => 'g', 'daily_value' => 0.0],
                            'fat' => ['amount' => 0.0, 'unit' => 'g', 'daily_value' => 0.0],
                        ],
                        'micronutrients' => [],
                        'fiber' => 0.0,
                        'sugar' => 0.0,
                        'sodium' => 0.0,
                    ],
                    'health_indicators' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate calories.
     *
     * @param string               $imageUrl           URL or base64 encoded image
     * @param array<string, mixed> $calculationOptions Calculation options
     * @param array<string, mixed> $options            Calculation options
     *
     * @return array{
     *     success: bool,
     *     calorie_calculation: array{
     *         image_url: string,
     *         calculation_options: array<string, mixed>,
     *         calorie_breakdown: array<int, array{
     *             food_name: string,
     *             portion_size: string,
     *             calories: float,
     *             confidence: float,
     *         }>,
     *         total_calories: float,
     *         calorie_distribution: array{
     *             proteins: float,
     *             carbohydrates: float,
     *             fats: float,
     *         },
     *         accuracy_estimate: float,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function calculateCalories(
        string $imageUrl,
        array $calculationOptions = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'calculation_options' => array_merge([
                    'include_portion_estimation' => $calculationOptions['include_portion'] ?? true,
                    'include_cooking_methods' => $calculationOptions['include_cooking'] ?? true,
                    'confidence_weighting' => $calculationOptions['confidence_weighting'] ?? true,
                ], $calculationOptions),
                'options' => array_merge([
                    'precision_level' => $options['precision'] ?? 'medium',
                    'include_breakdown' => $options['include_breakdown'] ?? true,
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/nutrition/calculate-calories", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'calorie_calculation' => [
                    'image_url' => $imageUrl,
                    'calculation_options' => $calculationOptions,
                    'calorie_breakdown' => array_map(fn ($food) => [
                        'food_name' => $food['name'] ?? '',
                        'portion_size' => $food['portion'] ?? '',
                        'calories' => $food['calories'] ?? 0.0,
                        'confidence' => $food['confidence'] ?? 0.0,
                    ], $responseData['breakdown'] ?? []),
                    'total_calories' => $responseData['total_calories'] ?? 0.0,
                    'calorie_distribution' => [
                        'proteins' => $responseData['distribution']['protein'] ?? 0.0,
                        'carbohydrates' => $responseData['distribution']['carbs'] ?? 0.0,
                        'fats' => $responseData['distribution']['fat'] ?? 0.0,
                    ],
                    'accuracy_estimate' => $responseData['accuracy'] ?? 0.0,
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'calorie_calculation' => [
                    'image_url' => $imageUrl,
                    'calculation_options' => $calculationOptions,
                    'calorie_breakdown' => [],
                    'total_calories' => 0.0,
                    'calorie_distribution' => [
                        'proteins' => 0.0,
                        'carbohydrates' => 0.0,
                        'fats' => 0.0,
                    ],
                    'accuracy_estimate' => 0.0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze complete meals.
     *
     * @param string               $imageUrl            URL or base64 encoded image
     * @param array<string, mixed> $mealAnalysisOptions Meal analysis options
     * @param array<string, mixed> $options             Analysis options
     *
     * @return array{
     *     success: bool,
     *     meal_analysis: array{
     *         image_url: string,
     *         meal_analysis_options: array<string, mixed>,
     *         meal_summary: array{
     *             meal_type: string,
     *             total_calories: float,
     *             macronutrient_breakdown: array{
     *                 protein_percentage: float,
     *                 carb_percentage: float,
     *                 fat_percentage: float,
     *             },
     *             health_score: float,
     *             balanced_score: float,
     *         },
     *         food_items: array<int, array{
     *             food_name: string,
     *             portion_size: string,
     *             calories: float,
     *             nutrients: array<string, float>,
     *         }>,
     *         dietary_analysis: array{
     *             allergens_detected: array<int, string>,
     *             dietary_restrictions: array<string, bool>,
     *             health_benefits: array<int, string>,
     *             concerns: array<int, string>,
     *         },
     *         recommendations: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function analyzeMeal(
        string $imageUrl,
        array $mealAnalysisOptions = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'image_url' => $imageUrl,
                'meal_analysis_options' => array_merge([
                    'include_health_scoring' => $mealAnalysisOptions['include_health'] ?? true,
                    'include_allergen_detection' => $mealAnalysisOptions['include_allergens'] ?? true,
                    'include_portion_analysis' => $mealAnalysisOptions['include_portions'] ?? true,
                ], $mealAnalysisOptions),
                'options' => array_merge([
                    'dietary_preferences' => $options['dietary_preferences'] ?? [],
                    'health_goals' => $options['health_goals'] ?? [],
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/nutrition/analyze-meal", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'meal_analysis' => [
                    'image_url' => $imageUrl,
                    'meal_analysis_options' => $mealAnalysisOptions,
                    'meal_summary' => [
                        'meal_type' => $responseData['meal_type'] ?? '',
                        'total_calories' => $responseData['total_calories'] ?? 0.0,
                        'macronutrient_breakdown' => [
                            'protein_percentage' => $responseData['macro_breakdown']['protein'] ?? 0.0,
                            'carb_percentage' => $responseData['macro_breakdown']['carbs'] ?? 0.0,
                            'fat_percentage' => $responseData['macro_breakdown']['fat'] ?? 0.0,
                        ],
                        'health_score' => $responseData['health_score'] ?? 0.0,
                        'balanced_score' => $responseData['balanced_score'] ?? 0.0,
                    ],
                    'food_items' => array_map(fn ($food) => [
                        'food_name' => $food['name'] ?? '',
                        'portion_size' => $food['portion'] ?? '',
                        'calories' => $food['calories'] ?? 0.0,
                        'nutrients' => $food['nutrients'] ?? [],
                    ], $responseData['food_items'] ?? []),
                    'dietary_analysis' => [
                        'allergens_detected' => $responseData['allergens'] ?? [],
                        'dietary_restrictions' => $responseData['dietary_restrictions'] ?? [],
                        'health_benefits' => $responseData['health_benefits'] ?? [],
                        'concerns' => $responseData['concerns'] ?? [],
                    ],
                    'recommendations' => $responseData['recommendations'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'meal_analysis' => [
                    'image_url' => $imageUrl,
                    'meal_analysis_options' => $mealAnalysisOptions,
                    'meal_summary' => [
                        'meal_type' => '',
                        'total_calories' => 0.0,
                        'macronutrient_breakdown' => [
                            'protein_percentage' => 0.0,
                            'carb_percentage' => 0.0,
                            'fat_percentage' => 0.0,
                        ],
                        'health_score' => 0.0,
                        'balanced_score' => 0.0,
                    ],
                    'food_items' => [],
                    'dietary_analysis' => [
                        'allergens_detected' => [],
                        'dietary_restrictions' => [],
                        'health_benefits' => [],
                        'concerns' => [],
                    ],
                    'recommendations' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Suggest food alternatives.
     *
     * @param string               $foodName    Name of the food
     * @param array<string, mixed> $preferences User preferences
     * @param array<string, mixed> $options     Alternative options
     *
     * @return array{
     *     success: bool,
     *     food_alternatives: array{
     *         original_food: string,
     *         preferences: array<string, mixed>,
     *         alternatives: array<int, array{
     *             food_name: string,
     *             similarity_score: float,
     *             nutrition_comparison: array{
     *                 calories_difference: float,
     *                 protein_difference: float,
     *                 carb_difference: float,
     *                 fat_difference: float,
     *             },
     *             health_benefits: array<int, string>,
     *             availability: string,
     *         }>,
     *         recommendation_reasoning: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function suggestAlternatives(
        string $foodName,
        array $preferences = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'food_name' => $foodName,
                'preferences' => array_merge([
                    'dietary_restrictions' => $preferences['dietary_restrictions'] ?? [],
                    'health_goals' => $preferences['health_goals'] ?? [],
                    'taste_preferences' => $preferences['taste_preferences'] ?? [],
                ], $preferences),
                'options' => array_merge([
                    'max_alternatives' => $options['max_alternatives'] ?? 5,
                    'include_nutrition_comparison' => $options['include_comparison'] ?? true,
                    'similarity_threshold' => $options['similarity_threshold'] ?? 0.6,
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/nutrition/suggest-alternatives", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'food_alternatives' => [
                    'original_food' => $foodName,
                    'preferences' => $preferences,
                    'alternatives' => array_map(fn ($alt) => [
                        'food_name' => $alt['name'] ?? '',
                        'similarity_score' => $alt['similarity'] ?? 0.0,
                        'nutrition_comparison' => [
                            'calories_difference' => $alt['calories_diff'] ?? 0.0,
                            'protein_difference' => $alt['protein_diff'] ?? 0.0,
                            'carb_difference' => $alt['carb_diff'] ?? 0.0,
                            'fat_difference' => $alt['fat_diff'] ?? 0.0,
                        ],
                        'health_benefits' => $alt['health_benefits'] ?? [],
                        'availability' => $alt['availability'] ?? '',
                    ], $responseData['alternatives'] ?? []),
                    'recommendation_reasoning' => $responseData['reasoning'] ?? [],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'food_alternatives' => [
                    'original_food' => $foodName,
                    'preferences' => $preferences,
                    'alternatives' => [],
                    'recommendation_reasoning' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Track nutritional intake.
     *
     * @param array<int, array<string, mixed>> $foodItems       Array of food items consumed
     * @param array<string, mixed>             $trackingOptions Tracking options
     * @param array<string, mixed>             $options         Tracking options
     *
     * @return array{
     *     success: bool,
     *     intake_tracking: array{
     *         food_items: array<int, array<string, mixed>>,
     *         tracking_options: array<string, mixed>,
     *         daily_summary: array{
     *             total_calories: float,
     *             macronutrients: array{
     *                 protein: float,
     *                 carbohydrates: float,
     *                 fat: float,
     *             },
     *             micronutrients: array<string, float>,
     *             meal_distribution: array<string, float>,
     *         },
     *         progress_analysis: array{
     *             goal_comparison: array<string, mixed>,
     *             trends: array<string, mixed>,
     *             recommendations: array<int, string>,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function trackIntake(
        array $foodItems,
        array $trackingOptions = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'food_items' => $foodItems,
                'tracking_options' => array_merge([
                    'include_micronutrients' => $trackingOptions['include_micronutrients'] ?? true,
                    'include_meal_distribution' => $trackingOptions['include_meals'] ?? true,
                    'include_progress_analysis' => $trackingOptions['include_progress'] ?? true,
                ], $trackingOptions),
                'options' => array_merge([
                    'user_goals' => $options['user_goals'] ?? [],
                    'tracking_period' => $options['tracking_period'] ?? 'daily',
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/nutrition/track-intake", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'intake_tracking' => [
                    'food_items' => $foodItems,
                    'tracking_options' => $trackingOptions,
                    'daily_summary' => [
                        'total_calories' => $responseData['daily_summary']['total_calories'] ?? 0.0,
                        'macronutrients' => [
                            'protein' => $responseData['daily_summary']['protein'] ?? 0.0,
                            'carbohydrates' => $responseData['daily_summary']['carbs'] ?? 0.0,
                            'fat' => $responseData['daily_summary']['fat'] ?? 0.0,
                        ],
                        'micronutrients' => $responseData['daily_summary']['micronutrients'] ?? [],
                        'meal_distribution' => $responseData['daily_summary']['meal_distribution'] ?? [],
                    ],
                    'progress_analysis' => [
                        'goal_comparison' => $responseData['progress']['goal_comparison'] ?? [],
                        'trends' => $responseData['progress']['trends'] ?? [],
                        'recommendations' => $responseData['progress']['recommendations'] ?? [],
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'intake_tracking' => [
                    'food_items' => $foodItems,
                    'tracking_options' => $trackingOptions,
                    'daily_summary' => [
                        'total_calories' => 0.0,
                        'macronutrients' => [
                            'protein' => 0.0,
                            'carbohydrates' => 0.0,
                            'fat' => 0.0,
                        ],
                        'micronutrients' => [],
                        'meal_distribution' => [],
                    ],
                    'progress_analysis' => [
                        'goal_comparison' => [],
                        'trends' => [],
                        'recommendations' => [],
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate nutrition reports.
     *
     * @param array<string, mixed> $reportData Report data
     * @param string               $reportType Type of report
     * @param array<string, mixed> $options    Report options
     *
     * @return array{
     *     success: bool,
     *     nutrition_report: array{
     *         report_data: array<string, mixed>,
     *         report_type: string,
     *         report_content: array{
     *             executive_summary: string,
     *             detailed_analysis: array<string, mixed>,
     *             charts_data: array<string, mixed>,
     *             recommendations: array<int, string>,
     *             insights: array<int, string>,
     *         },
     *         report_metadata: array{
     *             generated_at: string,
     *             report_period: string,
     *             data_points: int,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function generateReport(
        array $reportData,
        string $reportType = 'daily',
        array $options = [],
    ): array {
        try {
            $requestData = [
                'report_data' => $reportData,
                'report_type' => $reportType,
                'options' => array_merge([
                    'include_charts' => $options['include_charts'] ?? true,
                    'include_recommendations' => $options['include_recommendations'] ?? true,
                    'include_insights' => $options['include_insights'] ?? true,
                ], $options),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/nutrition/generate-report", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();

            return [
                'success' => true,
                'nutrition_report' => [
                    'report_data' => $reportData,
                    'report_type' => $reportType,
                    'report_content' => [
                        'executive_summary' => $responseData['summary'] ?? '',
                        'detailed_analysis' => $responseData['analysis'] ?? [],
                        'charts_data' => $responseData['charts'] ?? [],
                        'recommendations' => $responseData['recommendations'] ?? [],
                        'insights' => $responseData['insights'] ?? [],
                    ],
                    'report_metadata' => [
                        'generated_at' => $responseData['generated_at'] ?? date('c'),
                        'report_period' => $responseData['period'] ?? '',
                        'data_points' => $responseData['data_points'] ?? 0,
                    ],
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'nutrition_report' => [
                    'report_data' => $reportData,
                    'report_type' => $reportType,
                    'report_content' => [
                        'executive_summary' => '',
                        'detailed_analysis' => [],
                        'charts_data' => [],
                        'recommendations' => [],
                        'insights' => [],
                    ],
                    'report_metadata' => [
                        'generated_at' => '',
                        'report_period' => '',
                        'data_points' => 0,
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}

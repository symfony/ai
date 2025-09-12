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
#[AsTool('azure_ai_analyze', 'Tool that analyzes content using Azure AI Services')]
#[AsTool('azure_ai_translate', 'Tool that translates text', method: 'translate')]
#[AsTool('azure_ai_summarize', 'Tool that summarizes content', method: 'summarize')]
#[AsTool('azure_ai_extract_keywords', 'Tool that extracts keywords', method: 'extractKeywords')]
#[AsTool('azure_ai_sentiment_analysis', 'Tool that analyzes sentiment', method: 'sentimentAnalysis')]
#[AsTool('azure_ai_entity_recognition', 'Tool that recognizes entities', method: 'entityRecognition')]
#[AsTool('azure_ai_language_detection', 'Tool that detects language', method: 'languageDetection')]
#[AsTool('azure_ai_text_analytics', 'Tool that performs text analytics', method: 'textAnalytics')]
final readonly class AzureAi
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
     * Analyze content using Azure AI Services.
     *
     * @param string               $content         Content to analyze
     * @param array<string, mixed> $analysisOptions Analysis options
     * @param array<string, mixed> $context         Analysis context
     *
     * @return array{
     *     success: bool,
     *     analysis: array{
     *         content: string,
     *         analysis_options: array<string, mixed>,
     *         results: array{
     *             sentiment: array{
     *                 score: float,
     *                 label: string,
     *                 confidence: float,
     *             },
     *             entities: array<int, array{
     *                 text: string,
     *                 category: string,
     *                 subcategory: string,
     *                 confidence: float,
     *                 offset: int,
     *                 length: int,
     *             }>,
     *             key_phrases: array<int, string>,
     *             language: array{
     *                 name: string,
     *                 iso6391_name: string,
     *                 confidence: float,
     *             },
     *             pii_entities: array<int, array{
     *                 text: string,
     *                 category: string,
     *                 subcategory: string,
     *                 confidence: float,
     *             }>,
     *         },
     *         insights: array<int, string>,
     *         recommendations: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $content,
        array $analysisOptions = [],
        array $context = [],
    ): array {
        try {
            $requestData = [
                'documents' => [
                    [
                        'id' => '1',
                        'text' => $content,
                        'language' => $context['language'] ?? 'en',
                    ],
                ],
                'analysis_options' => $analysisOptions,
            ];

            $response = $this->httpClient->request('POST', "{$this->endpoint}/text/analytics/v3.1/analyze", [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $document = $responseData['documents'][0] ?? [];
            $errors = $responseData['errors'] ?? [];

            return [
                'success' => empty($errors),
                'analysis' => [
                    'content' => $content,
                    'analysis_options' => $analysisOptions,
                    'results' => [
                        'sentiment' => [
                            'score' => $document['sentiment'] ?? 'neutral',
                            'label' => $this->getSentimentLabel($document['confidenceScores'] ?? []),
                            'confidence' => $document['confidenceScores']['positive'] ?? 0.0,
                        ],
                        'entities' => array_map(fn ($entity) => [
                            'text' => $entity['text'] ?? '',
                            'category' => $entity['category'] ?? '',
                            'subcategory' => $entity['subcategory'] ?? '',
                            'confidence' => $entity['confidenceScore'] ?? 0.0,
                            'offset' => $entity['offset'] ?? 0,
                            'length' => $entity['length'] ?? 0,
                        ], $document['entities'] ?? []),
                        'key_phrases' => $document['keyPhrases'] ?? [],
                        'language' => [
                            'name' => $document['detectedLanguage']['name'] ?? '',
                            'iso6391_name' => $document['detectedLanguage']['iso6391Name'] ?? '',
                            'confidence' => $document['detectedLanguage']['confidenceScore'] ?? 0.0,
                        ],
                        'pii_entities' => array_map(fn ($entity) => [
                            'text' => $entity['text'] ?? '',
                            'category' => $entity['category'] ?? '',
                            'subcategory' => $entity['subcategory'] ?? '',
                            'confidence' => $entity['confidenceScore'] ?? 0.0,
                        ], $document['redactedText'] ?? []),
                    ],
                    'insights' => $this->generateInsights($document),
                    'recommendations' => $this->generateRecommendations($document),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => implode(', ', array_map(fn ($error) => $error['message'], $errors)),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'analysis' => [
                    'content' => $content,
                    'analysis_options' => $analysisOptions,
                    'results' => [
                        'sentiment' => [
                            'score' => 'neutral',
                            'label' => 'neutral',
                            'confidence' => 0.0,
                        ],
                        'entities' => [],
                        'key_phrases' => [],
                        'language' => [
                            'name' => '',
                            'iso6391_name' => '',
                            'confidence' => 0.0,
                        ],
                        'pii_entities' => [],
                    ],
                    'insights' => [],
                    'recommendations' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Translate text.
     *
     * @param string               $text           Text to translate
     * @param string               $targetLanguage Target language
     * @param string               $sourceLanguage Source language (optional)
     * @param array<string, mixed> $options        Translation options
     *
     * @return array{
     *     success: bool,
     *     translation: array{
     *         text: string,
     *         source_language: string,
     *         target_language: string,
     *         translated_text: string,
     *         confidence: float,
     *         alternatives: array<int, array{
     *             text: string,
     *             confidence: float,
     *         }>,
     *         detected_language: array{
     *             language: string,
     *             score: float,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function translate(
        string $text,
        string $targetLanguage = 'en',
        string $sourceLanguage = '',
        array $options = [],
    ): array {
        try {
            $requestData = [
                [
                    'text' => $text,
                ],
            ];

            $query = ['to' => $targetLanguage];
            if ($sourceLanguage) {
                $query['from'] = $sourceLanguage;
            }

            $response = $this->httpClient->request('POST', "{$this->endpoint}/translate?api-version=3.0", [
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
                    'confidence' => $translation['confidenceScore'] ?? 0.0,
                    'alternatives' => array_map(fn ($alt) => [
                        'text' => $alt['text'] ?? '',
                        'confidence' => $alt['confidenceScore'] ?? 0.0,
                    ], $translation['alternatives'] ?? []),
                    'detected_language' => [
                        'language' => $translation['detectedLanguage']['language'] ?? '',
                        'score' => $translation['detectedLanguage']['score'] ?? 0.0,
                    ],
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
                    'confidence' => 0.0,
                    'alternatives' => [],
                    'detected_language' => [
                        'language' => '',
                        'score' => 0.0,
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Summarize content.
     *
     * @param string               $content      Content to summarize
     * @param int                  $maxSentences Maximum number of sentences
     * @param string               $summaryType  Type of summary
     * @param array<string, mixed> $options      Summary options
     *
     * @return array{
     *     success: bool,
     *     summary: array{
     *         content: string,
     *         max_sentences: int,
     *         summary_type: string,
     *         summary_text: string,
     *         summary_sentences: array<int, string>,
     *         key_points: array<int, string>,
     *         confidence: float,
     *         compression_ratio: float,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function summarize(
        string $content,
        int $maxSentences = 3,
        string $summaryType = 'extractive',
        array $options = [],
    ): array {
        try {
            $requestData = [
                'documents' => [
                    [
                        'id' => '1',
                        'text' => $content,
                    ],
                ],
                'summary_type' => $summaryType,
                'max_sentences' => max(1, min($maxSentences, 10)),
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->endpoint}/text/analytics/v3.1/abstractive/summarize", [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $summary = $responseData['documents'][0]['summaries'][0] ?? [];

            return [
                'success' => true,
                'summary' => [
                    'content' => $content,
                    'max_sentences' => $maxSentences,
                    'summary_type' => $summaryType,
                    'summary_text' => $summary['text'] ?? '',
                    'summary_sentences' => explode('. ', $summary['text'] ?? ''),
                    'key_points' => $summary['keyPoints'] ?? [],
                    'confidence' => $summary['confidence'] ?? 0.0,
                    'compression_ratio' => $this->calculateCompressionRatio($content, $summary['text'] ?? ''),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'summary' => [
                    'content' => $content,
                    'max_sentences' => $maxSentences,
                    'summary_type' => $summaryType,
                    'summary_text' => '',
                    'summary_sentences' => [],
                    'key_points' => [],
                    'confidence' => 0.0,
                    'compression_ratio' => 0.0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract keywords.
     *
     * @param string               $content     Content to extract keywords from
     * @param int                  $maxKeywords Maximum number of keywords
     * @param array<string, mixed> $options     Extraction options
     *
     * @return array{
     *     success: bool,
     *     keywords: array{
     *         content: string,
     *         max_keywords: int,
     *         extracted_keywords: array<int, array{
     *             keyword: string,
     *             relevance_score: float,
     *             frequency: int,
     *             category: string,
     *         }>,
     *         total_keywords: int,
     *         extraction_confidence: float,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function extractKeywords(
        string $content,
        int $maxKeywords = 10,
        array $options = [],
    ): array {
        try {
            $requestData = [
                'documents' => [
                    [
                        'id' => '1',
                        'text' => $content,
                    ],
                ],
                'max_keywords' => max(1, min($maxKeywords, 50)),
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->endpoint}/text/analytics/v3.1/keyPhrases", [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $document = $responseData['documents'][0] ?? [];
            $keyPhrases = $document['keyPhrases'] ?? [];

            return [
                'success' => true,
                'keywords' => [
                    'content' => $content,
                    'max_keywords' => $maxKeywords,
                    'extracted_keywords' => array_map(fn ($phrase, $index) => [
                        'keyword' => $phrase,
                        'relevance_score' => 1.0 - ($index / \count($keyPhrases)),
                        'frequency' => substr_count(strtolower($content), strtolower($phrase)),
                        'category' => $this->categorizeKeyword($phrase),
                    ], \array_slice($keyPhrases, 0, $maxKeywords)),
                    'total_keywords' => \count($keyPhrases),
                    'extraction_confidence' => $document['confidence'] ?? 0.0,
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'keywords' => [
                    'content' => $content,
                    'max_keywords' => $maxKeywords,
                    'extracted_keywords' => [],
                    'total_keywords' => 0,
                    'extraction_confidence' => 0.0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze sentiment.
     *
     * @param string               $content Content to analyze
     * @param array<string, mixed> $options Sentiment analysis options
     *
     * @return array{
     *     success: bool,
     *     sentiment: array{
     *         content: string,
     *         sentiment_score: float,
     *         sentiment_label: string,
     *         confidence_scores: array{
     *             positive: float,
     *             neutral: float,
     *             negative: float,
     *         },
     *         sentence_sentiments: array<int, array{
     *             text: string,
     *             sentiment: string,
     *             confidence: float,
     *         }>,
     *         overall_sentiment: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function sentimentAnalysis(
        string $content,
        array $options = [],
    ): array {
        try {
            $requestData = [
                'documents' => [
                    [
                        'id' => '1',
                        'text' => $content,
                    ],
                ],
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->endpoint}/text/analytics/v3.1/sentiment", [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $document = $responseData['documents'][0] ?? [];
            $sentences = $document['sentences'] ?? [];

            return [
                'success' => true,
                'sentiment' => [
                    'content' => $content,
                    'sentiment_score' => $this->calculateSentimentScore($document['confidenceScores'] ?? []),
                    'sentiment_label' => $document['sentiment'] ?? 'neutral',
                    'confidence_scores' => [
                        'positive' => $document['confidenceScores']['positive'] ?? 0.0,
                        'neutral' => $document['confidenceScores']['neutral'] ?? 0.0,
                        'negative' => $document['confidenceScores']['negative'] ?? 0.0,
                    ],
                    'sentence_sentiments' => array_map(fn ($sentence) => [
                        'text' => $sentence['text'] ?? '',
                        'sentiment' => $sentence['sentiment'] ?? 'neutral',
                        'confidence' => $sentence['confidenceScores']['positive'] ?? 0.0,
                    ], $sentences),
                    'overall_sentiment' => $document['sentiment'] ?? 'neutral',
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'sentiment' => [
                    'content' => $content,
                    'sentiment_score' => 0.0,
                    'sentiment_label' => 'neutral',
                    'confidence_scores' => [
                        'positive' => 0.0,
                        'neutral' => 1.0,
                        'negative' => 0.0,
                    ],
                    'sentence_sentiments' => [],
                    'overall_sentiment' => 'neutral',
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Recognize entities.
     *
     * @param string               $content     Content to analyze
     * @param array<string, mixed> $entityTypes Entity types to recognize
     * @param array<string, mixed> $options     Recognition options
     *
     * @return array{
     *     success: bool,
     *     entities: array{
     *         content: string,
     *         entity_types: array<string, mixed>,
     *         recognized_entities: array<int, array{
     *             text: string,
     *             category: string,
     *             subcategory: string,
     *             confidence: float,
     *             offset: int,
     *             length: int,
     *             wikipedia_url: string,
     *         }>,
     *         entity_categories: array<string, int>,
     *         total_entities: int,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function entityRecognition(
        string $content,
        array $entityTypes = [],
        array $options = [],
    ): array {
        try {
            $requestData = [
                'documents' => [
                    [
                        'id' => '1',
                        'text' => $content,
                    ],
                ],
                'entity_types' => $entityTypes,
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->endpoint}/text/analytics/v3.1/entities/recognition/general", [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $document = $responseData['documents'][0] ?? [];
            $entities = $document['entities'] ?? [];

            return [
                'success' => true,
                'entities' => [
                    'content' => $content,
                    'entity_types' => $entityTypes,
                    'recognized_entities' => array_map(fn ($entity) => [
                        'text' => $entity['text'] ?? '',
                        'category' => $entity['category'] ?? '',
                        'subcategory' => $entity['subcategory'] ?? '',
                        'confidence' => $entity['confidenceScore'] ?? 0.0,
                        'offset' => $entity['offset'] ?? 0,
                        'length' => $entity['length'] ?? 0,
                        'wikipedia_url' => $entity['wikipediaUrl'] ?? '',
                    ], $entities),
                    'entity_categories' => $this->countEntityCategories($entities),
                    'total_entities' => \count($entities),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'entities' => [
                    'content' => $content,
                    'entity_types' => $entityTypes,
                    'recognized_entities' => [],
                    'entity_categories' => [],
                    'total_entities' => 0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Detect language.
     *
     * @param string               $content Content to analyze
     * @param array<string, mixed> $options Detection options
     *
     * @return array{
     *     success: bool,
     *     language_detection: array{
     *         content: string,
     *         detected_language: array{
     *             name: string,
     *             iso6391_name: string,
     *             confidence: float,
     *         },
     *         alternative_languages: array<int, array{
     *             name: string,
     *             iso6391_name: string,
     *             confidence: float,
     *         }>,
     *         detection_confidence: float,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function languageDetection(
        string $content,
        array $options = [],
    ): array {
        try {
            $requestData = [
                'documents' => [
                    [
                        'id' => '1',
                        'text' => $content,
                    ],
                ],
                'options' => $options,
            ];

            $response = $this->httpClient->request('POST', "{$this->endpoint}/text/analytics/v3.1/languages", [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $document = $responseData['documents'][0] ?? [];
            $detectedLanguage = $document['detectedLanguage'] ?? [];

            return [
                'success' => true,
                'language_detection' => [
                    'content' => $content,
                    'detected_language' => [
                        'name' => $detectedLanguage['name'] ?? '',
                        'iso6391_name' => $detectedLanguage['iso6391Name'] ?? '',
                        'confidence' => $detectedLanguage['confidenceScore'] ?? 0.0,
                    ],
                    'alternative_languages' => array_map(fn ($alt) => [
                        'name' => $alt['name'] ?? '',
                        'iso6391_name' => $alt['iso6391Name'] ?? '',
                        'confidence' => $alt['confidenceScore'] ?? 0.0,
                    ], $detectedLanguage['alternatives'] ?? []),
                    'detection_confidence' => $detectedLanguage['confidenceScore'] ?? 0.0,
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'language_detection' => [
                    'content' => $content,
                    'detected_language' => [
                        'name' => '',
                        'iso6391_name' => '',
                        'confidence' => 0.0,
                    ],
                    'alternative_languages' => [],
                    'detection_confidence' => 0.0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Perform comprehensive text analytics.
     *
     * @param string               $content          Content to analyze
     * @param array<string, mixed> $analyticsOptions Analytics options
     *
     * @return array{
     *     success: bool,
     *     text_analytics: array{
     *         content: string,
     *         analytics_options: array<string, mixed>,
     *         comprehensive_analysis: array{
     *             sentiment: array<string, mixed>,
     *             entities: array<int, array<string, mixed>>,
     *             key_phrases: array<int, string>,
     *             language: array<string, mixed>,
     *             pii_entities: array<int, array<string, mixed>>,
     *         },
     *         insights: array<int, string>,
     *         recommendations: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function textAnalytics(
        string $content,
        array $analyticsOptions = [],
    ): array {
        try {
            // Perform comprehensive analysis by combining multiple Azure AI services
            $sentimentResult = $this->sentimentAnalysis($content);
            $entitiesResult = $this->entityRecognition($content);
            $keywordsResult = $this->extractKeywords($content);
            $languageResult = $this->languageDetection($content);

            return [
                'success' => $sentimentResult['success'] && $entitiesResult['success'],
                'text_analytics' => [
                    'content' => $content,
                    'analytics_options' => $analyticsOptions,
                    'comprehensive_analysis' => [
                        'sentiment' => $sentimentResult['sentiment'],
                        'entities' => $entitiesResult['entities']['recognized_entities'],
                        'key_phrases' => $keywordsResult['keywords']['extracted_keywords'],
                        'language' => $languageResult['language_detection']['detected_language'],
                        'pii_entities' => [],
                    ],
                    'insights' => $this->generateComprehensiveInsights([
                        'sentiment' => $sentimentResult['sentiment'],
                        'entities' => $entitiesResult['entities'],
                        'keywords' => $keywordsResult['keywords'],
                        'language' => $languageResult['language_detection'],
                    ]),
                    'recommendations' => $this->generateComprehensiveRecommendations([
                        'sentiment' => $sentimentResult['sentiment'],
                        'entities' => $entitiesResult['entities'],
                        'keywords' => $keywordsResult['keywords'],
                        'language' => $languageResult['language_detection'],
                    ]),
                ],
                'processingTime' => max(
                    $sentimentResult['processingTime'],
                    $entitiesResult['processingTime'],
                    $keywordsResult['processingTime'],
                    $languageResult['processingTime']
                ),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'text_analytics' => [
                    'content' => $content,
                    'analytics_options' => $analyticsOptions,
                    'comprehensive_analysis' => [
                        'sentiment' => [],
                        'entities' => [],
                        'key_phrases' => [],
                        'language' => [],
                        'pii_entities' => [],
                    ],
                    'insights' => [],
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
    private function getSentimentLabel(array $confidenceScores): string
    {
        $maxScore = max($confidenceScores);

        return array_search($maxScore, $confidenceScores) ?: 'neutral';
    }

    private function calculateSentimentScore(array $confidenceScores): float
    {
        $positive = $confidenceScores['positive'] ?? 0.0;
        $negative = $confidenceScores['negative'] ?? 0.0;

        return $positive - $negative;
    }

    private function calculateCompressionRatio(string $original, string $summary): float
    {
        if (empty($original)) {
            return 0.0;
        }

        return \strlen($summary) / \strlen($original);
    }

    private function categorizeKeyword(string $keyword): string
    {
        $techKeywords = ['software', 'technology', 'computer', 'programming', 'development'];
        $businessKeywords = ['business', 'management', 'strategy', 'marketing', 'sales'];
        $scienceKeywords = ['research', 'study', 'analysis', 'data', 'science'];

        $keywordLower = strtolower($keyword);

        if (array_intersect(explode(' ', $keywordLower), $techKeywords)) {
            return 'technology';
        } elseif (array_intersect(explode(' ', $keywordLower), $businessKeywords)) {
            return 'business';
        } elseif (array_intersect(explode(' ', $keywordLower), $scienceKeywords)) {
            return 'science';
        }

        return 'general';
    }

    private function countEntityCategories(array $entities): array
    {
        $categories = [];
        foreach ($entities as $entity) {
            $category = $entity['category'] ?? 'unknown';
            $categories[$category] = ($categories[$category] ?? 0) + 1;
        }

        return $categories;
    }

    private function generateInsights(array $document): array
    {
        $insights = [];

        if (!empty($document['entities'])) {
            $insights[] = 'Content contains '.\count($document['entities']).' named entities';
        }

        if (!empty($document['keyPhrases'])) {
            $insights[] = 'Key phrases identified: '.implode(', ', \array_slice($document['keyPhrases'], 0, 3));
        }

        return $insights;
    }

    private function generateRecommendations(array $document): array
    {
        $recommendations = [];

        if (empty($document['keyPhrases'])) {
            $recommendations[] = 'Consider adding more specific keywords to improve content discoverability';
        }

        if (empty($document['entities'])) {
            $recommendations[] = 'Content could benefit from more specific named entities';
        }

        return $recommendations;
    }

    private function generateComprehensiveInsights(array $results): array
    {
        $insights = [];

        if ('neutral' !== $results['sentiment']['sentiment_label']) {
            $insights[] = 'Content has '.$results['sentiment']['sentiment_label'].' sentiment';
        }

        if (!empty($results['entities']['recognized_entities'])) {
            $insights[] = 'Identified '.$results['entities']['total_entities'].' entities';
        }

        return $insights;
    }

    private function generateComprehensiveRecommendations(array $results): array
    {
        $recommendations = [];

        if ('negative' === $results['sentiment']['sentiment_label']) {
            $recommendations[] = 'Consider revising content to improve sentiment';
        }

        if ($results['entities']['total_entities'] < 3) {
            $recommendations[] = 'Add more specific entities to enhance content richness';
        }

        return $recommendations;
    }
}

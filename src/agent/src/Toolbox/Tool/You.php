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
#[AsTool('you_search', 'Tool that performs AI-powered search using You.com')]
#[AsTool('you_chat', 'Tool that performs AI chat using You.com', method: 'chat')]
#[AsTool('you_summarize', 'Tool that summarizes content using You.com', method: 'summarize')]
#[AsTool('you_analyze', 'Tool that analyzes content using You.com', method: 'analyze')]
#[AsTool('you_translate', 'Tool that translates content using You.com', method: 'translate')]
#[AsTool('you_code', 'Tool that generates code using You.com', method: 'code')]
#[AsTool('you_writing', 'Tool that helps with writing using You.com', method: 'writing')]
#[AsTool('you_math', 'Tool that solves math problems using You.com', method: 'math')]
final readonly class You
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.you.com/v1',
        private array $options = [],
    ) {
    }

    /**
     * Perform AI-powered search using You.com.
     *
     * @param string               $query       Search query
     * @param array<string, mixed> $searchTypes Types of search (web, images, videos, news)
     * @param string               $language    Language for results
     * @param int                  $count       Number of results to return
     * @param string               $region      Region for search
     *
     * @return array{
     *     success: bool,
     *     search: array{
     *         query: string,
     *         results: array<int, array{
     *             title: string,
     *             url: string,
     *             snippet: string,
     *             source: string,
     *             published_date?: string,
     *             relevance_score: float,
     *         }>,
     *         total_results: int,
     *         search_time: float,
     *         ai_insights: array{
     *             summary: string,
     *             key_points: array<int, string>,
     *             related_topics: array<int, string>,
     *         },
     *     },
     *     language: string,
     *     region: string,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $query,
        array $searchTypes = ['web'],
        string $language = 'en',
        int $count = 10,
        string $region = 'us',
    ): array {
        try {
            $requestData = [
                'query' => $query,
                'search_types' => $searchTypes,
                'language' => $language,
                'count' => max(1, min($count, 50)),
                'region' => $region,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/search", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $search = $data['search'] ?? [];

            return [
                'success' => true,
                'search' => [
                    'query' => $query,
                    'results' => array_map(fn ($result) => [
                        'title' => $result['title'] ?? '',
                        'url' => $result['url'] ?? '',
                        'snippet' => $result['snippet'] ?? '',
                        'source' => $result['source'] ?? '',
                        'published_date' => $result['published_date'] ?? null,
                        'relevance_score' => $result['relevance_score'] ?? 0.0,
                    ], $search['results'] ?? []),
                    'total_results' => $search['total_results'] ?? 0,
                    'search_time' => $search['search_time'] ?? 0.0,
                    'ai_insights' => [
                        'summary' => $search['ai_insights']['summary'] ?? '',
                        'key_points' => $search['ai_insights']['key_points'] ?? [],
                        'related_topics' => $search['ai_insights']['related_topics'] ?? [],
                    ],
                ],
                'language' => $language,
                'region' => $region,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'search' => [
                    'query' => $query,
                    'results' => [],
                    'total_results' => 0,
                    'search_time' => 0.0,
                    'ai_insights' => [
                        'summary' => '',
                        'key_points' => [],
                        'related_topics' => [],
                    ],
                ],
                'language' => $language,
                'region' => $region,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Perform AI chat using You.com.
     *
     * @param string $message Chat message
     * @param array<int, array{
     *     role: string,
     *     content: string,
     * }> $conversationHistory Previous conversation history
     * @param string               $model      AI model to use
     * @param array<string, mixed> $parameters Additional parameters
     *
     * @return array{
     *     success: bool,
     *     chat: array{
     *         message: string,
     *         response: string,
     *         model: string,
     *         conversation_id: string,
     *         response_time: float,
     *         suggestions: array<int, string>,
     *         sources: array<int, array{
     *             title: string,
     *             url: string,
     *             snippet: string,
     *         }>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function chat(
        string $message,
        array $conversationHistory = [],
        string $model = 'you-chat',
        array $parameters = [],
    ): array {
        try {
            $requestData = [
                'message' => $message,
                'conversation_history' => $conversationHistory,
                'model' => $model,
                'parameters' => $parameters,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/chat", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $chat = $data['chat'] ?? [];

            return [
                'success' => true,
                'chat' => [
                    'message' => $message,
                    'response' => $chat['response'] ?? '',
                    'model' => $model,
                    'conversation_id' => $chat['conversation_id'] ?? '',
                    'response_time' => $chat['response_time'] ?? 0.0,
                    'suggestions' => $chat['suggestions'] ?? [],
                    'sources' => array_map(fn ($source) => [
                        'title' => $source['title'] ?? '',
                        'url' => $source['url'] ?? '',
                        'snippet' => $source['snippet'] ?? '',
                    ], $chat['sources'] ?? []),
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'chat' => [
                    'message' => $message,
                    'response' => '',
                    'model' => $model,
                    'conversation_id' => '',
                    'response_time' => 0.0,
                    'suggestions' => [],
                    'sources' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Summarize content using You.com.
     *
     * @param string $content     Content to summarize
     * @param string $contentType Type of content (text, url, document)
     * @param int    $maxLength   Maximum summary length
     * @param string $style       Summary style (brief, detailed, bulleted)
     * @param string $language    Language for summary
     *
     * @return array{
     *     success: bool,
     *     summary: array{
     *         original_content: string,
     *         summary: string,
     *         length: int,
     *         style: string,
     *         key_points: array<int, string>,
     *         keywords: array<int, string>,
     *         confidence: float,
     *         compression_ratio: float,
     *     },
     *     contentType: string,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function summarize(
        string $content,
        string $contentType = 'text',
        int $maxLength = 200,
        string $style = 'brief',
        string $language = 'en',
    ): array {
        try {
            $requestData = [
                'content' => $content,
                'content_type' => $contentType,
                'max_length' => max(50, min($maxLength, 1000)),
                'style' => $style,
                'language' => $language,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/summarize", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $summary = $data['summary'] ?? [];
            $originalLength = \strlen($content);

            return [
                'success' => true,
                'summary' => [
                    'original_content' => $content,
                    'summary' => $summary['summary'] ?? '',
                    'length' => $summary['length'] ?? 0,
                    'style' => $summary['style'] ?? $style,
                    'key_points' => $summary['key_points'] ?? [],
                    'keywords' => $summary['keywords'] ?? [],
                    'confidence' => $summary['confidence'] ?? 0.0,
                    'compression_ratio' => $originalLength > 0 ? ($summary['length'] ?? 0) / $originalLength : 0.0,
                ],
                'contentType' => $contentType,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'summary' => [
                    'original_content' => $content,
                    'summary' => '',
                    'length' => 0,
                    'style' => $style,
                    'key_points' => [],
                    'keywords' => [],
                    'confidence' => 0.0,
                    'compression_ratio' => 0.0,
                ],
                'contentType' => $contentType,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze content using You.com.
     *
     * @param string               $content       Content to analyze
     * @param string               $contentType   Type of content (text, url, document)
     * @param array<string, mixed> $analysisTypes Types of analysis to perform
     * @param string               $language      Language for analysis
     *
     * @return array{
     *     success: bool,
     *     analysis: array{
     *         content: string,
     *         sentiment: array{
     *             score: float,
     *             label: string,
     *             confidence: float,
     *         },
     *         topics: array<int, string>,
     *         keywords: array<int, string>,
     *         entities: array<int, array{
     *             text: string,
     *             type: string,
     *             confidence: float,
     *         }>,
     *         readability: array{
     *             score: float,
     *             level: string,
     *             grade_level: int,
     *         },
     *         tone: array{
     *             score: float,
     *             label: string,
     *             confidence: float,
     *         },
     *         insights: array<int, string>,
     *     },
     *     contentType: string,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function analyze(
        string $content,
        string $contentType = 'text',
        array $analysisTypes = ['sentiment', 'topics', 'keywords', 'entities', 'readability', 'tone'],
        string $language = 'en',
    ): array {
        try {
            $requestData = [
                'content' => $content,
                'content_type' => $contentType,
                'analysis_types' => $analysisTypes,
                'language' => $language,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/analyze", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $analysis = $data['analysis'] ?? [];

            return [
                'success' => true,
                'analysis' => [
                    'content' => $content,
                    'sentiment' => [
                        'score' => $analysis['sentiment']['score'] ?? 0.0,
                        'label' => $analysis['sentiment']['label'] ?? 'neutral',
                        'confidence' => $analysis['sentiment']['confidence'] ?? 0.0,
                    ],
                    'topics' => $analysis['topics'] ?? [],
                    'keywords' => $analysis['keywords'] ?? [],
                    'entities' => array_map(fn ($entity) => [
                        'text' => $entity['text'] ?? '',
                        'type' => $entity['type'] ?? '',
                        'confidence' => $entity['confidence'] ?? 0.0,
                    ], $analysis['entities'] ?? []),
                    'readability' => [
                        'score' => $analysis['readability']['score'] ?? 0.0,
                        'level' => $analysis['readability']['level'] ?? 'intermediate',
                        'grade_level' => $analysis['readability']['grade_level'] ?? 8,
                    ],
                    'tone' => [
                        'score' => $analysis['tone']['score'] ?? 0.0,
                        'label' => $analysis['tone']['label'] ?? 'neutral',
                        'confidence' => $analysis['tone']['confidence'] ?? 0.0,
                    ],
                    'insights' => $analysis['insights'] ?? [],
                ],
                'contentType' => $contentType,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'analysis' => [
                    'content' => $content,
                    'sentiment' => ['score' => 0.0, 'label' => 'neutral', 'confidence' => 0.0],
                    'topics' => [],
                    'keywords' => [],
                    'entities' => [],
                    'readability' => ['score' => 0.0, 'level' => 'intermediate', 'grade_level' => 8],
                    'tone' => ['score' => 0.0, 'label' => 'neutral', 'confidence' => 0.0],
                    'insights' => [],
                ],
                'contentType' => $contentType,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Translate content using You.com.
     *
     * @param string $content        Content to translate
     * @param string $targetLanguage Target language code
     * @param string $sourceLanguage Source language code (auto-detect if empty)
     * @param string $contentType    Type of content (text, url, document)
     *
     * @return array{
     *     success: bool,
     *     translation: array{
     *         original_text: string,
     *         translated_text: string,
     *         source_language: string,
     *         target_language: string,
     *         confidence: float,
     *         word_count: int,
     *         character_count: int,
     *     },
     *     contentType: string,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function translate(
        string $content,
        string $targetLanguage,
        string $sourceLanguage = '',
        string $contentType = 'text',
    ): array {
        try {
            $requestData = [
                'content' => $content,
                'target_language' => $targetLanguage,
                'content_type' => $contentType,
            ];

            if ($sourceLanguage) {
                $requestData['source_language'] = $sourceLanguage;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/translate", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $translation = $data['translation'] ?? [];

            return [
                'success' => true,
                'translation' => [
                    'original_text' => $content,
                    'translated_text' => $translation['translated_text'] ?? '',
                    'source_language' => $translation['source_language'] ?? $sourceLanguage,
                    'target_language' => $targetLanguage,
                    'confidence' => $translation['confidence'] ?? 0.0,
                    'word_count' => $translation['word_count'] ?? 0,
                    'character_count' => $translation['character_count'] ?? 0,
                ],
                'contentType' => $contentType,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'translation' => [
                    'original_text' => $content,
                    'translated_text' => '',
                    'source_language' => $sourceLanguage,
                    'target_language' => $targetLanguage,
                    'confidence' => 0.0,
                    'word_count' => 0,
                    'character_count' => 0,
                ],
                'contentType' => $contentType,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate code using You.com.
     *
     * @param string               $prompt   Code generation prompt
     * @param string               $language Programming language
     * @param string               $style    Code style (clean, documented, optimized)
     * @param array<string, mixed> $examples Code examples for context
     *
     * @return array{
     *     success: bool,
     *     code: array{
     *         prompt: string,
     *         generated_code: string,
     *         language: string,
     *         style: string,
     *         explanation: string,
     *         complexity: string,
     *         best_practices: array<int, string>,
     *         examples_used: int,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function code(
        string $prompt,
        string $language,
        string $style = 'clean',
        array $examples = [],
    ): array {
        try {
            $requestData = [
                'prompt' => $prompt,
                'language' => $language,
                'style' => $style,
                'examples' => $examples,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/code", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $code = $data['code'] ?? [];

            return [
                'success' => true,
                'code' => [
                    'prompt' => $prompt,
                    'generated_code' => $code['generated_code'] ?? '',
                    'language' => $language,
                    'style' => $code['style'] ?? $style,
                    'explanation' => $code['explanation'] ?? '',
                    'complexity' => $code['complexity'] ?? 'medium',
                    'best_practices' => $code['best_practices'] ?? [],
                    'examples_used' => \count($examples),
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'code' => [
                    'prompt' => $prompt,
                    'generated_code' => '',
                    'language' => $language,
                    'style' => $style,
                    'explanation' => '',
                    'complexity' => 'medium',
                    'best_practices' => [],
                    'examples_used' => \count($examples),
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Help with writing using You.com.
     *
     * @param string $content     Content to improve
     * @param string $writingType Type of writing (essay, email, report, creative)
     * @param string $tone        Writing tone (formal, casual, persuasive, informative)
     * @param string $purpose     Writing purpose
     * @param string $audience    Target audience
     *
     * @return array{
     *     success: bool,
     *     writing: array{
     *         original_content: string,
     *         improved_content: string,
     *         writing_type: string,
     *         tone: string,
     *         purpose: string,
     *         audience: string,
     *         improvements: array<int, string>,
     *         suggestions: array<int, string>,
     *         word_count: int,
     *         readability_score: float,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function writing(
        string $content,
        string $writingType = 'general',
        string $tone = 'neutral',
        string $purpose = 'inform',
        string $audience = 'general',
    ): array {
        try {
            $requestData = [
                'content' => $content,
                'writing_type' => $writingType,
                'tone' => $tone,
                'purpose' => $purpose,
                'audience' => $audience,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/writing", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $writing = $data['writing'] ?? [];

            return [
                'success' => true,
                'writing' => [
                    'original_content' => $content,
                    'improved_content' => $writing['improved_content'] ?? '',
                    'writing_type' => $writingType,
                    'tone' => $tone,
                    'purpose' => $purpose,
                    'audience' => $audience,
                    'improvements' => $writing['improvements'] ?? [],
                    'suggestions' => $writing['suggestions'] ?? [],
                    'word_count' => $writing['word_count'] ?? 0,
                    'readability_score' => $writing['readability_score'] ?? 0.0,
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'writing' => [
                    'original_content' => $content,
                    'improved_content' => '',
                    'writing_type' => $writingType,
                    'tone' => $tone,
                    'purpose' => $purpose,
                    'audience' => $audience,
                    'improvements' => [],
                    'suggestions' => [],
                    'word_count' => 0,
                    'readability_score' => 0.0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Solve math problems using You.com.
     *
     * @param string $problem    Math problem to solve
     * @param string $subject    Math subject (algebra, calculus, geometry, statistics)
     * @param string $difficulty Problem difficulty (easy, medium, hard)
     * @param bool   $showSteps  Whether to show solution steps
     *
     * @return array{
     *     success: bool,
     *     math: array{
     *         problem: string,
     *         solution: string,
     *         answer: string,
     *         subject: string,
     *         difficulty: string,
     *         steps: array<int, array{
     *             step: int,
     *             description: string,
     *             calculation: string,
     *             result: string,
     *         }>,
     *         explanation: string,
     *         related_concepts: array<int, string>,
     *         confidence: float,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function math(
        string $problem,
        string $subject = 'general',
        string $difficulty = 'medium',
        bool $showSteps = true,
    ): array {
        try {
            $requestData = [
                'problem' => $problem,
                'subject' => $subject,
                'difficulty' => $difficulty,
                'show_steps' => $showSteps,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/math", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $math = $data['math'] ?? [];

            return [
                'success' => true,
                'math' => [
                    'problem' => $problem,
                    'solution' => $math['solution'] ?? '',
                    'answer' => $math['answer'] ?? '',
                    'subject' => $subject,
                    'difficulty' => $difficulty,
                    'steps' => array_map(fn ($step) => [
                        'step' => $step['step'] ?? 0,
                        'description' => $step['description'] ?? '',
                        'calculation' => $step['calculation'] ?? '',
                        'result' => $step['result'] ?? '',
                    ], $math['steps'] ?? []),
                    'explanation' => $math['explanation'] ?? '',
                    'related_concepts' => $math['related_concepts'] ?? [],
                    'confidence' => $math['confidence'] ?? 0.0,
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'math' => [
                    'problem' => $problem,
                    'solution' => '',
                    'answer' => '',
                    'subject' => $subject,
                    'difficulty' => $difficulty,
                    'steps' => [],
                    'explanation' => '',
                    'related_concepts' => [],
                    'confidence' => 0.0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}

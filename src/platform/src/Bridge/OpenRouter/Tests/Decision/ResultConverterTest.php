<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Tests\Decision;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decision\Answer\Answers;
use Symfony\AI\Platform\Bridge\Decision\TokenUsageExtractor;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\OpenRouter\Decision\ResultConverter;
use Symfony\AI\Platform\Bridge\OpenRouter\DecisionModel;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class ResultConverterTest extends TestCase
{
    public function testItSupportsOnlyDecisionModels()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new DecisionModel('typesafe/jev-1.13')));
        $this->assertFalse($converter->supports(new CompletionsModel('openrouter/auto')));
    }

    public function testItConvertsTheAnswers()
    {
        $result = (new ResultConverter())->convert($this->createRawResult($this->createResponseData()));

        $answers = $result->getContent();
        $this->assertInstanceOf(Answers::class, $answers);
        $this->assertSame(0.96, $answers->getNoul('is_bug')->getProbability());
        $this->assertSame('technical', $answers->getChoice('department')->getChoice());
        $this->assertSame(0.88, $answers->getChoice('department')->getConfidence());
        $this->assertSame(['billing' => 0.08, 'technical' => 0.92], $answers->getChoice('department')->getProbabilities());
        $this->assertSame(1.4, $answers->getScore('frustration')->getScore());
        $this->assertSame([0 => 'Calm', 1 => 'Frustrated', 2 => 'Angry'], $answers->getScore('frustration')->getLegend());
    }

    public function testItExtractsTheTokenUsage()
    {
        $converter = new ResultConverter();
        $extractor = $converter->getTokenUsageExtractor();

        $this->assertInstanceOf(TokenUsageExtractor::class, $extractor);

        $tokenUsage = $extractor->extract($this->createRawResult($this->createResponseData()));
        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(100, $tokenUsage->getPromptTokens());
        $this->assertSame(10, $tokenUsage->getCompletionTokens());
    }

    public function testItReportsBadRequestsWithTheOpenRouterErrorMessage()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('questions.is_bug.criteria must contain "true" and "false"');

        (new ResultConverter())->convert($this->createRawResult(['error' => ['code' => 400, 'message' => 'questions.is_bug.criteria must contain "true" and "false"']], 400));
    }

    public function testItReportsAuthenticationFailures()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No auth credentials found');

        (new ResultConverter())->convert($this->createRawResult(['error' => ['code' => 401, 'message' => 'No auth credentials found']], 401));
    }

    public function testItReportsRateLimits()
    {
        $this->expectException(RateLimitExceededException::class);

        (new ResultConverter())->convert($this->createRawResult(['error' => ['code' => 429, 'message' => 'Rate limit exceeded']], 429));
    }

    public function testItReportsServerErrors()
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Provider returned error');

        (new ResultConverter())->convert($this->createRawResult(['error' => ['code' => 502, 'message' => 'Provider returned error']], 502));
    }

    public function testItReportsInsufficientCredits()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 402: "Insufficient credits"');

        (new ResultConverter())->convert($this->createRawResult(['error' => ['code' => 402, 'message' => 'Insufficient credits']], 402));
    }

    /**
     * @return array<string, mixed>
     */
    private function createResponseData(): array
    {
        return [
            'id' => 'gen-dec-123',
            'model' => 'typesafe/jev-1.13-20260917',
            'provider' => 'TypeSafe',
            'answers' => [
                'is_bug' => ['type' => 'noul', 'noul' => 0.96],
                'department' => [
                    'type' => 'choice',
                    'choice' => 'technical',
                    'confidence' => 0.88,
                    'probabilities' => ['billing' => 0.08, 'technical' => 0.92],
                ],
                'frustration' => [
                    'type' => 'score',
                    'score' => 1.4,
                    'confidence' => 0.7,
                    'probabilities' => ['0' => 0.1, '1' => 0.4, '2' => 0.5],
                    'legend' => ['0' => 'Calm', '1' => 'Frustrated', '2' => 'Angry'],
                ],
            ],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 10, 'cost' => 0.00002],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createRawResult(array $data, int $statusCode = 200): RawHttpResult
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($data, ['http_code' => $statusCode]));

        return new RawHttpResult($httpClient->request('POST', 'https://openrouter.ai/api/alpha/decisions'));
    }
}

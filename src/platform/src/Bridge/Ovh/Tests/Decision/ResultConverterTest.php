<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\Tests\Decision;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decision\Answer\Answers;
use Symfony\AI\Platform\Bridge\Decision\TokenUsageExtractor;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Ovh\Decision\ResultConverter;
use Symfony\AI\Platform\Bridge\Ovh\DecisionModel;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ResultConverterTest extends TestCase
{
    public function testItSupportsOnlyDecisionModels()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new DecisionModel('clef-flash')));
        $this->assertFalse($converter->supports(new CompletionsModel('gpt-oss-120b')));
    }

    public function testItConvertsTheAnswers()
    {
        $answers = (new ResultConverter())->convert($this->createRawResult($this->createResponseData()))->getContent();

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
        $extractor = (new ResultConverter())->getTokenUsageExtractor();

        $this->assertInstanceOf(TokenUsageExtractor::class, $extractor);

        $tokenUsage = $extractor->extract($this->createRawResult($this->createResponseData()));
        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(100, $tokenUsage->getPromptTokens());
        $this->assertSame(10, $tokenUsage->getCompletionTokens());
    }

    public function testItReportsAuthenticationFailures()
    {
        $this->expectException(AuthenticationException::class);

        (new ResultConverter())->convert($this->createRawResult(['error' => 'Unauthorized'], 401));
    }

    public function testItReportsServerErrors()
    {
        $this->expectException(ServerException::class);

        (new ResultConverter())->convert($this->createRawResult(['error' => 'Bad gateway'], 502));
    }

    public function testItReportsUnexpectedStatusCodes()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 204');

        (new ResultConverter())->convert($this->createRawResult([], 204));
    }

    /**
     * @return array<string, mixed>
     */
    private function createResponseData(): array
    {
        return [
            'model' => 'clef-flash',
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
            'usage' => ['input_tokens' => 100, 'output_tokens' => 10],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createRawResult(array $data, int $statusCode = 200): RawHttpResult
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($data, ['http_code' => $statusCode]));

        return new RawHttpResult($httpClient->request('POST', 'https://oai.endpoints.kepler.ai.cloud.ovh.net/api/v1/systemone'));
    }
}

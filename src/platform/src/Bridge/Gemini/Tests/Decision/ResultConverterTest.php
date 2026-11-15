<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Decision;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Decision\ResultConverter;
use Symfony\AI\Platform\Decision\Answer\Answers;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ResultConverterTest extends TestCase
{
    public function testItConvertsTheAnswers()
    {
        $result = (new ResultConverter())->convert($this->createRawResult([
            'answers' => [
                'is_urgent' => ['type' => 'noul', 'noul' => 0.99, 'confidence' => 0.99],
                'department' => ['type' => 'choice', 'choice' => 'technical', 'probabilities' => ['billing' => 0.08, 'technical' => 0.92], 'confidence' => 0.92],
                'frustration' => ['type' => 'score', 'score' => 1.12, 'legend' => ['0' => 'Calm', '1' => 'Angry'], 'probabilities' => ['0' => 0.03, '1' => 0.97], 'confidence' => 0.97],
            ],
        ]));

        $answers = $result->getContent();
        $this->assertInstanceOf(Answers::class, $answers);
        $this->assertSame(0.99, $answers->getNoul('is_urgent')->getProbability());
        $this->assertSame('technical', $answers->getChoice('department')->getChoice());
        $this->assertSame([0 => 'Calm', 1 => 'Angry'], $answers->getScore('frustration')->getLegend());
    }

    public function testItExtractsTheTokenUsage()
    {
        $tokenUsage = (new ResultConverter())->getTokenUsageExtractor()->extract($this->createRawResult([
            'answers' => [],
            'usage' => ['total_input_tokens' => 100, 'input_tokens_by_modality' => [['modality' => 'text', 'tokens' => 100]], 'total_output_tokens' => 0, 'total_tokens' => 100],
        ]));

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(100, $tokenUsage->getPromptTokens());
        $this->assertSame(0, $tokenUsage->getCompletionTokens());
        $this->assertSame(100, $tokenUsage->getTotalTokens());
    }

    public function testItThrowsOnBlockedInput()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('The input was blocked for safety reasons.');

        (new ResultConverter())->convert($this->createRawResult(['error' => ['code' => 'safety', 'message' => 'The input was blocked for safety reasons.']], 400));
    }

    public function testItThrowsOnDepletedCredits()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 402: "Your prepayment credits are depleted."');

        (new ResultConverter())->convert($this->createRawResult(['error' => ['code' => 402, 'message' => 'Your prepayment credits are depleted.', 'status' => 'RESOURCE_EXHAUSTED']], 402));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function createRawResult(array $body, int $status = 200): RawHttpResult
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($body, ['http_code' => $status]));

        return new RawHttpResult($httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/decisions'));
    }
}

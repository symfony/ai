<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Decision;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Decision\ResultConverter;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\ResultConverter as GptResultConverter;
use Symfony\AI\Platform\Decision\Answer\Answers;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ResultConverterTest extends TestCase
{
    private const RESPONSE = [
        'model' => 'gpt-6-luna',
        'answers' => [
            ['type' => 'choice', 'name' => 'department', 'choice' => 'technical', 'probabilities' => [['value' => 'billing', 'probability' => 0.02], ['value' => 'technical', 'probability' => 0.98]], 'confidence' => 0.96],
            ['type' => 'score', 'name' => 'frustration', 'score' => 1.05, 'probabilities' => [['value' => 0, 'label' => 'Calm', 'probability' => 0.0], ['value' => 1, 'label' => 'Frustrated', 'probability' => 0.95], ['value' => 2, 'label' => 'Very angry', 'probability' => 0.05]], 'confidence' => 0.93],
            ['type' => 'predicate', 'name' => 'is_urgent', 'probability' => 0.85],
        ],
        'usage' => ['input_tokens' => 393, 'input_tokens_details' => ['cached_tokens' => 0, 'cache_write_tokens' => 0], 'output_tokens' => 0, 'output_tokens_details' => ['reasoning_tokens' => 0], 'total_tokens' => 393],
    ];

    public function testItConvertsTheAnswersKeyedByName()
    {
        $answers = (new ResultConverter())->convert($this->createRawResult(self::RESPONSE))->getContent();

        $this->assertInstanceOf(Answers::class, $answers);
        $this->assertSame(['department', 'frustration', 'is_urgent'], array_keys($answers->all()));

        $department = $answers->getChoice('department');
        $this->assertSame('technical', $department->getChoice());
        $this->assertSame(['billing' => 0.02, 'technical' => 0.98], $department->getProbabilities());
        $this->assertSame(0.96, $department->getConfidence());

        $frustration = $answers->getScore('frustration');
        $this->assertSame(1.05, $frustration->getScore());
        $this->assertSame([0 => 'Calm', 1 => 'Frustrated', 2 => 'Very angry'], $frustration->getLegend());
        $this->assertSame([0 => 0.0, 1 => 0.95, 2 => 0.05], $frustration->getProbabilities());
        $this->assertSame(0.93, $frustration->getConfidence());

        $this->assertSame(0.85, $answers->getNoul('is_urgent')->getProbability());
    }

    public function testItExtractsTheTokenUsage()
    {
        $tokenUsage = (new GptResultConverter())->getTokenUsageExtractor()->extract($this->createRawResult(self::RESPONSE));

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(393, $tokenUsage->getPromptTokens());
        $this->assertSame(0, $tokenUsage->getCachedTokens());
        $this->assertSame(393, $tokenUsage->getTotalTokens());
    }

    public function testItThrowsOnRefusals()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The model refused to answer the question "is_harmful".');

        (new ResultConverter())->convert($this->createRawResult(['answers' => [['type' => 'refusal', 'name' => 'is_harmful']]]));
    }

    public function testItRejectsAnIncompleteAnswer()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Answer "department" of type "choice" is missing the "probabilities" key.');

        (new ResultConverter())->convert($this->createRawResult(['answers' => [['type' => 'choice', 'name' => 'department', 'choice' => 'billing', 'confidence' => 0.5]]]));
    }

    public function testItRejectsAScoreProbabilityWithANonIntegerValue()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Answer "frustration" of type "score" has a probability with an invalid value.');

        (new ResultConverter())->convert($this->createRawResult(['answers' => [['type' => 'score', 'name' => 'frustration', 'score' => 1.0, 'probabilities' => [['value' => 1.5, 'label' => 'Frustrated', 'probability' => 1.0]], 'confidence' => 1.0]]]));
    }

    public function testItThrowsOnInvalidRequests()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage("Unknown parameter: 'questions[0].criteria'.");

        (new ResultConverter())->convert($this->createRawResult(['error' => ['message' => "Unknown parameter: 'questions[0].criteria'.", 'type' => 'invalid_request_error']], 400));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function createRawResult(array $body, int $status = 200): RawHttpResult
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($body, ['http_code' => $status]));

        return new RawHttpResult($httpClient->request('POST', 'https://api.openai.com/v1/decisions'));
    }
}

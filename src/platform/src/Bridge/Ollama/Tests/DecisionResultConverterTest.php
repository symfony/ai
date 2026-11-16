<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decision\Answer\Answers;
use Symfony\AI\Platform\Bridge\Decision\TokenUsageExtractor;
use Symfony\AI\Platform\Bridge\Ollama\Decision;
use Symfony\AI\Platform\Bridge\Ollama\DecisionResultConverter;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class DecisionResultConverterTest extends TestCase
{
    public function testItSupportsOnlyDecisionModels()
    {
        $converter = new DecisionResultConverter();

        $this->assertTrue($converter->supports(new Decision('nimble')));
        $this->assertFalse($converter->supports(new Ollama('llama3')));
    }

    public function testItConvertsTheDocumentedResponse()
    {
        // Response examples of https://github.com/ollama/ollama/blob/main/docs/capabilities/decision.mdx
        $result = (new DecisionResultConverter())->convert($this->createRawResult([
            'model' => 'nimble',
            'answers' => [
                'label' => [
                    'type' => 'choice',
                    'choice' => 'bug',
                    'probabilities' => ['billing' => 0.0125, 'bug' => 0.9781, 'account' => 0.0093],
                    'confidence' => 0.8906,
                ],
                'refund' => ['type' => 'noul', 'noul' => 0.9989],
                'urgency' => [
                    'type' => 'score',
                    'score' => 0.8308,
                    'legend' => ['0' => 'Routine: no time pressure', '1' => 'Soon: a customer is inconvenienced', '2' => 'Immediate: a critical service is unavailable'],
                    'probabilities' => ['0' => 0.2, '1' => 0.5, '2' => 0.3],
                    'confidence' => 0.4,
                ],
            ],
            'usage' => ['input_tokens' => 174, 'output_tokens' => 1],
        ]));

        $answers = $result->getContent();
        $this->assertInstanceOf(Answers::class, $answers);
        $this->assertSame('bug', $answers->getChoice('label')->getChoice());
        $this->assertSame(0.8906, $answers->getChoice('label')->getConfidence());
        $this->assertSame(0.9989, $answers->getNoul('refund')->getProbability());
        $this->assertSame(0.8308, $answers->getScore('urgency')->getScore());
    }

    public function testItReturnsTheTokenUsageExtractor()
    {
        $this->assertInstanceOf(TokenUsageExtractor::class, (new DecisionResultConverter())->getTokenUsageExtractor());
    }

    public function testItReportsBadRequestsWithTheOllamaErrorMessage()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('criteria must contain 2–26 candidates');

        (new DecisionResultConverter())->convert($this->createRawResult(['error' => 'question "label": criteria must contain 2–26 candidates'], 400));
    }

    public function testItReportsMissingModels()
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('model "nimble" not found, try pulling it first');

        (new DecisionResultConverter())->convert($this->createRawResult(['error' => 'model "nimble" not found, try pulling it first'], 404));
    }

    public function testItReportsAuthenticationFailures()
    {
        $this->expectException(AuthenticationException::class);

        (new DecisionResultConverter())->convert($this->createRawResult(['error' => 'unauthorized'], 401));
    }

    public function testItReportsServerErrors()
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('scoring failed');

        (new DecisionResultConverter())->convert($this->createRawResult(['error' => 'scoring failed'], 500));
    }

    public function testItReportsTooLargeRequests()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 413: "request body must not exceed 64 KiB without images"');

        (new DecisionResultConverter())->convert($this->createRawResult(['error' => 'request body must not exceed 64 KiB without images'], 413));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createRawResult(array $data, int $statusCode = 200): RawHttpResult
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($data, ['http_code' => $statusCode]));

        return new RawHttpResult($httpClient->request('POST', 'http://127.0.0.1:11434/v1/systemone'));
    }
}

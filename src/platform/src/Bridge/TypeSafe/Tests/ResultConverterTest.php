<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TypeSafe\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\Answers;
use Symfony\AI\Platform\Bridge\TypeSafe\Jev;
use Symfony\AI\Platform\Bridge\TypeSafe\ResultConverter;
use Symfony\AI\Platform\Bridge\TypeSafe\TokenUsageExtractor;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ResultConverterTest extends TestCase
{
    public function testItSupportsJevModel()
    {
        $this->assertTrue((new ResultConverter())->supports(new Jev('jev-latest')));
    }

    public function testItDoesNotSupportOtherModels()
    {
        $this->assertFalse((new ResultConverter())->supports(new Model('any-model')));
    }

    public function testItConvertsAnswers()
    {
        $result = (new ResultConverter())->convert($this->createRawResult([
            'model' => 'jev-1.13.0',
            'answers' => [
                'is_urgent' => ['type' => 'noul', 'noul' => 0.92],
                'department' => ['type' => 'choice', 'choice' => 'technical', 'probabilities' => ['billing' => 0.08, 'technical' => 0.85, 'sales' => 0.07], 'confidence' => 0.82],
                'frustration' => ['type' => 'score', 'score' => 1.6, 'legend' => ['0' => 'Calm', '1' => 'Frustrated', '2' => 'Very angry'], 'probabilities' => ['0' => 0.05, '1' => 0.3, '2' => 0.65], 'confidence' => 0.78],
            ],
            'usage' => ['input_tokens' => 312, 'output_tokens' => 48],
        ]));

        $answers = $result->getContent();
        $this->assertInstanceOf(Answers::class, $answers);

        $this->assertSame(0.92, $answers->getNoul('is_urgent')->getProbability());

        $this->assertSame('technical', $answers->getChoice('department')->getChoice());
        $this->assertSame(['billing' => 0.08, 'technical' => 0.85, 'sales' => 0.07], $answers->getChoice('department')->getProbabilities());
        $this->assertSame(0.82, $answers->getChoice('department')->getConfidence());

        $this->assertSame(1.6, $answers->getScore('frustration')->getScore());
        $this->assertSame(['Calm', 'Frustrated', 'Very angry'], $answers->getScore('frustration')->getLegend());
        $this->assertSame([0.05, 0.3, 0.65], $answers->getScore('frustration')->getProbabilities());
        $this->assertSame(0.78, $answers->getScore('frustration')->getConfidence());
    }

    public function testItConvertsIntegerNumbersToFloats()
    {
        $result = (new ResultConverter())->convert($this->createRawResult([
            'answers' => [
                'department' => ['type' => 'choice', 'choice' => 'billing', 'probabilities' => ['billing' => 1, 'sales' => 0], 'confidence' => 1],
            ],
        ]));

        $answers = $result->getContent();
        $this->assertInstanceOf(Answers::class, $answers);
        $this->assertSame(['billing' => 1.0, 'sales' => 0.0], $answers->getChoice('department')->getProbabilities());
        $this->assertSame(1.0, $answers->getChoice('department')->getConfidence());
    }

    public function testItConvertsScoreWithoutProbabilities()
    {
        $result = (new ResultConverter())->convert($this->createRawResult([
            'answers' => [
                'frustration' => ['type' => 'score', 'score' => 1.035, 'legend' => ['0' => 'Calm', '1' => 'Frustrated'], 'confidence' => 0.842],
            ],
        ]));

        $answers = $result->getContent();
        $this->assertInstanceOf(Answers::class, $answers);
        $this->assertSame([], $answers->getScore('frustration')->getProbabilities());
    }

    public function testItThrowsExceptionForMissingAnswers()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain answers.');

        (new ResultConverter())->convert($this->createRawResult(['model' => 'jev-1.13.0']));
    }

    public function testItThrowsExceptionForUnsupportedAnswerType()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Answer "is_urgent" has an unsupported type "boolean".');

        (new ResultConverter())->convert($this->createRawResult(['answers' => ['is_urgent' => ['type' => 'boolean']]]));
    }

    public function testItThrowsAuthenticationExceptionOnUnauthorized()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Cannot authenticate with the server. Please check your API key and try again.');

        (new ResultConverter())->convert($this->createRawResult([
            'detail' => ['error_type' => 'authentication_error', 'message' => 'Cannot authenticate with the server. Please check your API key and try again.'],
        ], 401));
    }

    public function testItThrowsBadRequestExceptionOnValidationError()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('body.questions: Field required; body.state: Field required');

        (new ResultConverter())->convert($this->createRawResult(['detail' => [
            ['type' => 'missing', 'loc' => ['body', 'questions'], 'msg' => 'Field required', 'input' => []],
            ['type' => 'missing', 'loc' => ['body', 'state'], 'msg' => 'Field required', 'input' => []],
        ]], 422));
    }

    public function testItThrowsBadRequestExceptionOnUnknownModel()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Unknown model: jev');

        (new ResultConverter())->convert($this->createRawResult(['detail' => ['error_type' => 'api_usage_error', 'message' => 'Unknown model: jev']], 400));
    }

    public function testItThrowsBadRequestExceptionWithPlainDetail()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Choice question must have at least one choice: department');

        (new ResultConverter())->convert($this->createRawResult(['detail' => 'Choice question must have at least one choice: department'], 400));
    }

    public function testItThrowsExceptionForMissingAnswerKey()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Answer "department" of type "choice" is missing the "confidence" key.');

        (new ResultConverter())->convert($this->createRawResult(['answers' => [
            'department' => ['type' => 'choice', 'choice' => 'billing', 'probabilities' => ['billing' => 1.0], 'confidence' => null],
        ]]));
    }

    public function testItThrowsRateLimitExceededExceptionOnTooManyRequests()
    {
        $this->expectException(RateLimitExceededException::class);

        (new ResultConverter())->convert($this->createRawResult(['detail' => ['error_type' => 'rate_limit_error', 'message' => 'Rate limit exceeded']], 429));
    }

    public function testItThrowsServerExceptionWhenOverloaded()
    {
        $this->expectException(ServerException::class);

        (new ResultConverter())->convert($this->createRawResult(['detail' => ['error_type' => 'overloaded_error', 'message' => 'Overloaded']], 529));
    }

    public function testItReturnsTokenUsageExtractor()
    {
        $this->assertInstanceOf(TokenUsageExtractor::class, (new ResultConverter())->getTokenUsageExtractor());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createRawResult(array $data, int $statusCode = 200): RawHttpResult
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($data, ['http_code' => $statusCode]));

        return new RawHttpResult($httpClient->request('POST', 'https://api.typesafe.ai/v1/systemone'));
    }
}

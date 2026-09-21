<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Jev\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Jev\Jev;
use Symfony\AI\Platform\Bridge\Jev\Output\ChoiceAnswer;
use Symfony\AI\Platform\Bridge\Jev\Output\EvaluationResult;
use Symfony\AI\Platform\Bridge\Jev\Output\NoulAnswer;
use Symfony\AI\Platform\Bridge\Jev\Output\ScoreAnswer;
use Symfony\AI\Platform\Bridge\Jev\ResultConverter;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class ResultConverterTest extends TestCase
{
    public function testItConvertsANoulResponse()
    {
        $result = $this->convertFixture('noul.json');

        $this->assertInstanceOf(ObjectResult::class, $result);
        $content = $result->getContent();
        $this->assertInstanceOf(EvaluationResult::class, $content);
        $this->assertSame('jev-1.13.0', $content->getModel());
        $this->assertSame(0.95, $content->getNoul('is_urgent'));
        $this->assertInstanceOf(NoulAnswer::class, $content->getAnswer('is_urgent'));
    }

    public function testItPreservesChoiceProbabilitiesAndConfidence()
    {
        $result = $this->convertFixture('choice.json');
        $content = $result->getContent();
        $this->assertInstanceOf(EvaluationResult::class, $content);

        $this->assertSame('billing', $content->getChoice('department'));
        $this->assertSame(0.81, $content->getConfidence('department'));
        $this->assertSame([
            'billing' => 0.88,
            'technical' => 0.12,
            'sales' => 0.0,
        ], $content->getProbabilities('department'));
        $this->assertInstanceOf(ChoiceAnswer::class, $content->getAnswer('department'));
    }

    public function testItConvertsAScoreResponse()
    {
        $result = $this->convertFixture('score.json');
        $content = $result->getContent();
        $this->assertInstanceOf(EvaluationResult::class, $content);

        $this->assertSame(1.05, $content->getScore('frustration'));
        $this->assertSame(0.92, $content->getConfidence('frustration'));
        $answer = $content->getAnswer('frustration');
        $this->assertInstanceOf(ScoreAnswer::class, $answer);
        $this->assertSame(['0' => 'Calm', '1' => 'Frustrated', '2' => 'Very angry'], $answer->getLegend());
        $this->assertSame(['0' => 0.0, '1' => 0.95, '2' => 0.05], $answer->getProbabilities());
    }

    public function testItThrowsOnAuthenticationError()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Must supply an API key!');

        $this->convertFixture('authentication-error.json', 403);
    }

    public function testItThrowsOnValidationError()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Field required');

        $this->convertFixture('validation-error.json', 422);
    }

    public function testItThrowsWhenAnswersAreMissing()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"model":"jev-1.13.0"}', ['http_code' => 200]),
        ]);

        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain answers.');

        $converter->convert(new RawHttpResult($httpClient->request('GET', 'http://localhost')));
    }

    public function testItSupportsJevModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Jev('jev-latest')));
    }

    private function convertFixture(string $filename, int $status = 200): ObjectResult
    {
        $body = file_get_contents(__DIR__.'/Fixtures/'.$filename);
        $httpClient = new MockHttpClient([
            new MockResponse($body, ['http_code' => $status, 'response_headers' => ['content-type' => 'application/json']]),
        ]);

        $result = (new ResultConverter())->convert(new RawHttpResult($httpClient->request('GET', 'http://localhost')));
        $this->assertInstanceOf(ObjectResult::class, $result);

        return $result;
    }
}

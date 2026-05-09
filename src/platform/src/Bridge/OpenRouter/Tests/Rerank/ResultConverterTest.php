<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Tests\Rerank;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\OpenRouter\Rerank\ResultConverter;
use Symfony\AI\Platform\Bridge\OpenRouter\RerankModel;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class ResultConverterTest extends TestCase
{
    public function testItSupportsRerankModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new RerankModel('cohere/rerank-v3.5')));
    }

    public function testItDoesNotSupportNonRerankModel()
    {
        $converter = new ResultConverter();

        $this->assertFalse($converter->supports(new CompletionsModel('openrouter/auto')));
    }

    public function testItThrowsExceptionOnNon200StatusCode()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $response->method('getContent')->willReturn('Internal Server Error');

        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 500');

        $converter->convert(new RawHttpResult($response));
    }

    public function testItConvertsResponseToRerankingResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'results' => [
                ['index' => 0, 'relevance_score' => 0.95],
                ['index' => 1, 'relevance_score' => 0.42],
            ],
        ]);

        $converter = new ResultConverter();
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(RerankingResult::class, $result);
        $entries = $result->getContent();
        $this->assertCount(2, $entries);
        $this->assertSame(0, $entries[0]->getIndex());
        $this->assertSame(0.95, $entries[0]->getScore());
        $this->assertSame(1, $entries[1]->getIndex());
        $this->assertSame(0.42, $entries[1]->getScore());
    }

    public function testItCastsStringValuesToCorrectTypes()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'results' => [
                ['index' => '2', 'relevance_score' => '0.81'],
            ],
        ]);

        $converter = new ResultConverter();
        $result = $converter->convert(new RawHttpResult($response));

        $entries = $result->getContent();
        $this->assertCount(1, $entries);
        $this->assertSame(2, $entries[0]->getIndex());
        $this->assertSame(0.81, $entries[0]->getScore());
    }

    public function testItThrowsExceptionWhenResponseDoesNotContainResults()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['invalid' => 'response']);

        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain reranking results.');

        $converter->convert(new RawHttpResult($response));
    }

    public function testItHasNoTokenUsageExtractor()
    {
        $converter = new ResultConverter();

        $this->assertNull($converter->getTokenUsageExtractor());
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Perplexity\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ResultConverterTest extends TestCase
{
    public function testConvertTextResult()
    {
        $converter = new ResultConverter();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello world',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
    }

    public function testConvertMultipleChoices()
    {
        $converter = new ResultConverter();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Choice 1',
                    ],
                    'finish_reason' => 'stop',
                ],
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Choice 2',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ChoiceResult::class, $result);
        $choices = $result->getContent();
        $this->assertCount(2, $choices);
        $this->assertSame('Choice 1', $choices[0]->getContent());
        $this->assertSame('Choice 2', $choices[1]->getContent());
    }

    public function testThrowsExceptionWhenNoChoices()
    {
        $converter = new ResultConverter();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain choices');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsExceptionForUnsupportedFinishReason()
    {
        $converter = new ResultConverter();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Test content',
                    ],
                    'finish_reason' => 'unsupported_reason',
                ],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported finish reason "unsupported_reason"');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testAttachesSearchResultsToMetadata()
    {
        $converter = new ResultConverter();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Test content',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'search_results' => [
                [
                    'title' => 'Result 1',
                    'url' => 'http://example.com/1',
                ],
                [
                    'title' => 'Result 2',
                    'url' => 'http://example.com/2',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertTrue($result->getMetadata()->has('search_results'));
        $searchResults = $result->getMetadata()->get('search_results');
        $this->assertCount(2, $searchResults);
        $this->assertSame('Result 1', $searchResults[0]['title']);
        $this->assertSame('http://example.com/1', $searchResults[0]['url']);
    }

    public function testAttachesCitationsToMetadata()
    {
        $converter = new ResultConverter();
        $httpResponse = self::createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Test content',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'citations' => [
                'Citation 1',
                'Citation 2',
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertTrue($result->getMetadata()->has('citations'));
        $citations = $result->getMetadata()->get('citations');
        $this->assertCount(2, $citations);
        $this->assertSame('Citation 1', $citations[0]);
        $this->assertSame('Citation 2', $citations[1]);
    }
}

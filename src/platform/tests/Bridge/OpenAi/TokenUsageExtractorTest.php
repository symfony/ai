<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Output;
use Symfony\AI\Platform\Bridge\OpenAi\TokenUsageExtractor;
use Symfony\AI\Platform\Message\MessageBagInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\Metadata\Metadata;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\TokenUsage\TokenUsage;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(TokenUsageExtractor::class)]
#[UsesClass(Output::class)]
#[UsesClass(TextResult::class)]
#[UsesClass(StreamResult::class)]
#[UsesClass(Metadata::class)]
#[Small]
final class TokenUsageExtractorTest extends TestCase
{
    public function testItHandlesStreamResponsesWithoutProcessing()
    {
        $extractor = new TokenUsageExtractor();
        $streamResult = new StreamResult((static function () { yield 'test'; })());
        $output = $this->createOutput($streamResult);

        $tokenUsage = $extractor->extractTokenUsage($output);

        $this->assertNull($tokenUsage);
    }

    public function testItDoesNothingWithoutRawResponse()
    {
        $extractor = new TokenUsageExtractor();
        $textResult = new TextResult('test');
        $output = $this->createOutput($textResult);

        $tokenUsage = $extractor->extractTokenUsage($output);

        $this->assertNull($tokenUsage);
    }

    public function testItAddsRemainingTokensToMetadata()
    {
        $extractor = new TokenUsageExtractor();
        $textResult = new TextResult('test');

        $textResult->setRawResult($this->createRawResult());

        $output = $this->createOutput($textResult);

        $tokenUsage = $extractor->extractTokenUsage($output);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(1000, $tokenUsage->remaining);
    }

    public function testItAddsUsageTokensToMetadata()
    {
        $extractor = new TokenUsageExtractor();
        $textResult = new TextResult('test');

        $rawResult = $this->createRawResult([
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
            ],
        ]);

        $textResult->setRawResult($rawResult);
        $output = $this->createOutput($textResult);

        // Act
        $tokenUsage = $extractor->extractTokenUsage($output);

        // Assert
        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(1000, $tokenUsage->remaining);
        $this->assertSame(10, $tokenUsage->prompt);
        $this->assertSame(20, $tokenUsage->completion);
        $this->assertSame(30, $tokenUsage->total);
    }

    public function testItHandlesMissingUsageFields()
    {
        // Arrange
        $extractor = new TokenUsageExtractor();
        $textResult = new TextResult('test');

        $rawResponse = $this->createRawResult([
            'usage' => [
                'prompt_tokens' => 10,
            ],
        ]);

        $textResult->setRawResult($rawResponse);
        $output = $this->createOutput($textResult);

        // Act
        $tokenUsage = $extractor->extractTokenUsage($output);

        // Assert
        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(1000, $tokenUsage->remaining);
        $this->assertSame(10, $tokenUsage->prompt);
        $this->assertNull($tokenUsage->completion);
        $this->assertNull($tokenUsage->total);
    }

    private function createRawResult(array $data = []): RawHttpResult
    {
        $rawResponse = $this->createStub(ResponseInterface::class);
        $rawResponse->method('getHeaders')->willReturn([
            'x-ratelimit-remaining-tokens' => ['1000'],
        ]);
        $rawResponse->method('toArray')->willReturn($data);

        return new RawHttpResult($rawResponse);
    }

    private function createOutput(ResultInterface $result): Output
    {
        return new Output(
            $this->createStub(Model::class),
            $result,
            $this->createStub(MessageBagInterface::class),
            [],
        );
    }
}

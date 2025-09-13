<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\VertexAi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\TokenUsageResultHandler;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Metadata\TokenUsage;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(TokenUsageResultHandler::class)]
#[UsesClass(TextResult::class)]
#[UsesClass(StreamResult::class)]
#[UsesClass(Metadata::class)]
#[Small]
final class TokenUsageDeterminerTest extends TestCase
{
    public function testItDoesNothingWithoutRawResponse()
    {
        $tokenUsageResultHandler = new TokenUsageResultHandler();
        $textResult = new TextResult('test');

        $tokenUsageResultHandler->handleResult($textResult);

        $this->assertCount(0, $textResult->getMetadata());
    }

    public function testItAddsUsageTokensToMetadata()
    {
        // Arrange
        $textResult = new TextResult('test');

        $rawResponse = $this->createRawResponse([
            'usageMetadata' => [
                'promptTokenCount' => 10,
                'candidatesTokenCount' => 20,
                'thoughtsTokenCount' => 20,
                'totalTokenCount' => 50,
            ],
        ]);

        $textResult->setRawResult($rawResponse);
        $tokenUsageResultHandler = new TokenUsageResultHandler();

        // Act
        $tokenUsageResultHandler->handleResult($textResult);

        // Assert
        $metadata = $textResult->getMetadata();
        $tokenUsage = $metadata->get('token_usage');

        $this->assertCount(1, $metadata);
        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->promptTokens);
        $this->assertSame(20, $tokenUsage->completionTokens);
        $this->assertSame(20, $tokenUsage->thinkingTokens);
        $this->assertSame(50, $tokenUsage->totalTokens);
    }

    public function testItHandlesMissingUsageFields()
    {
        // Arrange
        $textResult = new TextResult('test');

        $rawResponse = $this->createRawResponse([
            'usageMetadata' => [
                'promptTokenCount' => 10,
            ],
        ]);

        $textResult->setRawResult($rawResponse);
        $tokenUsageResultHandler = new TokenUsageResultHandler();

        // Act
        $tokenUsageResultHandler->handleResult($textResult);

        // Assert
        $metadata = $textResult->getMetadata();
        $tokenUsage = $metadata->get('token_usage');

        $this->assertCount(1, $metadata);
        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->promptTokens);
        $this->assertNull($tokenUsage->completionTokens);
        $this->assertNull($tokenUsage->thinkingTokens);
        $this->assertNull($tokenUsage->totalTokens);
    }

    public function testItAddsEmptyTokenUsageWhenUsageMetadataNotPresent()
    {
        // Arrange
        $textResult = new TextResult('test');
        $rawResponse = $this->createRawResponse(['other' => 'data']);
        $textResult->setRawResult($rawResponse);
        $tokenUsageResultHandler = new TokenUsageResultHandler();

        // Act
        $tokenUsageResultHandler->handleResult($textResult);

        // Assert
        $metadata = $textResult->getMetadata();
        $tokenUsage = $metadata->get('token_usage');

        $this->assertCount(1, $metadata);
        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertNull($tokenUsage->promptTokens);
        $this->assertNull($tokenUsage->completionTokens);
        $this->assertNull($tokenUsage->thinkingTokens);
        $this->assertNull($tokenUsage->totalTokens);
    }

    public function testItHandlesStreamResults()
    {
        $tokenUsageResultHandler = new TokenUsageResultHandler();
        $chunks = [
            ['content' => 'chunk1'],
            ['content' => 'chunk2', 'usageMetadata' => [
                'promptTokenCount' => 15,
                'candidatesTokenCount' => 25,
                'totalTokenCount' => 40,
            ]],
        ];

        $streamResult = new StreamResult((function () use ($chunks) {
            foreach ($chunks as $chunk) {
                yield $chunk;
            }
        })());

        $tokenUsageResultHandler->handleResult($streamResult);

        $metadata = $streamResult->getMetadata();
        $tokenUsage = $metadata->get('token_usage');

        $this->assertCount(1, $metadata);
        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(15, $tokenUsage->promptTokens);
        $this->assertSame(25, $tokenUsage->completionTokens);
        $this->assertNull($tokenUsage->thinkingTokens);
        $this->assertSame(40, $tokenUsage->totalTokens);
    }

    private function createRawResponse(array $data = []): RawHttpResult
    {
        $rawResponse = $this->createStub(ResponseInterface::class);

        $rawResponse->method('toArray')->willReturn($data);

        return new RawHttpResult($rawResponse);
    }
}

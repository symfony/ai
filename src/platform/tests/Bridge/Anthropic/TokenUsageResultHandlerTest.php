<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bridge\Anthropic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\TokenUsageResultHandler;
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
#[UsesClass(TokenUsage::class)]
#[Small]
final class TokenUsageResultHandlerTest extends TestCase
{
    public function testItHandlesStreamResponsesWithoutProcessing()
    {
        $tokenUsageResultHandler = new TokenUsageResultHandler();
        $streamResult = new StreamResult((static function () { yield 'test'; })());

        $tokenUsageResultHandler->handleResult($streamResult);

        $metadata = $streamResult->getMetadata();
        $this->assertCount(0, $metadata);
    }

    public function testItDoesNothingWithoutRawResponse()
    {
        $tokenUsageResultHandler = new TokenUsageResultHandler();
        $textResult = new TextResult('test');

        $tokenUsageResultHandler->handleResult($textResult);

        $metadata = $textResult->getMetadata();
        $this->assertCount(0, $metadata);
    }

    public function testItAddsRemainingTokensToMetadata()
    {
        $tokenUsageResultHandler = new TokenUsageResultHandler();
        $textResult = new TextResult('test');

        $textResult->setRawResult($this->createRawResult());

        $tokenUsageResultHandler->handleResult($textResult);

        $metadata = $textResult->getMetadata();
        $tokenUsage = $metadata->get('token_usage');

        $this->assertCount(1, $metadata);
        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertNull($tokenUsage->remainingTokens);
    }

    public function testItAddsUsageTokensToMetadata()
    {
        $tokenUsageResultHandler = new TokenUsageResultHandler();
        $textResult = new TextResult('test');

        $rawResult = $this->createRawResult([
            'usage' => [
                'input_tokens' => 10,
                'output_tokens' => 20,
                'server_tool_use' => [
                    'web_search_requests' => 30,
                ],
                'cache_creation_input_tokens' => 40,
                'cache_read_input_tokens' => 50,
            ],
        ]);

        $textResult->setRawResult($rawResult);

        $tokenUsageResultHandler->handleResult($textResult);

        $metadata = $textResult->getMetadata();
        $tokenUsage = $metadata->get('token_usage');

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->promptTokens);
        $this->assertSame(30, $tokenUsage->toolTokens);
        $this->assertSame(20, $tokenUsage->completionTokens);
        $this->assertNull($tokenUsage->remainingTokens);
        $this->assertNull($tokenUsage->thinkingTokens);
        $this->assertSame(90, $tokenUsage->cachedTokens);
        $this->assertNull($tokenUsage->totalTokens);
    }

    public function testItHandlesMissingUsageFields()
    {
        $tokenUsageResultHandler = new TokenUsageResultHandler();
        $textResult = new TextResult('test');

        $rawResult = $this->createRawResult([
            'usage' => [
                // Missing some fields
                'input_tokens' => 10,
            ],
        ]);

        $textResult->setRawResult($rawResult);

        $tokenUsageResultHandler->handleResult($textResult);

        $metadata = $textResult->getMetadata();
        $tokenUsage = $metadata->get('token_usage');

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->promptTokens);
        $this->assertNull($tokenUsage->remainingTokens);
        $this->assertNull($tokenUsage->completionTokens);
        $this->assertNull($tokenUsage->totalTokens);
    }

    private function createRawResult(array $data = []): RawHttpResult
    {
        $rawResponse = $this->createStub(ResponseInterface::class);
        $rawResponse->method('toArray')->willReturn($data);

        return new RawHttpResult($rawResponse);
    }
}

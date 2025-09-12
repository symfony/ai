<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Mistral;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\TokenUsageResultHandler;
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

        $textResult->setRawResult($this->createRawResponse());

        $tokenUsageResultHandler->handleResult($textResult);

        $metadata = $textResult->getMetadata();
        $tokenUsage = $metadata->get('token_usage');

        $this->assertCount(1, $metadata);
        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(1000, $tokenUsage->remainingTokensMinute);
        $this->assertSame(1000000, $tokenUsage->remainingTokensMonth);
    }

    public function testItAddsUsageTokensToMetadata()
    {
        $tokenUsageResultHandler = new TokenUsageResultHandler();
        $textResult = new TextResult('test');

        $rawResponse = $this->createRawResponse([
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
            ],
        ]);

        $textResult->setRawResult($rawResponse);

        $tokenUsageResultHandler->handleResult($textResult);

        $metadata = $textResult->getMetadata();
        $tokenUsage = $metadata->get('token_usage');

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(1000, $tokenUsage->remainingTokensMinute);
        $this->assertSame(1000000, $tokenUsage->remainingTokensMonth);
        $this->assertSame(10, $tokenUsage->promptTokens);
        $this->assertSame(20, $tokenUsage->completionTokens);
        $this->assertSame(30, $tokenUsage->totalTokens);
    }

    public function testItHandlesMissingUsageFields()
    {
        $tokenUsageResultHandler = new TokenUsageResultHandler();
        $textResult = new TextResult('test');

        $rawResponse = $this->createRawResponse([
            'usage' => [
                // Missing some fields
                'prompt_tokens' => 10,
            ],
        ]);

        $textResult->setRawResult($rawResponse);

        $tokenUsageResultHandler->handleResult($textResult);

        $metadata = $textResult->getMetadata();
        $tokenUsage = $metadata->get('token_usage');

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(1000, $tokenUsage->remainingTokensMinute);
        $this->assertSame(1000000, $tokenUsage->remainingTokensMonth);
        $this->assertSame(10, $tokenUsage->promptTokens);
        $this->assertNull($tokenUsage->completionTokens);
        $this->assertNull($tokenUsage->totalTokens);
    }

    private function createRawResponse(array $data = []): RawHttpResult
    {
        $rawResponse = $this->createStub(ResponseInterface::class);
        $rawResponse->method('getHeaders')->willReturn([
            'x-ratelimit-limit-tokens-minute' => ['1000'],
            'x-ratelimit-limit-tokens-month' => ['1000000'],
        ]);

        $rawResponse->method('toArray')->willReturn($data);

        return new RawHttpResult($rawResponse);
    }
}

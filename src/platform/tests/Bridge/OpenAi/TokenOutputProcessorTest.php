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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Output;
use Symfony\AI\Platform\Bridge\OpenAi\TokenOutputProcessor;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Metadata\TokenUsage;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TokenOutputProcessorTest extends TestCase
{
    public function testItHandlesStreamResponsesWithoutProcessing()
    {
        $processor = new TokenOutputProcessor();
        $streamResult = new StreamResult((static function () { yield 'test'; })());
        $output = $this->createOutput($streamResult);

        $processor->processOutput($output);

        $metadata = $output->getResult()->getMetadata();
        $this->assertCount(0, $metadata);
    }

    public function testItDoesNothingWithoutRawResponse()
    {
        $processor = new TokenOutputProcessor();
        $textResult = new TextResult('test');
        $output = $this->createOutput($textResult);

        $processor->processOutput($output);

        $metadata = $output->getResult()->getMetadata();
        $this->assertCount(0, $metadata);
    }

    public function testItAddsRemainingTokensToMetadata()
    {
        $processor = new TokenOutputProcessor();
        $textResult = new TextResult('test');

        $textResult->setRawResult($this->createRawResult());

        $output = $this->createOutput($textResult);

        $processor->processOutput($output);

        $metadata = $output->getResult()->getMetadata();
        $tokenUsage = $metadata->get('token_usage');

        $this->assertCount(1, $metadata);
        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(1000, $tokenUsage->remainingTokens);
    }

    public function testItAddsUsageTokensToMetadata()
    {
        $processor = new TokenOutputProcessor();
        $textResult = new TextResult('test');

        $rawResult = $this->createRawResult([
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 50,
                'completion_tokens_details' => [
                    'reasoning_tokens' => 20,
                ],
                'prompt_tokens_details' => [
                    'cached_tokens' => 40,
                ],
            ],
        ]);

        $textResult->setRawResult($rawResult);

        $output = $this->createOutput($textResult);

        $processor->processOutput($output);

        $metadata = $output->getResult()->getMetadata();
        $tokenUsage = $metadata->get('token_usage');

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->promptTokens);
        $this->assertSame(20, $tokenUsage->completionTokens);
        $this->assertSame(1000, $tokenUsage->remainingTokens);
        $this->assertSame(20, $tokenUsage->thinkingTokens);
        $this->assertSame(40, $tokenUsage->cachedTokens);
        $this->assertSame(50, $tokenUsage->totalTokens);
    }

    public function testItHandlesMissingUsageFields()
    {
        $processor = new TokenOutputProcessor();
        $textResult = new TextResult('test');

        $rawResult = $this->createRawResult([
            'usage' => [
                // Missing some fields
                'prompt_tokens' => 10,
            ],
        ]);

        $textResult->setRawResult($rawResult);

        $output = $this->createOutput($textResult);

        $processor->processOutput($output);

        $metadata = $output->getResult()->getMetadata();
        $tokenUsage = $metadata->get('token_usage');

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->promptTokens);
        $this->assertSame(1000, $tokenUsage->remainingTokens);
        $this->assertNull($tokenUsage->completionTokens);
        $this->assertNull($tokenUsage->totalTokens);
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
        return new Output('gpt-4o', $result, new MessageBag(), []);
    }
}

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
use Symfony\AI\Agent\Output;
use Symfony\AI\Platform\Bridge\Mistral\TokenOutputProcessor;
use Symfony\AI\Platform\Message\MessageBagInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\Metadata\Metadata;
use Symfony\AI\Platform\Result\Metadata\TokenUsage;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(TokenOutputProcessor::class)]
#[UsesClass(Output::class)]
#[UsesClass(TextResult::class)]
#[UsesClass(StreamResult::class)]
#[UsesClass(Metadata::class)]
#[Small]
final class TokenOutputProcessorTest extends TestCase
{
    public function testItHandlesStreamResponsesWithoutProcessing()
    {
        $processor = new TokenOutputProcessor();
        $streamResult = new StreamResult((static function () { yield 'test'; })());
        $output = $this->createOutput($streamResult);

        $processor->processOutput($output);

        $metadata = $output->result->getMetadata();
        $this->assertCount(0, $metadata);
    }

    public function testItDoesNothingWithoutRawResponse()
    {
        $processor = new TokenOutputProcessor();
        $textResult = new TextResult('test');
        $output = $this->createOutput($textResult);

        $processor->processOutput($output);

        $metadata = $output->result->getMetadata();
        $this->assertCount(0, $metadata);
    }

    public function testItAddsRemainingTokensToMetadata()
    {
        $processor = new TokenOutputProcessor();
        $textResult = new TextResult('test');

        $textResult->setRawResult($this->createRawResponse());

        $output = $this->createOutput($textResult);

        $processor->processOutput($output);

        $metadata = $output->result->getMetadata();
        $tokenUsage = $metadata->get('token_usage');

        $this->assertCount(1, $metadata);
        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(1000, $tokenUsage->remainingTokensMinute);
        $this->assertSame(1000000, $tokenUsage->remainingTokensMonth);
    }

    public function testItAddsUsageTokensToMetadata()
    {
        $processor = new TokenOutputProcessor();
        $textResult = new TextResult('test');

        $rawResponse = $this->createRawResponse([
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
            ],
        ]);

        $textResult->setRawResult($rawResponse);

        $output = $this->createOutput($textResult);

        $processor->processOutput($output);

        $metadata = $output->result->getMetadata();
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
        $processor = new TokenOutputProcessor();
        $textResult = new TextResult('test');

        $rawResponse = $this->createRawResponse([
            'usage' => [
                // Missing some fields
                'prompt_tokens' => 10,
            ],
        ]);

        $textResult->setRawResult($rawResponse);

        $output = $this->createOutput($textResult);

        $processor->processOutput($output);

        $metadata = $output->result->getMetadata();
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

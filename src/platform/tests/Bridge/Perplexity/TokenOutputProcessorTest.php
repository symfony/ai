<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Perplexity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Output;
use Symfony\AI\Platform\Bridge\Perplexity\TokenOutputProcessor;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Metadata\TokenUsage;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
#[CoversClass(TokenOutputProcessor::class)]
#[UsesClass(Output::class)]
#[UsesClass(TextResult::class)]
#[UsesClass(StreamResult::class)]
#[UsesClass(Metadata::class)]
#[UsesClass(TokenUsage::class)]
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

    public function testItAddsUsageTokensToMetadata()
    {
        $processor = new TokenOutputProcessor();
        $textResult = new TextResult('test');

        $rawResult = $this->createRawResult([
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 50,
                'reasoning_tokens' => 20,
            ],
        ]);

        $textResult->setRawResult($rawResult);

        $output = $this->createOutput($textResult);

        $processor->processOutput($output);

        $metadata = $output->result->getMetadata();
        $tokenUsage = $metadata->get('token_usage');

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->promptTokens);
        $this->assertSame(20, $tokenUsage->completionTokens);
        $this->assertSame(20, $tokenUsage->thinkingTokens);
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

        $metadata = $output->result->getMetadata();
        $tokenUsage = $metadata->get('token_usage');

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->promptTokens);
        $this->assertNull($tokenUsage->completionTokens);
        $this->assertNull($tokenUsage->thinkingTokens);
        $this->assertNull($tokenUsage->totalTokens);
    }

    private function createRawResult(array $data = []): RawHttpResult
    {
        $rawResponse = $this->createStub(ResponseInterface::class);
        $rawResponse->method('toArray')->willReturn($data);

        return new RawHttpResult($rawResponse);
    }

    private function createOutput(ResultInterface $result): Output
    {
        return new Output(
            $this->createStub(Model::class),
            $result,
            $this->createStub(MessageBag::class),
            [],
        );
    }
}

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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Output;
use Symfony\AI\Platform\Bridge\VertexAi\TokenOutputProcessor;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Metadata\TokenUsage;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TokenOutputProcessorTest extends TestCase
{
    public function testItDoesNothingWithoutRawResponse()
    {
        $processor = new TokenOutputProcessor();
        $textResult = new TextResult('test');
        $output = $this->createOutput($textResult);

        $processor->processOutput($output);

        $this->assertCount(0, $output->getResult()->getMetadata());
    }

    public function testItAddsUsageTokensToMetadata()
    {
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
        $processor = new TokenOutputProcessor();
        $output = $this->createOutput($textResult);

        $processor->processOutput($output);

        $metadata = $output->getResult()->getMetadata();
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
        $textResult = new TextResult('test');

        $rawResponse = $this->createRawResponse([
            'usageMetadata' => [
                'promptTokenCount' => 10,
            ],
        ]);

        $textResult->setRawResult($rawResponse);
        $processor = new TokenOutputProcessor();
        $output = $this->createOutput($textResult);

        $processor->processOutput($output);

        $metadata = $output->getResult()->getMetadata();
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
        $textResult = new TextResult('test');
        $rawResponse = $this->createRawResponse(['other' => 'data']);
        $textResult->setRawResult($rawResponse);
        $processor = new TokenOutputProcessor();
        $output = $this->createOutput($textResult);

        $processor->processOutput($output);

        $metadata = $output->getResult()->getMetadata();
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
        $processor = new TokenOutputProcessor();
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

        $output = $this->createOutput($streamResult);

        $processor->processOutput($output);

        $metadata = $output->getResult()->getMetadata();
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

    private function createOutput(ResultInterface $result): Output
    {
        return new Output('gemini-2.5-pro', $result, new MessageBag(), []);
    }
}

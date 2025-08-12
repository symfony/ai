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
use Symfony\AI\Agent\Output;
use Symfony\AI\Platform\Bridge\VertexAi\TokenOutputProcessor;
use Symfony\AI\Platform\Message\MessageBagInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\Metadata\Metadata;
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
    public function testItDoesNothingWithoutRawResponse()
    {
        $processor = new TokenOutputProcessor();
        $textResult = new TextResult('test');
        $output = $this->createOutput($textResult);

        $processor->processOutput($output);

        $this->assertCount(0, $output->result->getMetadata());
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
        $processor = new TokenOutputProcessor();
        $output = $this->createOutput($textResult);

        // Act
        $processor->processOutput($output);

        // Assert
        $metadata = $output->result->getMetadata();
        $this->assertCount(4, $metadata);
        $this->assertSame(10, $metadata->get('prompt_tokens'));
        $this->assertSame(20, $metadata->get('completion_tokens'));
        $this->assertSame(20, $metadata->get('thinking_tokens'));
        $this->assertSame(50, $metadata->get('total_tokens'));
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
        $processor = new TokenOutputProcessor();
        $output = $this->createOutput($textResult);

        // Act
        $processor->processOutput($output);

        // Assert
        $metadata = $output->result->getMetadata();
        $this->assertCount(4, $metadata);
        $this->assertSame(10, $metadata->get('prompt_tokens'));
        $this->assertNull($metadata->get('completion_tokens'));
        $this->assertNull($metadata->get('completion_tokens'));
        $this->assertNull($metadata->get('total_tokens'));
    }

    private function createRawResponse(array $data = []): RawHttpResult
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
            $this->createStub(MessageBagInterface::class),
            [],
        );
    }
}

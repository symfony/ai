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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Output;
use Symfony\AI\Platform\Bridge\Mistral\TokenOutputProcessor;
use Symfony\AI\Platform\Message\MessageBagInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\Metadata\Metadata;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\ResponseInterface as SymfonyHttpResponse;

#[CoversClass(TokenOutputProcessor::class)]
#[UsesClass(Output::class)]
#[UsesClass(TextResult::class)]
#[UsesClass(StreamResult::class)]
#[UsesClass(Metadata::class)]
#[Small]
final class TokenOutputProcessorTest extends TestCase
{
    #[Test]
    public function itHandlesStreamResponsesWithoutProcessing(): void
    {
        $processor = new TokenOutputProcessor();
        $streamResult = new StreamResult((static function () { yield 'test'; })());
        $output = $this->createOutput($streamResult);

        $processor->processOutput($output);

        $metadata = $output->result->getMetadata();
        self::assertCount(0, $metadata);
    }

    #[Test]
    public function itDoesNothingWithoutRawResponse(): void
    {
        $processor = new TokenOutputProcessor();
        $textResult = new TextResult('test');
        $output = $this->createOutput($textResult);

        $processor->processOutput($output);

        $metadata = $output->result->getMetadata();
        self::assertCount(0, $metadata);
    }

    #[Test]
    public function itAddsRemainingTokensToMetadata(): void
    {
        $processor = new TokenOutputProcessor();
        $textResult = new TextResult('test');

        $textResult->setRawResult($this->createRawResponse());

        $output = $this->createOutput($textResult);

        $processor->processOutput($output);

        $metadata = $output->result->getMetadata();
        self::assertCount(2, $metadata);
        self::assertSame(1000, $metadata->get('remaining_tokens_minute'));
        self::assertSame(1000000, $metadata->get('remaining_tokens_month'));
    }

    #[Test]
    public function itAddsUsageTokensToMetadata(): void
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
        self::assertCount(5, $metadata);
        self::assertSame(1000, $metadata->get('remaining_tokens_minute'));
        self::assertSame(1000000, $metadata->get('remaining_tokens_month'));
        self::assertSame(10, $metadata->get('prompt_tokens'));
        self::assertSame(20, $metadata->get('completion_tokens'));
        self::assertSame(30, $metadata->get('total_tokens'));
    }

    #[Test]
    public function itHandlesMissingUsageFields(): void
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
        self::assertCount(5, $metadata);
        self::assertSame(1000, $metadata->get('remaining_tokens_minute'));
        self::assertSame(1000000, $metadata->get('remaining_tokens_month'));
        self::assertSame(10, $metadata->get('prompt_tokens'));
        self::assertNull($metadata->get('completion_tokens'));
        self::assertNull($metadata->get('total_tokens'));
    }

    private function createRawResponse(array $data = []): RawHttpResult
    {
        $rawResponse = self::createStub(SymfonyHttpResponse::class);
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
            self::createStub(Model::class),
            $result,
            self::createStub(MessageBagInterface::class),
            [],
        );
    }
}

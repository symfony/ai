<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAI;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Output;
use Symfony\AI\Platform\Bridge\OpenAI\TokenOutputProcessor;
use Symfony\AI\Platform\Message\MessageBagInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Response\Metadata\Metadata;
use Symfony\AI\Platform\Response\ResponseInterface;
use Symfony\AI\Platform\Response\StreamResponse;
use Symfony\AI\Platform\Response\TextResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as SymfonyHttpResponse;

#[CoversClass(TokenOutputProcessor::class)]
#[UsesClass(Output::class)]
#[UsesClass(TextResponse::class)]
#[UsesClass(StreamResponse::class)]
#[UsesClass(Metadata::class)]
#[Small]
final class TokenOutputProcessorTest extends TestCase
{
    #[Test]
    public function itHandlesStreamResponsesWithoutProcessing(): void
    {
        $processor = new TokenOutputProcessor();
        $streamResponse = new StreamResponse((static function () { yield 'test'; })());
        $output = $this->createOutput($streamResponse);

        $processor->processOutput($output);

        $metadata = $output->response->getMetadata();
        self::assertCount(0, $metadata);
    }

    #[Test]
    public function itDoesNothingWithoutRawResponse(): void
    {
        $processor = new TokenOutputProcessor();
        $textResponse = new TextResponse('test');
        $output = $this->createOutput($textResponse);

        $processor->processOutput($output);

        $metadata = $output->response->getMetadata();
        self::assertCount(0, $metadata);
    }

    #[Test]
    public function itAddsRemainingTokensToMetadata(): void
    {
        $processor = new TokenOutputProcessor();
        $textResponse = new TextResponse('test');

        $textResponse->setRawResponse($this->createRawResponse());

        $output = $this->createOutput($textResponse);

        $processor->processOutput($output);

        $metadata = $output->response->getMetadata();
        self::assertCount(1, $metadata);
        self::assertSame(1000, $metadata->get('remaining_tokens'));
    }

    #[Test]
    public function itAddsUsageTokensToMetadata(): void
    {
        $processor = new TokenOutputProcessor();
        $textResponse = new TextResponse('test');

        $rawResponse = $this->createRawResponse([
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
            ],
        ]);

        $textResponse->setRawResponse($rawResponse);

        $output = $this->createOutput($textResponse);

        $processor->processOutput($output);

        $metadata = $output->response->getMetadata();
        self::assertCount(4, $metadata);
        self::assertSame(1000, $metadata->get('remaining_tokens'));
        self::assertSame(10, $metadata->get('prompt_tokens'));
        self::assertSame(20, $metadata->get('completion_tokens'));
        self::assertSame(30, $metadata->get('total_tokens'));
    }

    #[Test]
    public function itHandlesMissingUsageFields(): void
    {
        $processor = new TokenOutputProcessor();
        $textResponse = new TextResponse('test');

        $rawResponse = $this->createRawResponse([
            'usage' => [
                // Missing some fields
                'prompt_tokens' => 10,
            ],
        ]);

        $textResponse->setRawResponse($rawResponse);

        $output = $this->createOutput($textResponse);

        $processor->processOutput($output);

        $metadata = $output->response->getMetadata();
        self::assertCount(4, $metadata);
        self::assertSame(1000, $metadata->get('remaining_tokens'));
        self::assertSame(10, $metadata->get('prompt_tokens'));
        self::assertNull($metadata->get('completion_tokens'));
        self::assertNull($metadata->get('total_tokens'));
    }

    private function createRawResponse(array $data = []): SymfonyHttpResponse
    {
        $rawResponse = self::createStub(SymfonyHttpResponse::class);
        $rawResponse->method('getHeaders')->willReturn([
            'x-ratelimit-remaining-tokens' => ['1000'],
        ]);
        $rawResponse->method('toArray')->willReturn($data);

        return $rawResponse;
    }

    private function createOutput(ResponseInterface $response): Output
    {
        return new Output(
            self::createStub(Model::class),
            $response,
            self::createStub(MessageBagInterface::class),
            [],
        );
    }
}

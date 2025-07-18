<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Memory;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Memory\EmbeddingProvider;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultPromise;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\VectorStoreInterface;

#[UsesClass(Text::class)]
#[UsesClass(ImageUrl::class)]
#[UsesClass(Message::class)]
#[UsesClass(Input::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(VectorStoreInterface::class)]
#[UsesClass(Model::class)]
#[UsesClass(PlatformInterface::class)]
#[CoversClass(EmbeddingProvider::class)]
#[Small]
final class EmbeddingProviderTest extends TestCase
{
    #[Test]
    public function itIsDoingNothingWithEmptyMessageBag(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $vectorStore = $this->createMock(VectorStoreInterface::class);
        $vectorStore->expects($this->never())->method('query');

        $embeddingProvider = new EmbeddingProvider(
            $platform,
            self::createStub(Model::class),
            $vectorStore,
        );

        $embeddingProvider->loadMemory(new Input(
            self::createStub(Model::class),
            new MessageBag(),
            [],
        ));
    }

    #[Test]
    public function itIsDoingNothingWithoutUserMessageInBag(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $vectorStore = $this->createMock(VectorStoreInterface::class);
        $vectorStore->expects($this->never())->method('query');

        $embeddingProvider = new EmbeddingProvider(
            $platform,
            self::createStub(Model::class),
            $vectorStore,
        );

        $embeddingProvider->loadMemory(new Input(
            self::createStub(Model::class),
            new MessageBag(Message::forSystem('This is a system message')),
            [],
        ));
    }

    #[Test]
    public function itIsDoingNothingWhenUserMessageHasNoTextContent(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $vectorStore = $this->createMock(VectorStoreInterface::class);
        $vectorStore->expects($this->never())->method('query');

        $embeddingProvider = new EmbeddingProvider(
            $platform,
            self::createStub(Model::class),
            $vectorStore,
        );

        $embeddingProvider->loadMemory(new Input(
            self::createStub(Model::class),
            new MessageBag(Message::ofUser(new ImageUrl('foo.jpg'))),
            [],
        ));
    }

    #[Test]
    public function itIsNotCreatingMemoryWhenNoVectorsFound(): void
    {
        $vectorResult = new VectorResult($vector = new Vector([0.1, 0.2], 2));
        $resultPromise = new ResultPromise(
            static fn () => $vectorResult,
            self::createStub(RawResultInterface::class),
        );

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->willReturn($resultPromise);

        $vectorStore = $this->createMock(VectorStoreInterface::class);
        $vectorStore->expects($this->once())
            ->method('query')
            ->with($vector)
            ->willReturn([]);

        $embeddingProvider = new EmbeddingProvider(
            $platform,
            self::createStub(Model::class),
            $vectorStore,
        );

        $memory = $embeddingProvider->loadMemory(new Input(
            self::createStub(Model::class),
            new MessageBag(Message::ofUser(new Text('Have we talked about the weather?'))),
            [],
        ));

        self::assertCount(0, $memory);
    }

    #[Test]
    public function itIsCreatingMemoryWithFoundVectors(): void
    {
        $vectorResult = new VectorResult($vector = new Vector([0.1, 0.2], 2));
        $resultPromise = new ResultPromise(
            static fn () => $vectorResult,
            self::createStub(RawResultInterface::class),
        );

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->willReturn($resultPromise);

        $vectorStore = $this->createMock(VectorStoreInterface::class);
        $vectorStore->expects($this->once())
            ->method('query')
            ->with($vector)
            ->willReturn([
                (object) ['metadata' => ['fact' => 'The sky is blue']],
                (object) ['metadata' => ['fact' => 'Water is wet']],
            ]);

        $embeddingProvider = new EmbeddingProvider(
            $platform,
            self::createStub(Model::class),
            $vectorStore,
        );

        $memory = $embeddingProvider->loadMemory(new Input(
            self::createStub(Model::class),
            new MessageBag(Message::ofUser(new Text('Have we talked about the weather?'))),
            [],
        ));

        self::assertCount(1, $memory);
        self::assertSame(
            <<<MARKDOWN
                ## Dynamic memories fitting user message

                {"fact":"The sky is blue"}{"fact":"Water is wet"}
                MARKDOWN,
            $memory[0]->content,
        );
    }
}

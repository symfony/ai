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
use Symfony\AI\Store\StoreInterface;

#[UsesClass(Text::class)]
#[UsesClass(ImageUrl::class)]
#[UsesClass(Message::class)]
#[UsesClass(Input::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(StoreInterface::class)]
#[UsesClass(Model::class)]
#[UsesClass(PlatformInterface::class)]
#[CoversClass(EmbeddingProvider::class)]
#[Small]
final class EmbeddingProviderTest extends TestCase
{
    public function testItIsDoingNothingWithEmptyMessageBag()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $store = $this->createMock(StoreInterface::class);
        $store->expects($this->never())->method('query');

        $embeddingProvider = new EmbeddingProvider(
            $platform,
            $this->createStub(Model::class),
            $store,
        );

        $embeddingProvider->load(new Input(
            $this->createStub(Model::class),
            new MessageBag(),
            [],
        ));
    }

    public function testItIsDoingNothingWithoutUserMessageInBag()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $store = $this->createMock(StoreInterface::class);
        $store->expects($this->never())->method('query');

        $embeddingProvider = new EmbeddingProvider(
            $platform,
            $this->createStub(Model::class),
            $store,
        );

        $embeddingProvider->load(new Input(
            $this->createStub(Model::class),
            new MessageBag(Message::forSystem('This is a system message')),
            [],
        ));
    }

    public function testItIsDoingNothingWhenUserMessageHasNoTextContent()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $store = $this->createMock(StoreInterface::class);
        $store->expects($this->never())->method('query');

        $embeddingProvider = new EmbeddingProvider(
            $platform,
            $this->createStub(Model::class),
            $store,
        );

        $embeddingProvider->load(new Input(
            $this->createStub(Model::class),
            new MessageBag(Message::ofUser(new ImageUrl('foo.jpg'))),
            [],
        ));
    }

    public function testItIsNotCreatingMemoryWhenNoVectorsFound()
    {
        $vectorResult = new VectorResult($vector = new Vector([0.1, 0.2], 2));
        $resultPromise = new ResultPromise(
            static fn () => $vectorResult,
            $this->createStub(RawResultInterface::class),
        );

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->willReturn($resultPromise);

        $store = $this->createMock(StoreInterface::class);
        $store->expects($this->once())
            ->method('query')
            ->with($vector)
            ->willReturn([]);

        $embeddingProvider = new EmbeddingProvider(
            $platform,
            $this->createStub(Model::class),
            $store,
        );

        $memory = $embeddingProvider->load(new Input(
            $this->createStub(Model::class),
            new MessageBag(Message::ofUser(new Text('Have we talked about the weather?'))),
            [],
        ));

        $this->assertCount(0, $memory);
    }

    public function testItIsCreatingMemoryWithFoundVectors()
    {
        $vectorResult = new VectorResult($vector = new Vector([0.1, 0.2], 2));
        $resultPromise = new ResultPromise(
            static fn () => $vectorResult,
            $this->createStub(RawResultInterface::class),
        );

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->willReturn($resultPromise);

        $store = $this->createMock(StoreInterface::class);
        $store->expects($this->once())
            ->method('query')
            ->with($vector)
            ->willReturn([
                (object) ['metadata' => ['fact' => 'The sky is blue']],
                (object) ['metadata' => ['fact' => 'Water is wet']],
            ]);

        $embeddingProvider = new EmbeddingProvider(
            $platform,
            $this->createStub(Model::class),
            $store,
        );

        $memory = $embeddingProvider->load(new Input(
            $this->createStub(Model::class),
            new MessageBag(Message::ofUser(new Text('Have we talked about the weather?'))),
            [],
        ));

        $this->assertCount(1, $memory);
        $this->assertSame(
            <<<MARKDOWN
                ## Dynamic memories fitting user message

                {"fact":"The sky is blue"}{"fact":"Water is wet"}
                MARKDOWN,
            $memory[0]->content,
        );
    }
}

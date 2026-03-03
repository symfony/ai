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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Memory\EmbeddingProvider;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;

final class EmbeddingProviderTest extends TestCase
{
    public function testItIsDoingNothingWithEmptyMessageBag()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $store = $this->createMock(StoreInterface::class);
        $store->expects($this->never())->method('query');

        $embeddingProvider = new EmbeddingProvider($platform, new Model('embedding-001'), $store);

        $embeddingProvider->load(new Input('embedding-001', new MessageBag(), []));
    }

    public function testItIsDoingNothingWithoutUserMessageInBag()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $store = $this->createMock(StoreInterface::class);
        $store->expects($this->never())->method('query');

        $embeddingProvider = new EmbeddingProvider($platform, new Model('embedding-001'), $store);

        $embeddingProvider->load(new Input(
            'embedding-001',
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

        $embeddingProvider = new EmbeddingProvider($platform, new Model('embedding-001'), $store);

        $embeddingProvider->load(new Input(
            'embedding-001',
            new MessageBag(Message::ofUser(new ImageUrl('foo.jpg'))),
            [],
        ));
    }

    public function testItIsNotCreatingMemoryWhenNoVectorsFound()
    {
        $vectorResult = new VectorResult($vector = new Vector([0.1, 0.2], 2));
        $deferredResult = new DeferredResult(
            new PlainConverter($vectorResult),
            $this->createStub(RawResultInterface::class),
        );

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->with('text-embedding-3-small', 'Have we talked about the weather?')
            ->willReturn($deferredResult);

        $store = $this->createMock(StoreInterface::class);
        $store->expects($this->once())
            ->method('query')
            ->with($this->callback(static fn ($query) => $query instanceof VectorQuery && $query->getVector() === $vector))
            ->willReturn([]);

        $embeddingProvider = new EmbeddingProvider($platform, new Model('text-embedding-3-small'), $store);

        $memory = $embeddingProvider->load(new Input(
            'text-embedding-3-small',
            new MessageBag(Message::ofUser(new Text('Have we talked about the weather?'))),
            [],
        ));

        $this->assertCount(0, $memory);
    }

    public function testItIsCreatingMemoryWithFoundVectors()
    {
        $vectorResult = new VectorResult($vector = new Vector([0.1, 0.2], 2));
        $deferredResult = new DeferredResult(
            new PlainConverter($vectorResult),
            $this->createStub(RawResultInterface::class),
        );

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->with('text-embedding-3-small', 'Have we talked about the weather?')
            ->willReturn($deferredResult);

        $store = $this->createMock(StoreInterface::class);
        $generator = (static function () {
            yield new VectorDocument(123, new NullVector(), new Metadata(['fact' => 'The sky is blue']));
            yield new VectorDocument(234, new NullVector(), new Metadata(['fact' => 'Water is wet']));
        })();
        $store->expects($this->once())
            ->method('query')
            ->with($this->callback(static fn ($query) => $query instanceof VectorQuery && $query->getVector() === $vector))
            ->willReturn($generator);

        $embeddingProvider = new EmbeddingProvider($platform, new Model('text-embedding-3-small'), $store);

        $memory = $embeddingProvider->load(new Input(
            'text-embedding-3-small',
            new MessageBag(Message::ofUser(new Text('Have we talked about the weather?'))),
            [],
        ));

        $this->assertCount(1, $memory);
        $this->assertSame(
            <<<MARKDOWN
                ## Dynamic memories fitting user message

                {"fact":"The sky is blue"}{"fact":"Water is wet"}
                MARKDOWN,
            $memory[0]->getContent(),
        );
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Tests\Server\RequestHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpSdk\Capability\Resource\CollectionInterface;
use Symfony\AI\McpSdk\Capability\Resource\MetadataInterface;
use Symfony\AI\McpSdk\Message\Request;
use Symfony\AI\McpSdk\Server\RequestHandler\ResourceListHandler;

#[Small]
#[CoversClass(ResourceListHandler::class)]
class ResourceListHandlerTest extends TestCase
{
    public function testHandleEmpty(): void
    {
        $collection = $this->getMockBuilder(CollectionInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMetadata'])
            ->getMock();
        $collection->expects($this->once())->method('getMetadata')->willReturn([]);

        $handler = new ResourceListHandler($collection);
        $message = new Request(1, 'resources/list', []);
        $response = $handler->createResponse($message);
        $this->assertEquals(1, $response->id);
        $this->assertEquals(['resources' => []], $response->result);
    }

    /**
     * @param iterable<MetadataInterface> $metadataList
     */
    #[DataProvider('metadataProvider')]
    public function testHandleReturnAll(iterable $metadataList): void
    {
        $collection = $this->getMockBuilder(CollectionInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMetadata'])
            ->getMock();
        $collection->expects($this->once())->method('getMetadata')->willReturn($metadataList);
        $handler = new ResourceListHandler($collection);
        $message = new Request(1, 'resources/list', []);
        $response = $handler->createResponse($message);
        $this->assertCount(1, $response->result['resources']);
        $this->assertArrayNotHasKey('nextCursor', $response->result);
    }

    /**
     * @return array<string, iterable<MetadataInterface>>
     */
    public static function metadataProvider(): array
    {
        $item = self::createMetadataItem();

        return [
            'array' => [[$item]],
            'generator' => [(function () use ($item) { yield $item; })()],
        ];
    }

    public function testHandlePagination(): void
    {
        $item = self::createMetadataItem();
        $collection = $this->getMockBuilder(CollectionInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMetadata'])
            ->getMock();
        $collection->expects($this->once())->method('getMetadata')->willReturn([$item, $item]);
        $handler = new ResourceListHandler($collection, 2);
        $message = new Request(1, 'resources/list', []);
        $response = $handler->createResponse($message);
        $this->assertCount(2, $response->result['resources']);
        $this->assertArrayHasKey('nextCursor', $response->result);
    }

    private static function createMetadataItem(): MetadataInterface
    {
        return new class implements MetadataInterface {
            public function getUri(): string
            {
                return 'file:///src/SomeFile.php';
            }

            public function getName(): string
            {
                return 'src/SomeFile.php';
            }

            public function getDescription(): string
            {
                return 'File src/SomeFile.php';
            }

            public function getMimeType(): string
            {
                return 'text/plain';
            }

            public function getSize(): int
            {
                return 1024;
            }
        };
    }
}

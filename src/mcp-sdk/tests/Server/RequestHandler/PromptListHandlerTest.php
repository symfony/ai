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
use Symfony\AI\McpSdk\Capability\Prompt\CollectionInterface;
use Symfony\AI\McpSdk\Capability\Prompt\MetadataInterface;
use Symfony\AI\McpSdk\Message\Request;
use Symfony\AI\McpSdk\Server\RequestHandler\PromptListHandler;

#[Small]
#[CoversClass(PromptListHandler::class)]
class PromptListHandlerTest extends TestCase
{
    public function testHandleEmpty(): void
    {
        $collection = $this->getMockBuilder(CollectionInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMetadata'])
            ->getMock();
        $collection->expects($this->once())->method('getMetadata')->willReturn([]);

        $handler = new PromptListHandler($collection);
        $message = new Request(1, 'prompts/list', []);
        $response = $handler->createResponse($message);
        $this->assertEquals(1, $response->id);
        $this->assertEquals(['prompts' => []], $response->result);
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
        $handler = new PromptListHandler($collection);
        $message = new Request(1, 'prompts/list', []);
        $response = $handler->createResponse($message);
        $this->assertCount(1, $response->result['prompts']);
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
        $handler = new PromptListHandler($collection, 2);
        $message = new Request(1, 'prompts/list', []);
        $response = $handler->createResponse($message);
        $this->assertCount(2, $response->result['prompts']);
        $this->assertArrayHasKey('nextCursor', $response->result);
    }

    private static function createMetadataItem(): MetadataInterface
    {
        return new class implements MetadataInterface {
            public function getName(): string
            {
                return 'greet';
            }

            public function getDescription(): string
            {
                return 'Greet a person with a nice message';
            }

            public function getArguments(): array
            {
                return [
                    [
                        'name' => 'first name',
                        'description' => 'The name of the person to greet',
                        'required' => false,
                    ],
                ];
            }
        };
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\Normalizer\Message\Content;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\CollectionNormalizer;
use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class CollectionNormalizerTest extends TestCase
{
    private CollectionNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new CollectionNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new Collection(new Text('Hello'))));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([Collection::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalizeDelegatesEachPartToTheSerializer()
    {
        $first = new Text('Hello');
        $second = new Text('World');
        $collection = new Collection($first, $second);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->exactly(2))
            ->method('normalize')
            ->willReturnCallback(static function (Text $text): array {
                return ['type' => 'text', 'text' => $text->getText()];
            });

        $this->normalizer->setNormalizer($innerNormalizer);

        $expected = [
            'type' => 'collection',
            'content' => [
                ['type' => 'text', 'text' => 'Hello'],
                ['type' => 'text', 'text' => 'World'],
            ],
        ];

        $this->assertSame($expected, $this->normalizer->normalize($collection));
    }
}

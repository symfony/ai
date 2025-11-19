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
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\TextNormalizer;
use Symfony\AI\Platform\Message\Content\Text;

final class TextNormalizerTest extends TestCase
{
    private TextNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new TextNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new Text('Hello, world!')));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([Text::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalize()
    {
        $text = new Text('Hello, world!');

        $expected = [
            'type' => 'text',
            'text' => 'Hello, world!',
        ];

        $this->assertSame($expected, $this->normalizer->normalize($text));
    }
}

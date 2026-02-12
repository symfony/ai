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
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\ThinkingNormalizer;
use Symfony\AI\Platform\Message\Content\Thinking;

final class ThinkingNormalizerTest extends TestCase
{
    private ThinkingNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ThinkingNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new Thinking('Analyzing the problem...')));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([Thinking::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalize()
    {
        $thinking = new Thinking('First, I need to check the file system structure...');

        $expected = [
            'type' => 'thinking',
            'thinking' => 'First, I need to check the file system structure...',
        ];

        $this->assertSame($expected, $this->normalizer->normalize($thinking));
    }
}

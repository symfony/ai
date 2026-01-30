<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Serializer;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Toon\ToonEncoder;

final class ToonEncoderTest extends TestCase
{
    private ToonEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new ToonEncoder();
    }

    public function testSupportsEncoding()
    {
        $this->assertTrue($this->encoder->supportsEncoding('toon'));
        $this->assertFalse($this->encoder->supportsEncoding('json'));
        $this->assertFalse($this->encoder->supportsEncoding('xml'));
    }

    public function testSupportsDecoding()
    {
        $this->assertTrue($this->encoder->supportsDecoding('toon'));
        $this->assertFalse($this->encoder->supportsDecoding('json'));
        $this->assertFalse($this->encoder->supportsDecoding('xml'));
    }

    public function testDefaultContextOptions()
    {
        $encoder = new ToonEncoder(defaultContext: ['delimiter' => '|', 'indent_size' => 4]);

        $data = [
            'parent' => [
                'values' => [1, 2, 3],
            ],
        ];
        $encoded = $encoder->encode($data, 'toon');

        $expected = <<<TOON
            parent:
                values[3]: 1|2|3
            TOON;

        $this->assertSame($expected, $encoded);
    }
}

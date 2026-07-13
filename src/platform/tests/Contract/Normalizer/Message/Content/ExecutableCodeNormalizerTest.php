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
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\ExecutableCodeNormalizer;
use Symfony\AI\Platform\Message\Content\ExecutableCode;

final class ExecutableCodeNormalizerTest extends TestCase
{
    private ExecutableCodeNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ExecutableCodeNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new ExecutableCode('echo "hi";', 'php', 'exec_1')));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([ExecutableCode::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalize()
    {
        $executableCode = new ExecutableCode('echo "hi";', 'php', 'exec_1');

        $expected = [
            'type' => 'executable_code',
            'code' => 'echo "hi";',
            'language' => 'php',
            'id' => 'exec_1',
        ];

        $this->assertSame($expected, $this->normalizer->normalize($executableCode));
    }
}

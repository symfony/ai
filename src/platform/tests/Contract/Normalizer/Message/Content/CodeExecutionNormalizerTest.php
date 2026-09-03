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
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\CodeExecutionNormalizer;
use Symfony\AI\Platform\Message\Content\CodeExecution;

final class CodeExecutionNormalizerTest extends TestCase
{
    private CodeExecutionNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new CodeExecutionNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new CodeExecution(true, 'hi', 'exec_1')));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([CodeExecution::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalize()
    {
        $codeExecution = new CodeExecution(true, 'hi', 'exec_1');

        $expected = [
            'type' => 'code_execution',
            'succeeded' => true,
            'output' => 'hi',
            'id' => 'exec_1',
        ];

        $this->assertSame($expected, $this->normalizer->normalize($codeExecution));
    }
}

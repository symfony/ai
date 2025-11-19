<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\Normalizer\Message;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\Normalizer\Message\SystemMessageNormalizer;
use Symfony\AI\Platform\Message\SystemMessage;

final class SystemMessageNormalizerTest extends TestCase
{
    private SystemMessageNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new SystemMessageNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new SystemMessage('content')));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([SystemMessage::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalize()
    {
        $message = new SystemMessage('You are a helpful assistant');

        $expected = [
            'role' => 'system',
            'content' => 'You are a helpful assistant',
        ];

        $this->assertSame($expected, $this->normalizer->normalize($message));
    }
}

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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\Normalizer\Message\SystemMessageNormalizer;
use Symfony\AI\Platform\Message\SystemMessage;

#[CoversClass(SystemMessageNormalizer::class)]
#[UsesClass(SystemMessage::class)]
final class SystemMessageNormalizerTest extends TestCase
{
    private SystemMessageNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new SystemMessageNormalizer();
    }

    #[Test]
    public function supportsNormalization(): void
    {
        self::assertTrue($this->normalizer->supportsNormalization(new SystemMessage('content')));
        self::assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    #[Test]
    public function getSupportedTypes(): void
    {
        self::assertSame([SystemMessage::class => true], $this->normalizer->getSupportedTypes(null));
    }

    #[Test]
    public function normalize(): void
    {
        $message = new SystemMessage('You are a helpful assistant');

        $expected = [
            'role' => 'system',
            'content' => 'You are a helpful assistant',
        ];

        self::assertSame($expected, $this->normalizer->normalize($message));
    }
}

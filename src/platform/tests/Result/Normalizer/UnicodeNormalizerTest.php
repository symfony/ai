<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\Normalizer\UnicodeNormalizer;
use Symfony\AI\Platform\Result\TextResult;

final class UnicodeNormalizerTest extends TestCase
{
    public function testRemovesZeroWidthSpace()
    {
        $normalizer = new UnicodeNormalizer();

        $input = "foo\u{200B}bar";

        $this->assertSame('foobar', $normalizer->normalize($input));
    }

    public function testRemovesByteOrderMark()
    {
        $normalizer = new UnicodeNormalizer();

        $input = "\u{FEFF}hello";

        $this->assertSame('hello', $normalizer->normalize($input));
    }

    public function testConvertsNonBreakingSpaceToRegularSpace()
    {
        $normalizer = new UnicodeNormalizer();

        $input = "foo\u{00A0}bar";

        $this->assertSame('foo bar', $normalizer->normalize($input));
    }

    public function testReturnsEmptyStringOnEmptyInput()
    {
        $normalizer = new UnicodeNormalizer();

        $this->assertSame('', $normalizer->normalize(''));
    }

    public function testLeavesPlainTextUnchanged()
    {
        $normalizer = new UnicodeNormalizer();

        $input = 'Hello, World!';

        $this->assertSame($input, $normalizer->normalize($input));
    }

    public function testSupportsReturnsTrueRegardlessOfContext()
    {
        $normalizer = new UnicodeNormalizer();

        $this->assertTrue($normalizer->supports(
            new Model('gpt-4'),
            new TextResult('foo'),
            [],
        ));
    }
}

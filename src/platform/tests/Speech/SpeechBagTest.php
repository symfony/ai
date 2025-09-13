<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Speech;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\Speech\Speech;
use Symfony\AI\Platform\Speech\SpeechBag;

final class SpeechBagTest extends TestCase
{
    public function testBagCanStoreSpeech()
    {
        $converter = $this->createMock(ResultConverterInterface::class);
        $rawResult = $this->createMock(RawResultInterface::class);

        $result = new DeferredResult($converter, $rawResult);

        $bag = new SpeechBag();

        $bag->add(new Speech([], $result, 'foo'));

        $this->assertCount(1, $bag);

        $this->assertInstanceOf(Speech::class, $bag->get('foo'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No speech with identifier "bar" found.');
        $this->expectExceptionCode(0);
        $bag->get('bar');
    }
}

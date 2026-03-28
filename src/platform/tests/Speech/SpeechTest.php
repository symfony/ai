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
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Speech\Speech;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechTest extends TestCase
{
    public function testAsBinaryDelegatesToDeferredResult()
    {
        $binaryContent = 'raw-audio-binary';
        $speech = new Speech(new DeferredResult(new PlainConverter(new BinaryResult($binaryContent)), new InMemoryRawResult()));

        $this->assertSame($binaryContent, $speech->asBinary());
    }

    public function testAsBase64ReturnsEncodedBinary()
    {
        $binaryContent = 'raw-audio-binary';
        $speech = new Speech(new DeferredResult(new PlainConverter(new BinaryResult($binaryContent)), new InMemoryRawResult()));

        $this->assertSame(base64_encode($binaryContent), $speech->asBase64());
    }

    public function testAsDataUriReturnsFormattedStringWithDefaultMimeType()
    {
        $binaryContent = 'raw-audio-binary';
        $speech = new Speech(new DeferredResult(new PlainConverter(new BinaryResult($binaryContent)), new InMemoryRawResult()));

        $expected = 'data:audio/mpeg;base64,'.base64_encode($binaryContent);
        $this->assertSame($expected, $speech->asDataUri());
    }

    public function testAsDataUriReturnsFormattedStringWithCustomMimeType()
    {
        $binaryContent = 'raw-audio-binary';
        $speech = new Speech(new DeferredResult(new PlainConverter(new BinaryResult($binaryContent)), new InMemoryRawResult()));

        $expected = 'data:audio/wav;base64,'.base64_encode($binaryContent);
        $this->assertSame($expected, $speech->asDataUri('audio/wav'));
    }
}

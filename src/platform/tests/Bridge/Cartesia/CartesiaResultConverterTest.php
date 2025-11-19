<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Cartesia;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cartesia\Cartesia;
use Symfony\AI\Platform\Bridge\Cartesia\CartesiaResultConverter;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\TextResult;

final class CartesiaResultConverterTest extends TestCase
{
    public function testSupportsModel()
    {
        $converter = new CartesiaResultConverter();

        $this->assertTrue($converter->supports(new Cartesia('sonic-3')));
        $this->assertFalse($converter->supports(new Model('any-model')));
    }

    public function testConvertSpeechToTextResponse()
    {
        $converter = new CartesiaResultConverter();
        $rawResult = new InMemoryRawResult([
            'text' => 'Hello there',
        ], [], new class {
            public function getInfo(): string
            {
                return 'stt';
            }
        });

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello there', $result->getContent());
    }

    public function testConvertTextToSpeechResponse()
    {
        $converter = new CartesiaResultConverter();
        $rawResult = new InMemoryRawResult([], [], new class {
            public function getInfo(): string
            {
                return 'tts';
            }

            public function getContent(): string
            {
                return file_get_contents(\dirname(__DIR__, 5).'/fixtures/audio.mp3');
            }
        });

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }
}

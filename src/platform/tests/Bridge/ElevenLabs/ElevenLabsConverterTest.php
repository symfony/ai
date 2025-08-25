<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\ElevenLabs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabs;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabsResultConverter;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;

#[CoversClass(ElevenLabsResultConverter::class)]
#[UsesClass(ElevenLabs::class)]
#[UsesClass(Model::class)]
#[UsesClass(TextResult::class)]
#[UsesClass(BinaryResult::class)]
#[UsesClass(InMemoryRawResult::class)]
final class ElevenLabsConverterTest extends TestCase
{
    public function testSupportsModel()
    {
        $converter = new ElevenLabsResultConverter(new MockHttpClient());

        $this->assertTrue($converter->supports(new ElevenLabs()));
        $this->assertFalse($converter->supports(new Model('any-model')));
    }

    public function testConvertSpeechToTextResponse()
    {
        $converter = new ElevenLabsResultConverter(new MockHttpClient());
        $rawResult = new InMemoryRawResult([
            'text' => 'Hello there',
        ], new class {
            public function getInfo(): string
            {
                return 'speech-to-text';
            }
        });

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello there', $result->getContent());
    }

    public function testConvertTextToSpeechResponse()
    {
        $converter = new ElevenLabsResultConverter(new MockHttpClient());
        $rawResult = new InMemoryRawResult([], new class {
            public function getInfo(): string
            {
                return 'text-to-speech';
            }

            public function getContent(): string
            {
                return file_get_contents(\dirname(__DIR__, 5).'/fixtures/audio.mp3');
            }
        });

        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio/mpeg', $result->mimeType);
    }
}

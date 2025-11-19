<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi\TextToSpeech;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech;
use Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverterTest extends TestCase
{
    public function testSupportsTextToSpeechModel()
    {
        $converter = new ResultConverter();
        $model = new TextToSpeech('tts-1');

        $this->assertTrue($converter->supports($model));
    }

    public function testDoesntSupportOtherModels()
    {
        $converter = new ResultConverter();
        $model = new Model('test-model');

        $this->assertFalse($converter->supports($model));
    }

    public function testThrowsOnErrorResponse()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The OpenAI Text-to-Speech API returned an error: "Hi Test!"');

        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('getStatusCode')
            ->willReturn(400);
        $result
            ->method('getContent')
            ->willReturn('Hi Test!');

        (new ResultConverter())->convert(new RawHttpResult($result));
    }

    public function testReturnResponseAsBinary()
    {
        $result = $this->createStub(ResponseInterface::class);
        $result
            ->method('getStatusCode')
            ->willReturn(200);
        $result
            ->method('getContent')
            ->willReturn('fake-audio-bytes');

        $binaryResult = (new ResultConverter())->convert(new RawHttpResult($result));

        $this->assertInstanceOf(BinaryResult::class, $binaryResult);
        $this->assertSame('fake-audio-bytes', $binaryResult->getContent());
    }
}

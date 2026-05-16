<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost\Tests\Audio;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bifrost\Audio\SpeechModel;
use Symfony\AI\Platform\Bridge\Bifrost\Audio\SpeechResultConverter;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechResultConverterTest extends TestCase
{
    public function testItSupportsSpeechModelOnly()
    {
        $converter = new SpeechResultConverter();

        $this->assertTrue($converter->supports(new SpeechModel('openai/tts-1')));
        $this->assertFalse($converter->supports(new Model('test-model')));
    }

    public function testItReturnsBinaryResult()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn('audio-bytes');
        $response->method('getHeaders')->willReturn(['content-type' => ['audio/mpeg']]);

        $result = (new SpeechResultConverter())->convert(new RawHttpResult($response));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio-bytes', $result->getContent());
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    public function testItThrowsAuthenticationExceptionOn401()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(401);
        $response->method('toArray')->willReturn(['error' => ['message' => 'Invalid API key.']]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key.');

        (new SpeechResultConverter())->convert(new RawHttpResult($response));
    }

    public function testItThrowsRuntimeExceptionOnGenericError()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $response->method('toArray')->willReturn([]);
        $response->method('getContent')->willReturn('Upstream error');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Bifrost text-to-speech API returned an error: "Upstream error"');

        (new SpeechResultConverter())->convert(new RawHttpResult($response));
    }
}

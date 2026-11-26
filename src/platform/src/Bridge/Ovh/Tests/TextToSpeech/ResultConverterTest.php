<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\Tests\TextToSpeech;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Ovh\TextToSpeech;
use Symfony\AI\Platform\Bridge\Ovh\TextToSpeech\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ResultConverterTest extends TestCase
{
    public function testItSupportsTextToSpeechModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new TextToSpeech('nvr-tts-en-us')));
        $this->assertFalse($converter->supports(new CompletionsModel('gpt-oss-120b')));
    }

    public function testItReturnsTheAudioAsBinaryResult()
    {
        $result = (new ResultConverter())->convert($this->createResult("\x00\x01\x02audio"));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame("\x00\x01\x02audio", $result->getContent());
    }

    public function testItThrowsExceptionOnServerError()
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        (new ResultConverter())->convert($this->createResult('Internal Server Error', 500));
    }

    public function testItThrowsExceptionOnUnexpectedStatusCode()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 204');

        (new ResultConverter())->convert($this->createResult('', 204));
    }

    public function testItHasNoTokenUsageExtractor()
    {
        $this->assertNull((new ResultConverter())->getTokenUsageExtractor());
    }

    private function createResult(string $body, int $status = 200): RawHttpResult
    {
        return new RawHttpResult((new MockHttpClient(new MockResponse($body, ['http_code' => $status])))->request('POST', 'https://example.com/'));
    }
}

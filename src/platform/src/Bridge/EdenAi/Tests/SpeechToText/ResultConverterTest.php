<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests\SpeechToText;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\EdenAiJobClient;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Bridge\EdenAi\SpeechToText;
use Symfony\AI\Platform\Bridge\EdenAi\SpeechToText\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ResultConverterTest extends TestCase
{
    public function testItSupportsSpeechToTextModelOnly()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new SpeechToText('audio/speech_to_text_async/openai')));
        $this->assertFalse($converter->supports(new Ocr('ocr/ocr/google')));
    }

    public function testItConvertsResponseToTextResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createResponse([
            'status' => 'success',
            'provider' => 'deepgram',
            'cost' => '0.0008',
            'output' => [
                'text' => 'Hello world',
                'diarization' => [
                    'total_speakers' => 1,
                    'entries' => [
                        ['segment' => 'Hello world', 'speaker' => 1],
                    ],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
        $this->assertSame('deepgram', $result->getMetadata()->get('provider'));
        $this->assertSame(0.0008, $result->getMetadata()->get('cost'));
        $this->assertSame(1, $result->getMetadata()->get('diarization')['total_speakers']);
    }

    public function testItThrowsWhenJobFails()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createResponse([
            'status' => 'fail',
            'output' => null,
            'error' => ['message' => 'Audio could not be processed.'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Eden AI request failed: "Audio could not be processed."');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testItThrowsWhenTextIsMissing()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createResponse(['status' => 'success', 'output' => []]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain text.');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    /**
     * Providers that need longer than the request answer with a job still running, which the
     * caller resolves later through the job client instead of waiting for it inside invoke().
     */
    public function testItHandsOutAJobWhileTheTranscriptionIsStillRunning()
    {
        $converter = new ResultConverter(new EdenAiJobClient(new MockHttpClient(), 'test-key', 'https://api.edenai.run', 'edenai'));
        $httpResponse = $this->createResponse([
            'status' => 'processing',
            'public_id' => '822436ad-3730-4149-a2c5-c7bdc4e3d9a9',
            'output' => null,
        ], 202);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(JobResult::class, $result);

        $handle = $result->getContent();
        $this->assertSame('822436ad-3730-4149-a2c5-c7bdc4e3d9a9', $handle->getId());
        $this->assertSame('edenai', $handle->getProvider());
        $this->assertSame('speech_to_text_async', $handle->get('subfeature'));
        $this->assertSame(600, $handle->getMaxDuration());
    }

    public function testItThrowsWhenAnAcceptedJobCannotBeResolved()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createResponse(['status' => 'processing'], 202);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Eden AI accepted the job but did not return a public_id to resolve it with.');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createResponse(array $data, int $statusCode = 200): ResponseInterface
    {
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn($statusCode);
        $httpResponse->method('toArray')->willReturn($data);

        return $httpResponse;
    }
}

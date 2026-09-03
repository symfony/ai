<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\DocumentParser;
use Symfony\AI\Platform\Bridge\EdenAi\ImageAnalysis;
use Symfony\AI\Platform\Bridge\EdenAi\ImageGeneration;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Bridge\EdenAi\SpeechToText;
use Symfony\AI\Platform\Bridge\EdenAi\Tts;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Every converter of the bridge must map an Eden AI error response onto the same platform
 * exception, instead of falling through to a shape check and reporting a missing field.
 *
 * The payloads below were captured from the live API. Eden AI is a FastAPI application and
 * reports errors through "detail", which the shared HttpStatusErrorHandlingTrait cannot
 * read, so each shape is asserted explicitly.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ResultConverterHttpStatusTest extends TestCase
{
    /**
     * @return iterable<string, array{ResultConverterInterface}>
     */
    public static function converterProvider(): iterable
    {
        yield 'ocr' => [new Ocr\ResultConverter()];
        yield 'documentParser' => [new DocumentParser\ResultConverter()];
        yield 'imageAnalysis' => [new ImageAnalysis\ResultConverter()];
        yield 'imageGeneration' => [new ImageGeneration\ResultConverter()];
        yield 'speechToText' => [new SpeechToText\ResultConverter()];
        yield 'tts' => [new Tts\ResultConverter(new MockHttpClient())];
    }

    #[DataProvider('converterProvider')]
    public function testItThrowsAuthenticationExceptionOnInvalidToken(ResultConverterInterface $converter)
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid token');

        $converter->convert($this->rawResult(['detail' => 'Invalid token'], 401));
    }

    /**
     * A request without credentials is answered with 403, which the shared trait does not
     * map at all.
     */
    #[DataProvider('converterProvider')]
    public function testItThrowsAuthenticationExceptionWhenNotAuthenticated(ResultConverterInterface $converter)
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Not authenticated');

        $converter->convert($this->rawResult(['detail' => 'Not authenticated'], 403));
    }

    /**
     * The 422 body names the offending field, which is the whole value of the message.
     */
    #[DataProvider('converterProvider')]
    public function testItThrowsBadRequestExceptionWithFieldErrorsOnValidationError(ResultConverterInterface $converter)
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Validation error: language: Field required');

        $converter->convert($this->rawResult([
            'detail' => 'Validation error',
            'errors' => [['field' => 'language', 'message' => 'Field required']],
        ], 422));
    }

    #[DataProvider('converterProvider')]
    public function testItThrowsBadRequestExceptionOnMalformedModelString(ResultConverterInterface $converter)
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid provider format: Expected \'feature/subfeature/provider[/model]\'');

        $converter->convert($this->rawResult([
            'detail' => [
                'error' => 'Invalid provider format',
                'message' => 'Expected \'feature/subfeature/provider[/model]\'',
                'received' => 'garbage',
            ],
        ], 400));
    }

    #[DataProvider('converterProvider')]
    public function testItThrowsModelNotFoundExceptionOnUnknownProvider(ResultConverterInterface $converter)
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Provider not found: Provider \'nope\' does not support ocr/ocr');

        $converter->convert($this->rawResult([
            'detail' => [
                'error' => 'Provider not found',
                'message' => 'Provider \'nope\' does not support ocr/ocr',
            ],
        ], 404));
    }

    #[DataProvider('converterProvider')]
    public function testItThrowsRateLimitExceededExceptionWithRetryAfter(ResultConverterInterface $converter)
    {
        try {
            $converter->convert($this->rawResult(['detail' => 'Rate limit reached'], 429, ['retry-after' => '42']));
            $this->fail(\sprintf('%s did not throw.', $converter::class));
        } catch (RateLimitExceededException $e) {
            $this->assertSame(42, $e->getRetryAfter());
            $this->assertStringContainsString('Rate limit reached', $e->getMessage());
        }
    }

    #[DataProvider('converterProvider')]
    public function testItThrowsServerExceptionOnServerError(ResultConverterInterface $converter)
    {
        $this->expectException(ServerException::class);

        $converter->convert($this->rawResult(['detail' => 'Internal error'], 503));
    }

    /**
     * A 5xx from a gateway in front of the API carries an HTML body, which must not surface
     * as a JSON decoding exception.
     */
    #[DataProvider('converterProvider')]
    public function testItThrowsServerExceptionOnNonJsonServerError(ResultConverterInterface $converter)
    {
        $this->expectException(ServerException::class);

        $httpClient = new MockHttpClient(new MockResponse('<html><body>502 Bad Gateway</body></html>', ['http_code' => 502]));

        $converter->convert(new RawHttpResult($httpClient->request('POST', 'https://api.edenai.run/v3/universal-ai')));
    }

    /**
     * The OpenAI-compatible endpoints keep the "error.message" shape, so both must be read.
     */
    #[DataProvider('converterProvider')]
    public function testItReadsOpenAiStyleErrorPayloads(ResultConverterInterface $converter)
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Model(s) not found or inactive: openai/nope');

        $converter->convert($this->rawResult([
            'error' => ['message' => 'Model(s) not found or inactive: openai/nope', 'type' => 'invalid_request_error'],
        ], 400));
    }

    #[DataProvider('converterProvider')]
    public function testItFallsBackWhenErrorBodyIsEmpty(ResultConverterInterface $converter)
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Bad Request');

        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 400]));

        $converter->convert(new RawHttpResult($httpClient->request('POST', 'https://api.edenai.run/v3/universal-ai')));
    }

    /**
     * FastAPI's default validation shape is what the published OpenAPI schema documents,
     * even though the API emits the "errors" shape instead. Both are parsed.
     */
    #[DataProvider('converterProvider')]
    public function testItReadsTheSchemaDocumentedValidationShape(ResultConverterInterface $converter)
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('body.model: field required');

        $converter->convert($this->rawResult([
            'detail' => [['loc' => ['body', 'model'], 'msg' => 'field required', 'type' => 'value_error.missing']],
        ], 422));
    }

    /**
     * A status nobody maps must still report the payload rather than a missing field.
     */
    #[DataProvider('converterProvider')]
    public function testItReportsUnmappedStatusCodes(ResultConverterInterface $converter)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 402 from Eden AI: "Insufficient credits"');

        $converter->convert($this->rawResult(['detail' => 'Insufficient credits'], 402));
    }

    /**
     * The asynchronous endpoint accepts a job with 202, so the speech-to-text converter
     * must not treat it as an error the way the synchronous ones would.
     */
    public function testSpeechToTextAcceptsAcceptedStatus()
    {
        $converter = new SpeechToText\ResultConverter();

        $result = $converter->convert($this->rawResult([
            'status' => 'success',
            'output' => ['text' => 'Accepted and finished.'],
        ], 202));

        $this->assertSame('Accepted and finished.', $result->getContent());
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     */
    private function rawResult(array $body, int $statusCode, array $headers = []): RawHttpResult
    {
        $options = ['http_code' => $statusCode];

        if ([] !== $headers) {
            $options['response_headers'] = $headers;
        }

        $httpClient = new MockHttpClient(new JsonMockResponse($body, $options));

        return new RawHttpResult($httpClient->request('POST', 'https://api.edenai.run/v3/universal-ai'));
    }
}

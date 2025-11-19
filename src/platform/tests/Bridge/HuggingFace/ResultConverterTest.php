<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\HuggingFace;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\ResultConverter;
use Symfony\AI\Platform\Bridge\HuggingFace\Task;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ResultConverterTest extends TestCase
{
    #[TestDox('Supports conversion for all models')]
    public function testSupports()
    {
        $converter = new ResultConverter();
        $model = new Model('test-model');
        $this->assertTrue($converter->supports($model));
    }

    #[TestDox('Throws RuntimeException when service is unavailable (503)')]
    public function testConvertWithServiceUnavailable()
    {
        $response = new MockResponse('Service unavailable', ['http_code' => 503]);
        $httpClient = new MockHttpClient($response);
        $httpResponse = $httpClient->request('GET', 'https://example.com');
        $result = new RawHttpResult($httpResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Service unavailable.');

        (new ResultConverter())->convert($result);
    }

    #[TestDox('Throws InvalidArgumentException when model not found (404)')]
    public function testConvertWithNotFound()
    {
        $response = new MockResponse('Not found', ['http_code' => 404]);
        $httpClient = new MockHttpClient($response);
        $httpResponse = $httpClient->request('GET', 'https://example.com');
        $result = new RawHttpResult($httpResponse);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model, provider or task not found (404).');

        (new ResultConverter())->convert($result);
    }

    #[TestDox('Throws InvalidArgumentException with string error content (4xx)')]
    public function testConvertWithClientErrorStringContent()
    {
        $response = new MockResponse('Bad request error', ['http_code' => 400]);
        $httpClient = new MockHttpClient($response);
        $httpResponse = $httpClient->request('GET', 'https://example.com');
        $result = new RawHttpResult($httpResponse);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('API Client Error (400): "Bad request error"');

        (new ResultConverter())->convert($result);
    }

    #[TestDox('Throws InvalidArgumentException with JSON array error content (4xx)')]
    public function testConvertWithClientErrorJsonArrayContent()
    {
        $errorData = ['error' => ['First error', 'Second error']];
        $response = new JsonMockResponse($errorData, ['http_code' => 400]);
        $httpClient = new MockHttpClient($response);
        $httpResponse = $httpClient->request('GET', 'https://example.com');
        $result = new RawHttpResult($httpResponse);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('API Client Error (400): "First error"');

        (new ResultConverter())->convert($result);
    }

    #[TestDox('Throws InvalidArgumentException with JSON string error content (4xx)')]
    public function testConvertWithClientErrorJsonStringContent()
    {
        $errorData = ['error' => 'Single error message'];
        $response = new JsonMockResponse($errorData, ['http_code' => 400]);
        $httpClient = new MockHttpClient($response);
        $httpResponse = $httpClient->request('GET', 'https://example.com');
        $result = new RawHttpResult($httpResponse);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('API Client Error (400): "Single error message"');

        (new ResultConverter())->convert($result);
    }

    #[TestDox('Throws RuntimeException for unhandled HTTP status codes')]
    public function testConvertWithUnhandledResponseCode()
    {
        $response = new MockResponse('Internal server error', ['http_code' => 500]);
        $httpClient = new MockHttpClient($response);
        $httpResponse = $httpClient->request('GET', 'https://example.com');
        $result = new RawHttpResult($httpResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unhandled response code: 500');

        (new ResultConverter())->convert($result);
    }

    #[TestDox('Throws RuntimeException for unsupported tasks')]
    public function testConvertWithUnsupportedTask()
    {
        $response = new JsonMockResponse([]);
        $httpClient = new MockHttpClient($response);
        $httpResponse = $httpClient->request('GET', 'https://example.com');
        $result = new RawHttpResult($httpResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported task: unsupported-task');

        (new ResultConverter())->convert($result, ['task' => 'unsupported-task']);
    }

    #[TestDox('Converts classification responses to ObjectResult')]
    #[TestWith([Task::AUDIO_CLASSIFICATION, [['label' => 'speech', 'score' => 0.9]]])]
    #[TestWith([Task::IMAGE_CLASSIFICATION, [['label' => 'cat', 'score' => 0.8]]])]
    public function testConvertClassificationTasks(string $task, array $responseData)
    {
        $response = new JsonMockResponse($responseData);
        $httpClient = new MockHttpClient($response);
        $httpResponse = $httpClient->request('GET', 'https://example.com');
        $result = new RawHttpResult($httpResponse);

        $convertedResult = (new ResultConverter())->convert($result, ['task' => $task]);

        $this->assertInstanceOf(ObjectResult::class, $convertedResult);
    }

    #[TestDox('Converts text-generating tasks to TextResult with correct content')]
    #[TestWith([Task::AUTOMATIC_SPEECH_RECOGNITION, ['text' => 'Hello world'], 'Hello world'])]
    #[TestWith([Task::CHAT_COMPLETION, ['choices' => [['message' => ['content' => 'Hello there']]]], 'Hello there'])]
    #[TestWith([Task::TEXT_GENERATION, [['generated_text' => 'Once upon a time']], 'Once upon a time'])]
    #[TestWith([Task::IMAGE_TO_TEXT, [['generated_text' => 'A cat sitting on a table']], 'A cat sitting on a table'])]
    #[TestWith([Task::SUMMARIZATION, [['summary_text' => 'This is a summary']], 'This is a summary'])]
    #[TestWith([Task::TRANSLATION, [['translation_text' => 'Bonjour le monde']], 'Bonjour le monde'])]
    public function testConvertTextGeneratingTasks(string $task, array $responseData, string $expectedValue)
    {
        $response = new JsonMockResponse($responseData);
        $httpClient = new MockHttpClient($response);
        $httpResponse = $httpClient->request('GET', 'https://example.com');
        $result = new RawHttpResult($httpResponse);

        $convertedResult = (new ResultConverter())->convert($result, ['task' => $task]);

        $this->assertInstanceOf(TextResult::class, $convertedResult);
        $this->assertSame($expectedValue, $convertedResult->getContent());
    }

    #[TestDox('Converts feature extraction response to VectorResult with correct data')]
    public function testConvertFeatureExtraction()
    {
        $responseData = [0.1, 0.2, 0.3];
        $response = new JsonMockResponse($responseData);
        $httpClient = new MockHttpClient($response);
        $httpResponse = $httpClient->request('GET', 'https://example.com');
        $result = new RawHttpResult($httpResponse);

        $convertedResult = (new ResultConverter())->convert($result, ['task' => Task::FEATURE_EXTRACTION]);

        $this->assertInstanceOf(VectorResult::class, $convertedResult);
        $vectors = $convertedResult->getContent();
        $this->assertCount(1, $vectors);
        $this->assertEquals([0.1, 0.2, 0.3], $vectors[0]->getData());
    }

    #[TestDox('Converts various tasks to ObjectResult')]
    #[TestWith([Task::FILL_MASK, [['token_str' => 'world', 'token' => 12345, 'score' => 0.8, 'sequence' => 'Hello world']]])]
    #[TestWith([Task::IMAGE_SEGMENTATION, [['label' => 'person', 'mask' => 'mask_data', 'score' => 0.9]]])]
    #[TestWith([Task::OBJECT_DETECTION, [['label' => 'person', 'box' => ['xmin' => 0, 'ymin' => 0, 'xmax' => 100, 'ymax' => 100], 'score' => 0.95]]])]
    #[TestWith([Task::TOKEN_CLASSIFICATION, [['entity_group' => 'PERSON', 'word' => 'John', 'start' => 0, 'end' => 4, 'score' => 0.99]]])]
    #[TestWith([Task::QUESTION_ANSWERING, ['answer' => 'Paris', 'score' => 0.9, 'start' => 0, 'end' => 5]])]
    #[TestWith([Task::SENTENCE_SIMILARITY, [0.8]])]
    #[TestWith([Task::TABLE_QUESTION_ANSWERING, ['answer' => '42', 'coordinates' => [[0, 0]]]])]
    #[TestWith([Task::ZERO_SHOT_CLASSIFICATION, ['sequence' => 'Hello', 'labels' => ['greeting', 'farewell'], 'scores' => [0.9, 0.1]]])]
    public function testConvertTasksToObjectResult(string $task, array $responseData)
    {
        $response = new JsonMockResponse($responseData);
        $httpClient = new MockHttpClient($response);
        $httpResponse = $httpClient->request('GET', 'https://example.com');
        $result = new RawHttpResult($httpResponse);

        $convertedResult = (new ResultConverter())->convert($result, ['task' => $task]);

        $this->assertInstanceOf(ObjectResult::class, $convertedResult);
    }

    #[TestDox('Converts text-to-image response to BinaryResult with correct MIME type')]
    public function testConvertWithTextToImageTask()
    {
        $binaryContent = 'fake-image-data';
        $response = new MockResponse($binaryContent, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'image/png'],
        ]);
        $httpClient = new MockHttpClient($response);
        $httpResponse = $httpClient->request('GET', 'https://example.com');
        $result = new RawHttpResult($httpResponse);

        $convertedResult = (new ResultConverter())->convert($result, ['task' => Task::TEXT_TO_IMAGE]);

        $this->assertInstanceOf(BinaryResult::class, $convertedResult);
        $this->assertSame($binaryContent, $convertedResult->getContent());
        $this->assertSame('image/png', $convertedResult->getMimeType());
    }
}

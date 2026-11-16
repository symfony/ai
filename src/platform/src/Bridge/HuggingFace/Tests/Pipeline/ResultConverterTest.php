<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Tests\Pipeline;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Classification;
use Symfony\AI\Platform\Bridge\HuggingFace\Classification\ResultConverter as ClassificationResultConverter;
use Symfony\AI\Platform\Bridge\HuggingFace\Embeddings;
use Symfony\AI\Platform\Bridge\HuggingFace\Embeddings\ResultConverter as EmbeddingsResultConverter;
use Symfony\AI\Platform\Bridge\HuggingFace\FillMask;
use Symfony\AI\Platform\Bridge\HuggingFace\FillMask\ResultConverter as FillMaskResultConverter;
use Symfony\AI\Platform\Bridge\HuggingFace\ImageSegmentation;
use Symfony\AI\Platform\Bridge\HuggingFace\ImageSegmentation\ResultConverter as ImageSegmentationResultConverter;
use Symfony\AI\Platform\Bridge\HuggingFace\ObjectDetection;
use Symfony\AI\Platform\Bridge\HuggingFace\ObjectDetection\ResultConverter as ObjectDetectionResultConverter;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ClassificationResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\FillMaskResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ImageSegmentationResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ObjectDetectionResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\QuestionAnsweringResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\TableQuestionAnsweringResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\TokenClassificationResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ZeroShotClassificationResult;
use Symfony\AI\Platform\Bridge\HuggingFace\QuestionAnswering;
use Symfony\AI\Platform\Bridge\HuggingFace\QuestionAnswering\ResultConverter as QuestionAnsweringResultConverter;
use Symfony\AI\Platform\Bridge\HuggingFace\Reranker;
use Symfony\AI\Platform\Bridge\HuggingFace\Reranker\ResultConverter as RerankerResultConverter;
use Symfony\AI\Platform\Bridge\HuggingFace\SpeechRecognition;
use Symfony\AI\Platform\Bridge\HuggingFace\SpeechRecognition\ResultConverter as SpeechRecognitionResultConverter;
use Symfony\AI\Platform\Bridge\HuggingFace\Summarization;
use Symfony\AI\Platform\Bridge\HuggingFace\Summarization\ResultConverter as SummarizationResultConverter;
use Symfony\AI\Platform\Bridge\HuggingFace\TableQuestionAnswering;
use Symfony\AI\Platform\Bridge\HuggingFace\TableQuestionAnswering\ResultConverter as TableQuestionAnsweringResultConverter;
use Symfony\AI\Platform\Bridge\HuggingFace\TextToImage;
use Symfony\AI\Platform\Bridge\HuggingFace\TextToImage\ResultConverter as TextToImageResultConverter;
use Symfony\AI\Platform\Bridge\HuggingFace\TokenClassification;
use Symfony\AI\Platform\Bridge\HuggingFace\TokenClassification\ResultConverter as TokenClassificationResultConverter;
use Symfony\AI\Platform\Bridge\HuggingFace\Translation;
use Symfony\AI\Platform\Bridge\HuggingFace\Translation\ResultConverter as TranslationResultConverter;
use Symfony\AI\Platform\Bridge\HuggingFace\ZeroShotClassification;
use Symfony\AI\Platform\Bridge\HuggingFace\ZeroShotClassification\ResultConverter as ZeroShotClassificationResultConverter;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ResultConverterTest extends TestCase
{
    /**
     * @return iterable<string, array{ResultConverterInterface, Model}>
     */
    public static function converterProvider(): iterable
    {
        yield 'classification' => [new ClassificationResultConverter(), new Classification('test/model')];
        yield 'embeddings' => [new EmbeddingsResultConverter(), new Embeddings('test/model')];
        yield 'fill-mask' => [new FillMaskResultConverter(), new FillMask('test/model')];
        yield 'image-segmentation' => [new ImageSegmentationResultConverter(), new ImageSegmentation('test/model')];
        yield 'object-detection' => [new ObjectDetectionResultConverter(), new ObjectDetection('test/model')];
        yield 'question-answering' => [new QuestionAnsweringResultConverter(), new QuestionAnswering('test/model')];
        yield 'reranker' => [new RerankerResultConverter(), new Reranker('test/model')];
        yield 'speech-recognition' => [new SpeechRecognitionResultConverter(), new SpeechRecognition('test/model')];
        yield 'summarization' => [new SummarizationResultConverter(), new Summarization('test/model')];
        yield 'table-question-answering' => [new TableQuestionAnsweringResultConverter(), new TableQuestionAnswering('test/model')];
        yield 'text-to-image' => [new TextToImageResultConverter(), new TextToImage('test/model')];
        yield 'token-classification' => [new TokenClassificationResultConverter(), new TokenClassification('test/model')];
        yield 'translation' => [new TranslationResultConverter(), new Translation('test/model')];
        yield 'zero-shot-classification' => [new ZeroShotClassificationResultConverter(), new ZeroShotClassification('test/model')];
    }

    #[DataProvider('converterProvider')]
    public function testSupportsOnlyItsOwnModel(ResultConverterInterface $converter, Model $model)
    {
        $this->assertTrue($converter->supports($model));

        foreach (self::converterProvider() as [, $otherModel]) {
            if ($otherModel::class !== $model::class) {
                $this->assertFalse($converter->supports($otherModel), \sprintf('Converter for "%s" must not support "%s".', $model::class, $otherModel::class));
            }
        }
    }

    /**
     * @return iterable<string, array{ResultConverterInterface, array<mixed>, class-string<ObjectResult|TextResult>, class-string|string}>
     */
    public static function jsonResponseProvider(): iterable
    {
        yield 'image classification' => [new ClassificationResultConverter(), [['label' => 'cat', 'score' => 0.8]], ObjectResult::class, ClassificationResult::class];
        yield 'text classification' => [new ClassificationResultConverter(), [[['label' => 'positive', 'score' => 0.9]]], ObjectResult::class, ClassificationResult::class];
        yield 'fill mask' => [new FillMaskResultConverter(), [['token_str' => 'world', 'token' => 12345, 'score' => 0.8, 'sequence' => 'Hello world']], ObjectResult::class, FillMaskResult::class];
        yield 'image segmentation' => [new ImageSegmentationResultConverter(), [['label' => 'person', 'mask' => 'mask_data', 'score' => 0.9]], ObjectResult::class, ImageSegmentationResult::class];
        yield 'object detection' => [new ObjectDetectionResultConverter(), [['label' => 'person', 'box' => ['xmin' => 0, 'ymin' => 0, 'xmax' => 100, 'ymax' => 100], 'score' => 0.95]], ObjectResult::class, ObjectDetectionResult::class];
        yield 'question answering' => [new QuestionAnsweringResultConverter(), ['answer' => 'Paris', 'score' => 0.9, 'start' => 0, 'end' => 5], ObjectResult::class, QuestionAnsweringResult::class];
        yield 'table question answering' => [new TableQuestionAnsweringResultConverter(), ['answer' => '42', 'coordinates' => [[0, 0]]], ObjectResult::class, TableQuestionAnsweringResult::class];
        yield 'token classification' => [new TokenClassificationResultConverter(), [['entity_group' => 'PERSON', 'word' => 'John', 'start' => 0, 'end' => 4, 'score' => 0.99]], ObjectResult::class, TokenClassificationResult::class];
        yield 'zero-shot classification' => [new ZeroShotClassificationResultConverter(), ['sequence' => 'Hello', 'labels' => ['greeting', 'farewell'], 'scores' => [0.9, 0.1]], ObjectResult::class, ZeroShotClassificationResult::class];
        yield 'speech recognition' => [new SpeechRecognitionResultConverter(), ['text' => 'Hello world'], TextResult::class, 'Hello world'];
        yield 'summarization' => [new SummarizationResultConverter(), [['summary_text' => 'This is a summary']], TextResult::class, 'This is a summary'];
        yield 'translation' => [new TranslationResultConverter(), [['translation_text' => 'Bonjour le monde']], TextResult::class, 'Bonjour le monde'];
    }

    /**
     * @param array<mixed>                          $responseData
     * @param class-string<ObjectResult|TextResult> $expectedResultClass
     * @param class-string|string                   $expectedContent     Output class for object results, text for text results
     */
    #[DataProvider('jsonResponseProvider')]
    public function testConvertsJsonResponse(ResultConverterInterface $converter, array $responseData, string $expectedResultClass, string $expectedContent)
    {
        $result = $converter->convert(self::createResult(new JsonMockResponse($responseData)));

        $this->assertInstanceOf($expectedResultClass, $result);
        if ($result instanceof ObjectResult) {
            $this->assertInstanceOf($expectedContent, $result->getContent());
        } else {
            $this->assertSame($expectedContent, $result->getContent());
        }
    }

    public function testClassificationUnwrapsTheLabelsOfTextClassification()
    {
        $converter = new ClassificationResultConverter();

        $result = $converter->convert(self::createResult(new JsonMockResponse([[['label' => 'positive', 'score' => 0.9], ['label' => 'negative', 'score' => 0.1]]])));

        $classification = $result->getContent();
        $this->assertInstanceOf(ClassificationResult::class, $classification);
        $this->assertCount(2, $classification->getClassifications());
        $this->assertSame('positive', $classification->getClassifications()[0]->getLabel());
    }

    public function testEmbeddingsConvertsSingleVector()
    {
        $converter = new EmbeddingsResultConverter();

        $result = $converter->convert(self::createResult(new JsonMockResponse([0.1, 0.2, 0.3])));

        $this->assertInstanceOf(VectorResult::class, $result);
        $this->assertCount(1, $result->getContent());
        $this->assertSame([0.1, 0.2, 0.3], $result->getContent()[0]->getData());
    }

    public function testEmbeddingsConvertsBatchOfVectors()
    {
        $converter = new EmbeddingsResultConverter();

        $result = $converter->convert(self::createResult(new JsonMockResponse([[0.1, 0.2], [0.3, 0.4]])));

        $this->assertCount(2, $result->getContent());
        $this->assertSame([0.3, 0.4], $result->getContent()[1]->getData());
    }

    public function testRerankerConvertsScoresByIndex()
    {
        $converter = new RerankerResultConverter();

        $result = $converter->convert(self::createResult(new JsonMockResponse([
            ['score' => 9.18, 'index' => 0],
            ['score' => -11.34, 'index' => 1],
        ])));

        $this->assertInstanceOf(RerankingResult::class, $result);
        $entries = $result->getContent();
        $this->assertCount(2, $entries);
        $this->assertSame(0, $entries[0]->getIndex());
        $this->assertSame(9.18, $entries[0]->getScore());
        $this->assertSame(1, $entries[1]->getIndex());
        $this->assertSame(-11.34, $entries[1]->getScore());
    }

    public function testTextToImageConvertsBinaryResponse()
    {
        $converter = new TextToImageResultConverter();

        $result = $converter->convert(self::createResult(new MockResponse('fake-image-data', [
            'response_headers' => ['Content-Type' => 'image/png'],
        ])));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('fake-image-data', $result->getContent());
        $this->assertSame('image/png', $result->getMimeType());
    }

    /**
     * @return iterable<string, array{int, mixed, class-string<\Throwable>, string}>
     */
    public static function errorResponseProvider(): iterable
    {
        yield 'plain string error' => [400, ['error' => 'Model not supported by provider hf-inference'], BadRequestException::class, 'Model not supported by provider hf-inference'];
        yield 'list of errors' => [400, ['error' => ['Input is too long']], BadRequestException::class, 'Input is too long'];
        yield 'unauthorized' => [401, ['error' => 'Invalid username or password.'], AuthenticationException::class, 'Invalid username or password.'];
        yield 'not found' => [404, ['error' => 'Model does/not-exist does not exist'], ModelNotFoundException::class, 'Model does/not-exist does not exist'];
        yield 'rate limit' => [429, ['error' => 'Too many requests'], RateLimitExceededException::class, 'Too many requests'];
        yield 'model loading' => [503, ['error' => 'Model is currently loading'], ServerException::class, 'Model is currently loading'];
        yield 'unexpected status' => [422, ['error' => 'Unprocessable input'], RuntimeException::class, 'Unexpected response code 422: "Unprocessable input"'];
    }

    /**
     * @param class-string<\Throwable> $expectedException
     */
    #[DataProvider('errorResponseProvider')]
    public function testThrowsOnErrorResponse(int $statusCode, mixed $body, string $expectedException, string $expectedMessage)
    {
        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedMessage);

        (new SummarizationResultConverter())->convert(self::createResult(new JsonMockResponse($body, ['http_code' => $statusCode])));
    }

    /**
     * @return iterable<string, array{ResultConverterInterface, array<mixed>, string}>
     */
    public static function unexpectedTextResponseProvider(): iterable
    {
        yield 'speech recognition' => [new SpeechRecognitionResultConverter(), ['chunks' => []], 'Response does not contain transcription text.'];
        yield 'summarization' => [new SummarizationResultConverter(), [['generated_text' => 'This is a summary']], 'Response does not contain summary text.'];
        yield 'summarization without entries' => [new SummarizationResultConverter(), [], 'Response does not contain summary text.'];
        yield 'translation' => [new TranslationResultConverter(), [['generated_text' => 'Bonjour le monde']], 'Response does not contain translation text.'];
        yield 'translation without entries' => [new TranslationResultConverter(), [], 'Response does not contain translation text.'];
    }

    /**
     * @param array<mixed> $responseData
     */
    #[DataProvider('unexpectedTextResponseProvider')]
    public function testThrowsOnUnexpectedTextResponse(ResultConverterInterface $converter, array $responseData, string $expectedMessage)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);

        $converter->convert(self::createResult(new JsonMockResponse($responseData)));
    }

    public function testTextToImageThrowsOnErrorResponse()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Prompt is required');

        (new TextToImageResultConverter())->convert(self::createResult(new JsonMockResponse(['error' => 'Prompt is required'], ['http_code' => 400])));
    }

    private static function createResult(MockResponse $response): RawHttpResult
    {
        return new RawHttpResult((new MockHttpClient($response))->request('POST', 'https://router.huggingface.co/hf-inference/models/test/model'));
    }
}

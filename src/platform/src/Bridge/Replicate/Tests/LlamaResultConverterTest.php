<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Replicate\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Bridge\Replicate\LlamaResultConverter;
use Symfony\AI\Platform\Bridge\Replicate\ReplicateJobClient;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class LlamaResultConverterTest extends TestCase
{
    public function testSupportsLlamaModel()
    {
        $converter = self::createConverter();
        $this->assertTrue($converter->supports(new Llama('llama-3.1-405b-instruct')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $converter = self::createConverter();
        $otherModel = $this->createMock(Model::class);
        $this->assertFalse($converter->supports($otherModel));
    }

    public function testConvertReturnsAJobHandleForTheCreatedPrediction()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getData')->willReturn(['id' => 'pred-123', 'status' => 'starting']);

        $result = self::createConverter()->convert($rawResult);

        $this->assertInstanceOf(JobResult::class, $result);
        $this->assertSame('pred-123', $result->getContent()->getId());
        $this->assertSame('replicate', $result->getContent()->getProvider());
        $this->assertSame(ReplicateJobClient::DEFAULT_MAX_DURATION, $result->getContent()->getMaxDuration());
        $this->assertSame(ReplicateJobClient::DEFAULT_POLL_INTERVAL, $result->getContent()->getPollInterval());
        $this->assertSame('v1/predictions/pred-123', $result->getContent()->get('prediction_path'));
    }

    public function testConvertNamesTheProviderTheJobClientServes()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getData')->willReturn(['id' => 'pred-123']);

        $result = self::createConverter('my-replicate')->convert($rawResult);

        $this->assertInstanceOf(JobResult::class, $result);
        $this->assertSame('my-replicate', $result->getContent()->getProvider());
    }

    public function testConvertThrowsExceptionWhenPredictionIdentifierMissing()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $rawResult->method('getData')->willReturn(['detail' => 'Invalid input']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Replicate API error: "Invalid input".');

        self::createConverter()->convert($rawResult);
    }

    public function testConvertThrowsOnHttpError()
    {
        $mockResponse = new MockResponse('{"detail": "Invalid version or not permitted"}', ['http_code' => 404]);
        $httpClient = new MockHttpClient($mockResponse);
        $response = $httpClient->request('POST', 'https://api.replicate.com/test');
        $rawResult = new RawHttpResult($response);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Replicate API error (HTTP 404)');

        self::createConverter()->convert($rawResult);
    }

    private static function createConverter(string $name = 'replicate'): LlamaResultConverter
    {
        return new LlamaResultConverter($name);
    }
}

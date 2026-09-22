<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Tests\Video;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenRouter\Video\JobClient;
use Symfony\AI\Platform\Bridge\OpenRouter\Video\ResultConverter;
use Symfony\AI\Platform\Bridge\OpenRouter\VideoGenerationModel;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class ResultConverterTest extends TestCase
{
    public function testSupportsModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new VideoGenerationModel('google/veo-3.1')));
        $this->assertFalse($converter->supports(new Model('any-model')));
    }

    public function testConvertsSubmissionIntoJobResult()
    {
        $converter = new ResultConverter('my-openrouter');
        $result = $converter->convert($this->createRawResult(['id' => 'job-123', 'status' => 'pending']));

        $handle = $result->getContent();

        $this->assertSame('job-123', $handle->getId());
        $this->assertSame('my-openrouter', $handle->getProvider());
        $this->assertSame(ResultConverter::MAX_DURATION, $handle->getMaxDuration());
        $this->assertTrue((new JobClient(new MockHttpClient()))->supports($handle));
    }

    public function testThrowsWhenNoJobIdIsReturned()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The video generation request did not return a job ID.');

        (new ResultConverter())->convert($this->createRawResult(['status' => 'pending']));
    }

    public function testTokenUsageExtractorIsNull()
    {
        $this->assertNull((new ResultConverter())->getTokenUsageExtractor());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createRawResult(array $data): RawHttpResult
    {
        $httpClient = new MockHttpClient([new JsonMockResponse($data, ['http_code' => 202])]);

        return new RawHttpResult($httpClient->request('POST', 'https://openrouter.ai/api/v1/videos'));
    }
}

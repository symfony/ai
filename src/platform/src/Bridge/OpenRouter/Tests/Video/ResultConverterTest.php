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
use Symfony\AI\Platform\Bridge\OpenRouter\Video\ResultConverter;
use Symfony\AI\Platform\Bridge\OpenRouter\VideoGenerationModel;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

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

    public function testConvertsBinaryVideoResponse()
    {
        $videoContent = file_get_contents(\dirname(__DIR__, 7).'/fixtures/ocean.mp4');

        $mockHttpClient = new MockHttpClient([
            new MockResponse($videoContent, ['response_headers' => ['content-type' => 'video/mp4']]),
        ]);

        $response = $mockHttpClient->request('GET', 'https://example.com/video.mp4');

        $converter = new ResultConverter();
        $result = $converter->convert(new RawHttpResult($response));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame($videoContent, $result->getContent());
        $this->assertSame('video/mp4', $result->getMimeType());
    }

    public function testTokenUsageExtractorIsNull()
    {
        $this->assertNull((new ResultConverter())->getTokenUsageExtractor());
    }
}

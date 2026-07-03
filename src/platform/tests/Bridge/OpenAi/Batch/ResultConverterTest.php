<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi\Batch;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Batch\BatchJobResult;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\OpenAi\Batch\ResultConverter;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class ResultConverterTest extends TestCase
{
    public function testSupportsGptModel()
    {
        $this->assertTrue((new ResultConverter())->supports(new Gpt('gpt-4o-mini', [Capability::BATCH])));
    }

    public function testDoesNotSupportNonGptModel()
    {
        $this->assertFalse((new ResultConverter())->supports(new Claude(Claude::SONNET_4)));
    }

    public function testConvertBuildsBatchJobResult()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'batch_xyz',
            'status' => 'validating',
            'request_counts' => ['total' => 2, 'completed' => 0, 'failed' => 0],
        ])));
        $response = $httpClient->request('GET', 'https://example.test');

        $result = (new ResultConverter())->convert(new RawHttpResult($response));

        $this->assertInstanceOf(BatchJobResult::class, $result);
        $job = $result->getContent();
        $this->assertSame('batch_xyz', $job->getId());
        $this->assertTrue($job->isProcessing());
        $this->assertSame(2, $job->getTotalCount());
    }

    public function testHasNoTokenUsageExtractor()
    {
        $this->assertNull((new ResultConverter())->getTokenUsageExtractor());
    }
}

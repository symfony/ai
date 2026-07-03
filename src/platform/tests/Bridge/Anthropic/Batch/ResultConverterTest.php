<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Anthropic\Batch;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Batch\BatchJobResult;
use Symfony\AI\Platform\Bridge\Anthropic\Batch\ResultConverter;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
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
    public function testSupportsClaudeModel()
    {
        $this->assertTrue((new ResultConverter())->supports(new Claude(Claude::SONNET_4, [Capability::BATCH])));
    }

    public function testDoesNotSupportNonClaudeModel()
    {
        $this->assertFalse((new ResultConverter())->supports(new Gpt('gpt-4o')));
    }

    public function testConvertBuildsBatchJobResult()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'msgbatch_abc',
            'processing_status' => 'in_progress',
            'request_counts' => ['processing' => 2, 'succeeded' => 0, 'errored' => 0, 'canceled' => 0, 'expired' => 0],
        ])));
        $response = $httpClient->request('GET', 'https://example.test');

        $result = (new ResultConverter())->convert(new RawHttpResult($response));

        $this->assertInstanceOf(BatchJobResult::class, $result);
        $this->assertSame('msgbatch_abc', $result->getContent()->getId());
        $this->assertTrue($result->getContent()->isProcessing());
    }
}

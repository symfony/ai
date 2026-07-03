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
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\OpenAi\Batch\SubmitModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class SubmitModelClientTest extends TestCase
{
    public function testSupportsGptModel()
    {
        $client = new SubmitModelClient(new MockHttpClient(), 'sk-test-key');

        $this->assertTrue($client->supports(new Gpt('gpt-4o-mini', [Capability::BATCH])));
    }

    public function testDoesNotSupportNonGptModel()
    {
        $client = new SubmitModelClient(new MockHttpClient(), 'sk-test-key');

        $this->assertFalse($client->supports(new Claude(Claude::SONNET_4)));
    }

    public function testSubmitBatchUploadsFileAndCreatesBatch()
    {
        $calls = 0;
        $capturedUrls = [];
        $capturedBatchBody = null;

        $httpClient = new MockHttpClient(static function ($method, $url, $options) use (&$calls, &$capturedUrls, &$capturedBatchBody) {
            ++$calls;
            $capturedUrls[] = $url;

            if (1 === $calls) {
                return new MockResponse(json_encode(['id' => 'file-abc123']));
            }

            $capturedBatchBody = json_decode($options['body'], true);

            return new MockResponse(json_encode([
                'id' => 'batch_xyz789',
                'status' => 'validating',
                'request_counts' => ['total' => 2, 'completed' => 0, 'failed' => 0],
            ]));
        });

        $client = new SubmitModelClient($httpClient, 'sk-test-key');

        $raw = $client->submitBatch(new Gpt('gpt-4o-mini'), [
            ['id' => 'req-1', 'payload' => ['input' => [['role' => 'user', 'content' => 'Hello']]]],
            ['id' => 'req-2', 'payload' => ['input' => [['role' => 'user', 'content' => 'World']]]],
        ]);

        $this->assertSame(2, $calls);
        $this->assertStringEndsWith('/v1/files', $capturedUrls[0]);
        $this->assertStringEndsWith('/v1/batches', $capturedUrls[1]);
        $this->assertSame('file-abc123', $capturedBatchBody['input_file_id']);
        $this->assertSame('/v1/responses', $capturedBatchBody['endpoint']);
        $this->assertSame('batch_xyz789', $raw->getData()['id']);
    }

    public function testSubmitEmptyBatchThrows()
    {
        $client = new SubmitModelClient(new MockHttpClient(), 'sk-test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot submit an empty batch.');

        $client->submitBatch(new Gpt('gpt-4o-mini'), []);
    }

    public function testSubmitBatchThrowsWhenUploadHasNoFileId()
    {
        $httpClient = new MockHttpClient(static fn () => new MockResponse(json_encode(['error' => 'nope'])));
        $client = new SubmitModelClient($httpClient, 'sk-test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing file ID');

        $client->submitBatch(new Gpt('gpt-4o-mini'), [
            ['id' => 'req-1', 'payload' => ['input' => [['role' => 'user', 'content' => 'Hello']]]],
        ]);
    }
}

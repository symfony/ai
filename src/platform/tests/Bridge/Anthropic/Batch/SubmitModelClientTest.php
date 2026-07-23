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
use Symfony\AI\Platform\Bridge\Anthropic\Batch\SubmitModelClient;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Capability;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class SubmitModelClientTest extends TestCase
{
    public function testSupportsClaudeModel()
    {
        $client = new SubmitModelClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new Claude(Claude::SONNET_4, [Capability::BATCH])));
    }

    public function testDoesNotSupportNonClaudeModel()
    {
        $client = new SubmitModelClient(new MockHttpClient(), 'test-key');

        $this->assertFalse($client->supports(new Gpt('gpt-4o')));
    }

    public function testSubmitBatchSendsCorrectRequest()
    {
        $capturedUrl = null;
        $capturedBody = null;

        $httpClient = new MockHttpClient(function ($method, $url, $options) use (&$capturedUrl, &$capturedBody) {
            $capturedUrl = $url;
            $capturedBody = json_decode($this->readBody($options['body']), true);

            return new MockResponse(json_encode([
                'id' => 'msgbatch_abc123',
                'processing_status' => 'in_progress',
                'request_counts' => ['processing' => 2, 'succeeded' => 0, 'errored' => 0, 'canceled' => 0, 'expired' => 0],
            ]));
        });

        $client = new SubmitModelClient($httpClient, 'test-key');

        $raw = $client->submitBatch(new Claude(Claude::SONNET_4), [
            ['id' => 'req-1', 'payload' => ['messages' => [['role' => 'user', 'content' => 'Hello']]]],
            ['id' => 'req-2', 'payload' => ['messages' => [['role' => 'user', 'content' => 'World']]]],
        ]);

        $this->assertStringEndsWith('/v1/messages/batches', $capturedUrl);
        $this->assertCount(2, $capturedBody['requests']);
        $this->assertSame('req-1', $capturedBody['requests'][0]['custom_id']);
        $this->assertSame('req-2', $capturedBody['requests'][1]['custom_id']);
        $this->assertSame(Claude::SONNET_4, $capturedBody['requests'][0]['params']['model']);
        $this->assertSame('msgbatch_abc123', $raw->getData()['id']);
    }

    private function readBody(mixed $body): string
    {
        if (\is_string($body)) {
            return $body;
        }

        if ($body instanceof \Traversable) {
            return implode('', iterator_to_array($body));
        }

        $chunks = [];
        while ('' !== ($chunk = $body(1024))) {
            $chunks[] = $chunk;
        }

        return implode('', $chunks);
    }
}

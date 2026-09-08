<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Test\Replay;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Test\Replay\HttpCassette;

final class HttpCassetteTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/ai-cassette-'.bin2hex(random_bytes(6)).'.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testExistsReflectsTheFile()
    {
        $cassette = new HttpCassette($this->path);
        $this->assertFalse($cassette->exists());

        $cassette->record('POST', 'https://example.com', [], 200, [], '{}');
        $this->assertTrue($cassette->exists());
    }

    public function testRecordRedactsSensitiveHeadersAndKeepsBody()
    {
        $cassette = new HttpCassette($this->path);
        $cassette->record(
            'POST',
            'https://api.mistral.ai/v1/chat/completions',
            [
                'auth_bearer' => 'sk-secret',
                'headers' => [
                    'Authorization' => 'Bearer sk-secret',
                    'X-Subscription-Token' => 'sk-secret',
                    'X-Amz-Security-Token' => 'session-secret',
                    'X-Amz-Sso_Bearer_Token' => 'sso-secret',
                    'Anthropic-Workspace' => 'workspace-secret',
                    'Content-Type' => 'application/json',
                ],
                'json' => ['model' => 'mistral-large-latest'],
            ],
            200,
            ['content-type' => ['application/json']],
            '{"ok":true}',
        );

        $data = json_decode((string) file_get_contents($this->path), true, flags: \JSON_THROW_ON_ERROR);
        $request = $data['interactions'][0]['request'];

        $this->assertSame('POST', $request['method']);
        $this->assertSame(['[redacted]'], $request['headers']['authorization']);
        $this->assertSame(['[redacted]'], $request['headers']['x-subscription-token']);
        $this->assertSame(['[redacted]'], $request['headers']['x-amz-security-token']);
        $this->assertSame(['[redacted]'], $request['headers']['x-amz-sso_bearer_token']);
        $this->assertSame(['[redacted]'], $request['headers']['anthropic-workspace']);
        $this->assertSame(['application/json'], $request['headers']['content-type']);
        $this->assertSame(['model' => 'mistral-large-latest'], $request['body']);
        $this->assertArrayHasKey('signature', $request);

        $serialized = (string) file_get_contents($this->path);
        $this->assertStringNotContainsString('sk-secret', $serialized);
        $this->assertStringNotContainsString('session-secret', $serialized);
        $this->assertStringNotContainsString('sso-secret', $serialized);
        $this->assertStringNotContainsString('workspace-secret', $serialized);
    }

    public function testRecordRedactsSecretsPassedAsHeaderLinesAndResponseHeaders()
    {
        $cassette = new HttpCassette($this->path);
        $cassette->record(
            'POST',
            'https://generativelanguage.googleapis.com/v1beta/models/gemini:generateContent',
            ['headers' => ['x-goog-api-key: super-secret', 'Content-Type: application/json']],
            200,
            ['content-type' => ['application/json'], 'set-cookie' => ['session=leaky']],
            '{"ok":true}',
        );

        $data = json_decode((string) file_get_contents($this->path), true, flags: \JSON_THROW_ON_ERROR);
        $interaction = $data['interactions'][0];

        $this->assertSame(['[redacted]'], $interaction['request']['headers']['x-goog-api-key']);
        $this->assertSame(['[redacted]'], $interaction['response']['headers']['set-cookie']);

        $serialized = (string) file_get_contents($this->path);
        $this->assertStringNotContainsString('super-secret', $serialized);
        $this->assertStringNotContainsString('session=leaky', $serialized);
    }

    public function testRecordDropsTraceHeadersButKeepsRateLimitHeaders()
    {
        $cassette = new HttpCassette($this->path);
        $cassette->record(
            'POST',
            'https://api.mistral.ai/v1/chat/completions',
            [],
            429,
            [
                'content-type' => ['application/json'],
                'date' => ['Sat, 15 Aug 2026 19:03:05 GMT'],
                'cf-ray' => ['a2ba750efb21d8e2-VIE'],
                'mistral-correlation-id' => ['01a006ce-6575-7811-a112-202834b45bfc'],
                'x-envoy-upstream-service-time' => ['963'],
                'retry-after' => ['12'],
                'x-ratelimit-remaining-req-minute' => ['0'],
            ],
            '{"message":"rate limited"}',
        );

        $data = json_decode((string) file_get_contents($this->path), true, flags: \JSON_THROW_ON_ERROR);
        $headers = $data['interactions'][0]['response']['headers'];

        $this->assertArrayNotHasKey('date', $headers);
        $this->assertArrayNotHasKey('cf-ray', $headers);
        $this->assertArrayNotHasKey('mistral-correlation-id', $headers);
        $this->assertArrayNotHasKey('x-envoy-upstream-service-time', $headers);

        // read by the converters, so they have to survive
        $this->assertSame(['12'], $headers['retry-after']);
        $this->assertSame(['0'], $headers['x-ratelimit-remaining-req-minute']);
        $this->assertSame(['application/json'], $headers['content-type']);
    }

    public function testRecordStoresBodyFormat()
    {
        $cassette = new HttpCassette($this->path);
        $cassette->record('POST', 'https://example.com', [], 200, [], "data: {}\n\n", 'sse');

        $data = json_decode((string) file_get_contents($this->path), true, flags: \JSON_THROW_ON_ERROR);

        $this->assertSame('sse', $data['interactions'][0]['response']['body_format']);
    }

    public function testRecordElidesBinaryBodyToMetadataStub()
    {
        $cassette = new HttpCassette($this->path);
        $cassette->record('GET', 'https://example.com/speech', [], 200, ['content-type' => ['audio/mpeg']], "\x00\x01binary", 'binary');

        $data = json_decode((string) file_get_contents($this->path), true, flags: \JSON_THROW_ON_ERROR);

        $this->assertSame('binary', $data['interactions'][0]['response']['body_format']);
        $this->assertNull($data['interactions'][0]['response']['body']);
        $this->assertSame(8, $data['interactions'][0]['response']['body_size']);
        $this->assertSame(['audio/mpeg'], $data['interactions'][0]['response']['headers']['content-type']);
    }

    public function testNextReturnsInteractionsInOrder()
    {
        $cassette = new HttpCassette($this->path);
        $cassette->record('GET', 'https://example.com/1', [], 200, [], 'first');
        $cassette->record('GET', 'https://example.com/2', [], 201, [], 'second');

        $replay = new HttpCassette($this->path);
        $this->assertSame('first', $replay->next()['body']);
        $this->assertSame('second', $replay->next()['body']);
    }

    public function testNextThrowsWhenExhausted()
    {
        $cassette = new HttpCassette($this->path);
        $cassette->record('GET', 'https://example.com', [], 200, [], 'only');

        $replay = new HttpCassette($this->path);
        $replay->next();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is exhausted after 1 interaction(s); delete it to re-record.');
        $replay->next();
    }
}

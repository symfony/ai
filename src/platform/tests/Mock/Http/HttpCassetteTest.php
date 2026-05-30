<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Mock\Http;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Mock\Http\HttpCassette;

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
                'headers' => ['Authorization' => 'Bearer sk-secret', 'Content-Type' => 'application/json'],
                'json' => ['model' => 'mistral-large-latest'],
            ],
            200,
            ['content-type' => ['application/json']],
            '{"ok":true}',
        );

        $data = json_decode((string) file_get_contents($this->path), true, flags: \JSON_THROW_ON_ERROR);
        $request = $data['interactions'][0]['request'];

        $this->assertSame('POST', $request['method']);
        $this->assertArrayNotHasKey('Authorization', $request['headers']);
        $this->assertSame('application/json', $request['headers']['Content-Type']);
        $this->assertSame(['model' => 'mistral-large-latest'], $request['body']);
        $this->assertArrayHasKey('signature', $request);

        $serialized = (string) file_get_contents($this->path);
        $this->assertStringNotContainsString('sk-secret', $serialized);
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
        $this->expectExceptionMessage('is exhausted');
        $replay->next();
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\Tests\LiveTranscription;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Ovh\LiveTranscription;
use Symfony\AI\Platform\Bridge\Ovh\LiveTranscription\ResultConverter;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RealtimeSessionResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ResultConverterTest extends TestCase
{
    private const SESSION = [
        'id' => '45463597-20b7-4af7-b3b3-f5fb778203ab',
        'created_at' => '2026-11-25T09:04:17.210Z',
        'url' => 'wss://example.com/gladia/v2/live?token=4a39145c',
    ];

    public function testItSupportsLiveTranscriptionModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new LiveTranscription('solaria-3')));
        $this->assertFalse($converter->supports(new CompletionsModel('gpt-oss-120b')));
    }

    public function testItConvertsTheSession()
    {
        $result = (new ResultConverter())->convert($this->createResult(json_encode(self::SESSION), 201, 'solaria-3'));

        $this->assertInstanceOf(RealtimeSessionResult::class, $result);
        $this->assertSame(self::SESSION['id'], $result->getId());
        $this->assertSame(self::SESSION['url'], $result->getClientSecret());
        $this->assertSame('solaria-3', $result->getModel());
        $this->assertSame(['text'], $result->getModalities());
        $this->assertSame(self::SESSION, $result->getRaw());
    }

    public function testItAcceptsTheOkStatusCode()
    {
        $result = (new ResultConverter())->convert($this->createResult(json_encode(self::SESSION)));

        $this->assertInstanceOf(RealtimeSessionResult::class, $result);
        $this->assertSame('', $result->getModel());
    }

    public function testItReportsAuthenticationFailures()
    {
        $this->expectException(AuthenticationException::class);

        (new ResultConverter())->convert($this->createResult('{"error": "Unauthorized"}', 401));
    }

    public function testItThrowsExceptionOnUnexpectedStatusCode()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 204');

        (new ResultConverter())->convert($this->createResult('', 204));
    }

    public function testItThrowsExceptionWhenTheSessionIsIncomplete()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain the id and the URL of the session.');

        (new ResultConverter())->convert($this->createResult('{"id": "45463597"}', 201));
    }

    public function testItHasNoTokenUsageExtractor()
    {
        $this->assertNull((new ResultConverter())->getTokenUsageExtractor());
    }

    private function createResult(string $body, int $status = 200, ?string $model = null): RawHttpResult
    {
        $options = null === $model ? [] : ['user_data' => $model];
        $response = (new MockHttpClient(new MockResponse($body, ['http_code' => $status])))->request('POST', 'https://example.com/', $options);

        return new RawHttpResult($response);
    }
}

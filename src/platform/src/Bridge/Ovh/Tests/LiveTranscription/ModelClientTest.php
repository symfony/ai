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
use Symfony\AI\Platform\Bridge\Ovh\LiveTranscription\ModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ModelClientTest extends TestCase
{
    public function testItSupportsLiveTranscriptionModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new LiveTranscription('solaria-3')));
        $this->assertFalse($client->supports(new CompletionsModel('gpt-oss-120b')));
    }

    public function testItOpensASessionWithTheDefaultSettings()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://oai.endpoints.kepler.ai.cloud.ovh.net/gladia/v2/live', $url);
            $this->assertContains('Authorization: Bearer test-key', $options['headers']);
            $this->assertSame(['model' => 'solaria-3'], json_decode($options['body'], true));

            return new MockResponse();
        });

        (new ModelClient($httpClient, 'test-key'))->request(new LiveTranscription('solaria-3'), []);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItSendsTheSettingsOfTheSession()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame([
                'model' => 'solaria-3',
                'encoding' => 'wav/pcm',
                'sample_rate' => 16000,
                'language_config' => ['languages' => ['fr']],
            ], json_decode($options['body'], true));

            return new MockResponse();
        });

        (new ModelClient($httpClient, 'test-key'))->request(new LiveTranscription('solaria-3'), [
            'encoding' => 'wav/pcm',
            'sample_rate' => 16000,
            'language_config' => ['languages' => ['fr']],
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItSendsTheRegionAsQueryParameter()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('https://oai.endpoints.kepler.ai.cloud.ovh.net/gladia/v2/live?region=eu-west', $url);

            return new MockResponse();
        });

        (new ModelClient($httpClient, 'test-key'))->request(new LiveTranscription('solaria-3'), [], ['region' => 'eu-west']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItHonorsCustomBaseUrl()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('https://example.com/gladia/v2/live', $url);

            return new MockResponse();
        });

        (new ModelClient($httpClient, 'test-key', 'https://example.com/'))->request(new LiveTranscription('solaria-3'), []);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItRejectsAStringPayload()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The settings of the session must be an array, but a string was given');

        $client->request(new LiveTranscription('solaria-3'), 'settings');
    }
}

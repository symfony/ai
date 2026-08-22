<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Realtime;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Realtime;
use Symfony\AI\Platform\Bridge\OpenAi\Realtime\ModelClient;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Saiful Islam Feroz <saiful.feroz@gmail.com>
 */
final class ModelClientTest extends TestCase
{
    public function testSupportsRealtimeModel()
    {
        $httpClient = new MockHttpClient();
        $modelClient = new ModelClient($httpClient, 'sk-test-key');
        $model = new Realtime('gpt-4o-realtime-preview');

        $this->assertTrue($modelClient->supports($model));
    }

    public function testDoesNotSupportOtherModels()
    {
        $httpClient = new MockHttpClient();
        $modelClient = new ModelClient($httpClient, 'sk-test-key');
        $model = new Model('other-model');

        $this->assertFalse($modelClient->supports($model));
    }

    public function testRealtimeSessionRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/realtime/client_secrets', $url);
            self::assertSame('Authorization: Bearer sk-test-key', $options['normalized_headers']['authorization'][0]);

            $body = json_decode($options['body'], true);
            $session = $body['session'] ?? [];

            self::assertSame('realtime', $session['type']);
            self::assertSame('gpt-4o-realtime-preview', $session['model']);
            self::assertSame('You are a helpful customer service voice assistant.', $session['instructions']);
            self::assertSame('alloy', $session['audio']['output']['voice']);
            self::assertSame(['text', 'audio'], $session['modalities']);

            return new MockResponse(json_encode([
                'id' => 'sess_12345',
                'object' => 'realtime.client_secret',
                'value' => 'ek_secret_123',
                'expires_at' => 1790000000,
                'session' => [
                    'type' => 'realtime',
                    'model' => 'gpt-4o-realtime-preview',
                    'modalities' => ['text', 'audio'],
                    'instructions' => 'You are a helpful customer service voice assistant.',
                    'audio' => [
                        'output' => [
                            'voice' => 'alloy',
                        ],
                    ],
                ],
            ]));
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'sk-test-key');

        $modelClient->request(
            new Realtime('gpt-4o-realtime-preview'),
            'You are a helpful customer service voice assistant.',
            ['voice' => 'alloy']
        );
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Anthropic;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\ModelClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ModelClientTest extends TestCase
{
    public function testBetaFeaturesOption()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key' => 'test-api-key',
                    'anthropic-version' => '2023-06-01',
                    'anthropic-beta' => 'tool-use',
                ],
                'json' => [],
            ])
            ->willReturn($response);

        // Use the mock HttpClient directly instead of wrapping it
        $client = new ModelClient($httpClient, 'test-api-key');
        $model = new Claude();
        $payload = [];
        $options = ['beta_features' => ['tool-use']];

        $client->request($model, $payload, $options);
    }
}

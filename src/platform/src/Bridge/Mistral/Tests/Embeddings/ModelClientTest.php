<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests\Embeddings;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Embeddings;
use Symfony\AI\Platform\Bridge\Mistral\Embeddings\ModelClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ModelClientTest extends TestCase
{
    public function testHttpOptionsAreSentWithTheRequest()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url, array $options): MockResponse {
            $this->assertSame(30.0, $options['max_duration']);
            $this->assertSame(['model' => 'mistral-embed', 'input' => 'Hello'], json_decode($options['body'], true));

            return new MockResponse();
        }]);

        $client = new ModelClient($httpClient, 'test-api-key');
        $client->request(new Embeddings('mistral-embed'), 'Hello', ['http_options' => ['max_duration' => 30, 'json' => ['overridden' => true]]]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}

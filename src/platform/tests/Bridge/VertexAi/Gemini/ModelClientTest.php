<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\VertexAi\Gemini;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\ModelClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

#[Small]
#[CoversClass(ModelClient::class)]
#[UsesClass(Model::class)]
final class ModelClientTest extends TestCase
{
    public function testItInvokesTheTextModelsSuccessfully()
    {
        // Arrange
        $payload = [
            'content' => [
                ['parts' => ['text' => 'Hello, world!']],
            ],
        ];
        $expectedResponse = [
            'candidates' => [$payload],
        ];
        $httpClient = new MockHttpClient(
            new JsonMockResponse($expectedResponse),
        );

        $client = new ModelClient($httpClient, 'global', 'test');

        // Act
        $result = $client->request(new Model(Model::GEMINI_2_0_FLASH), $payload);
        $data = $result->getData();
        $info = $result->getObject()->getInfo();

        // Assert
        $this->assertNotEmpty($data);
        $this->assertNotEmpty($info);
        $this->assertSame('POST', $info['http_method']);
        $this->assertSame(
            'https://aiplatform.googleapis.com/v1/projects/test/locations/global/publishers/google/models/gemini-2.0-flash:generateContent',
            $info['url'],
        );
        $this->assertSame($expectedResponse, $data);
    }
}

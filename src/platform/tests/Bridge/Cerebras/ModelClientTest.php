<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Cerebras;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cerebras\Model;
use Symfony\AI\Platform\Bridge\Cerebras\ModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
class ModelClientTest extends TestCase
{
    public function testItDoesNotAllowAnEmptyKey()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must not be empty.');

        new ModelClient(new MockHttpClient(), '');
    }

    #[TestWith(['api-key-without-prefix'])]
    #[TestWith(['pk-api-key'])]
    #[TestWith(['SK-api-key'])]
    #[TestWith(['skapikey'])]
    #[TestWith(['sk api-key'])]
    #[TestWith(['sk'])]
    public function testItVerifiesIfTheKeyStartsWithCsk(string $invalidApiKey)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must start with "csk-".');

        new ModelClient(new MockHttpClient(), $invalidApiKey);
    }

    public function testItSupportsTheCorrectModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'csk-1234567890abcdef');

        $this->assertTrue($client->supports(new Model('llama3.1-8b')));
    }

    public function testItSuccessfullyInvokesTheModel()
    {
        $expectedResponse = [
            'model' => 'llama-3.3-70b',
            'input' => [
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello, world!'],
                ],
            ],
            'temperature' => 0.5,
        ];
        $httpClient = new MockHttpClient(
            new JsonMockResponse($expectedResponse),
        );

        $client = new ModelClient($httpClient, 'csk-1234567890abcdef');

        $payload = [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello, world!'],
            ],
        ];

        $result = $client->request(new Model('llama-3.3-70b'), $payload);
        $data = $result->getData();
        $info = $result->getObject()->getInfo();

        $this->assertNotEmpty($data);
        $this->assertNotEmpty($info);
        $this->assertSame('POST', $info['http_method']);
        $this->assertSame('https://api.cerebras.ai/v1/chat/completions', $info['url']);
        $this->assertSame($expectedResponse, $data);
    }
}

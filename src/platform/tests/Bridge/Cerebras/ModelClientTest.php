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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Action;
use Symfony\AI\Platform\Bridge\Cerebras\Model;
use Symfony\AI\Platform\Bridge\Cerebras\ModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
#[CoversClass(ModelClient::class)]
#[UsesClass(Model::class)]
#[Small]
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

        self::assertTrue($client->supports(new Model(Model::GPT_OSS_120B), Action::CHAT));
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

        $result = $client->request(new Model(Model::LLAMA_3_3_70B), $payload);
        $data = $result->getData();
        $info = $result->getObject()->getInfo();

        self::assertNotEmpty($data);
        self::assertNotEmpty($info);
        self::assertSame('POST', $info['http_method']);
        self::assertSame('https://api.cerebras.ai/v1/chat/completions', $info['url']);
        self::assertSame($expectedResponse, $data);
    }
}

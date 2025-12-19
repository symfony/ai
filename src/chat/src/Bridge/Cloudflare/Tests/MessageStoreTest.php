<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\Cloudflare\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Chat\Bridge\Cloudflare\MessageStore;
use Symfony\AI\Chat\Exception\InvalidArgumentException;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Uid\Uuid;

final class MessageStoreTest extends TestCase
{
    public function testMessageCannotSetupWithExtraOptions()
    {
        $httpClient = new MockHttpClient();

        $messageStore = new MessageStore($httpClient, 'foo', 'bar', 'random');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No supported options.');
        $this->expectExceptionCode(0);
        $messageStore->setup([
            'foo' => 'bar',
        ]);
    }

    public function testMessageStoreCannotSetupOnExistingNamespace()
    {
        $httpClient = new MockHttpClient(
            new JsonMockResponse([
                'result' => [
                    [
                        'id' => Uuid::v7()->toRfc4122(),
                        'title' => 'foo',
                        'supports_url_encoding' => false,
                    ],
                ],
                'success' => true,
                'errors' => [],
                'messages' => [],
            ], [
                'http_code' => 200,
            ]),
        );

        $messageStore = new MessageStore($httpClient, 'foo', 'bar', 'random');
        $messageStore->setup();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testMessageCannotSetupOnInvalidResponse()
    {
        $httpClient = new MockHttpClient(
            new JsonMockResponse([
                'result' => null,
                'success' => false,
                'errors' => [],
                'messages' => [
                    [
                        'code' => 400,
                        'message' => '',
                    ],
                ],
            ], [
                'http_code' => 400,
            ]),
        );

        $messageStore = new MessageStore($httpClient, 'foo', 'bar', 'random');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "https://api.cloudflare.com/client/v4/accounts/bar/storage/kv/namespaces"');
        $this->expectExceptionCode(400);
        $messageStore->setup();
    }

    public function testMessageStoreCanSetup()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [],
                'success' => true,
                'errors' => [],
                'messages' => [],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'result' => [
                    [
                        'id' => Uuid::v7()->toRfc4122(),
                        'title' => 'foo',
                        'supports_url_encoding' => false,
                    ],
                ],
                'success' => true,
                'errors' => [],
                'messages' => [],
            ], [
                'http_code' => 200,
            ]),
        ]);

        $messageStore = new MessageStore($httpClient, 'foo', 'bar', 'random');
        $messageStore->setup();

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testMessageStoreCannotDropUndefinedNamespace()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [],
                'success' => true,
                'errors' => [],
                'messages' => [],
            ], [
                'http_code' => 200,
            ]),
        ]);

        $messageStore = new MessageStore($httpClient, 'foo', 'bar', 'random');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No namespace found.');
        $this->expectExceptionCode(0);
        $messageStore->drop();
    }

    public function testMessageStoreCannotDropOnEmptyKeys()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [
                    [
                        'id' => Uuid::v7()->toRfc4122(),
                        'title' => 'foo',
                        'supports_url_encoding' => false,
                    ],
                ],
                'success' => true,
                'errors' => [],
                'messages' => [],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'result' => [],
                'success' => true,
                'errors' => [],
                'messages' => [],
            ], [
                'http_code' => 200,
            ]),
        ]);

        $messageStore = new MessageStore($httpClient, 'foo', 'bar', 'random');
        $messageStore->drop();

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testMessageStoreCanDrop()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [
                    [
                        'id' => Uuid::v7()->toRfc4122(),
                        'title' => 'foo',
                        'supports_url_encoding' => false,
                    ],
                ],
                'success' => true,
                'errors' => [],
                'messages' => [],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'result' => [
                    [
                        'name' => 'foo',
                        'expiration' => (new \DateTimeImmutable())->getTimestamp(),
                        'metadata' => [],
                    ],
                ],
                'success' => true,
                'errors' => [],
                'messages' => [],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'result' => [],
                'success' => true,
                'errors' => [],
                'messages' => [],
            ], [
                'http_code' => 200,
            ]),
        ]);

        $messageStore = new MessageStore($httpClient, 'foo', 'bar', 'random');
        $messageStore->drop();

        $this->assertSame(3, $httpClient->getRequestsCount());
    }

    public function testMessageStoreCanSave()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [
                    [
                        'id' => Uuid::v7()->toRfc4122(),
                        'title' => 'foo',
                        'supports_url_encoding' => false,
                    ],
                ],
                'success' => true,
                'errors' => [],
                'messages' => [],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'result' => [
                    'successful_key_count' => 1,
                    'unsuccessful_keys' => [],
                ],
                'success' => true,
                'errors' => [],
                'messages' => [],
            ], [
                'http_code' => 200,
            ]),
        ]);

        $messageStore = new MessageStore($httpClient, 'foo', 'bar', 'random');
        $messageStore->save(new MessageBag(
            Message::ofUser('Hello world'),
        ));

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testMessageStoreCanLoad()
    {
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageNormalizer(),
        ], [new JsonEncoder()]);

        $storedMessage = Message::ofUser('Hello World');

        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [
                    [
                        'id' => Uuid::v7()->toRfc4122(),
                        'title' => 'foo',
                        'supports_url_encoding' => false,
                    ],
                ],
                'success' => true,
                'errors' => [],
                'messages' => [],
            ], [
                'http_code' => 200,
            ]),

            new JsonMockResponse([
                'result' => [
                    [
                        'name' => 'foo',
                        'expiration' => (new \DateTimeImmutable())->getTimestamp(),
                        'metadata' => [],
                    ],
                ],
                'success' => true,
                'errors' => [],
                'messages' => [],
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                'result' => [
                    'values' => [
                        $storedMessage->getId()->toRfc4122() => $serializer->serialize($storedMessage, 'json'),
                    ],
                ],
                'success' => true,
                'errors' => [],
                'messages' => [],
            ], [
                'http_code' => 200,
            ]),
        ]);

        $messageStore = new MessageStore($httpClient, 'foo', 'bar', 'random', $serializer);
        $messageBag = $messageStore->load();

        $this->assertSame(3, $httpClient->getRequestsCount());
        $this->assertCount(1, $messageBag);
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Cloudflare\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Cloudflare\MessageStore;
use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Serializer\MessageBagNormalizer;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Uid\Uuid;

final class MessageStoreTest extends TestCase
{
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
                'result_info' => [
                    'count' => 1,
                    'total_count' => 1,
                    'total_pages' => 1,
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

    public function testMessageStoreCannotSetupOnExistingNamespaceOnSecondPageWithExistingIndices()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [
                    [
                        'id' => Uuid::v7()->toRfc4122(),
                        'title' => 'bar',
                        'supports_url_encoding' => false,
                    ],
                ],
                'result_info' => [
                    'count' => 1,
                    'page' => 1,
                    'total_count' => 3,
                    'total_pages' => 2,
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
                        'id' => Uuid::v7()->toRfc4122(),
                        'title' => 'second',
                        'supports_url_encoding' => false,
                    ],
                    [
                        'id' => Uuid::v7()->toRfc4122(),
                        'title' => 'foo',
                        'supports_url_encoding' => false,
                    ],
                ],
                'result_info' => [
                    'count' => 2,
                    'page' => 2,
                    'total_count' => 3,
                    'total_pages' => 2,
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

    public function testMessageStoreCannotSetupOnExistingNamespaceOnSecondPage()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [
                    [
                        'id' => Uuid::v7()->toRfc4122(),
                        'title' => 'bar',
                        'supports_url_encoding' => false,
                    ],
                ],
                'result_info' => [
                    'count' => 1,
                    'page' => 1,
                    'total_count' => 2,
                    'total_pages' => 2,
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
                        'id' => Uuid::v7()->toRfc4122(),
                        'title' => 'foo',
                        'supports_url_encoding' => false,
                    ],
                ],
                'result_info' => [
                    'count' => 1,
                    'page' => 2,
                    'total_count' => 2,
                    'total_pages' => 2,
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

    public function testMessageStoreCanSetupWhileNamespacesAreDefined()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [
                    [
                        'id' => Uuid::v7()->toRfc4122(),
                        'title' => 'bar',
                        'supports_url_encoding' => false,
                    ],
                ],
                'result_info' => [
                    'page' => 1,
                    'count' => 1,
                    'total_count' => 2,
                    'total_pages' => 2,
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
                        'id' => Uuid::v7()->toRfc4122(),
                        'title' => 'random',
                        'supports_url_encoding' => false,
                    ],
                ],
                'result_info' => [
                    'page' => 2,
                    'count' => 1,
                    'total_count' => 2,
                    'total_pages' => 2,
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

        $this->assertSame(3, $httpClient->getRequestsCount());
    }

    public function testMessageStoreCanSetup()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [],
                'result_info' => [
                    'count' => 0,
                    'total_count' => 0,
                    'total_pages' => 0,
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
                'result_info' => [
                    'page' => 1,
                    'total_count' => 0,
                    'total_pages' => 1,
                ],
                'success' => true,
                'errors' => [],
                'messages' => [],
            ], [
                'http_code' => 200,
            ]),
        ]);

        $messageStore = new MessageStore($httpClient, 'foo', 'bar', 'random');

        $messageStore->drop();

        $this->assertSame(1, $httpClient->getRequestsCount());
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
                'result_info' => [
                    'total_count' => 1,
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
                'result_info' => [
                    'page' => 1,
                    'count' => 1,
                    'total_count' => 1,
                    'total_pages' => 1,
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

    public function testMessageStoreCanDropWhenNamespaceOnNextPage()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'result' => [
                    [
                        'id' => Uuid::v7()->toRfc4122(),
                        'title' => 'bar',
                        'supports_url_encoding' => false,
                    ],
                    [
                        'id' => Uuid::v7()->toRfc4122(),
                        'title' => 'random',
                        'supports_url_encoding' => false,
                    ],
                    [
                        'id' => Uuid::v7()->toRfc4122(),
                        'title' => 'third',
                        'supports_url_encoding' => false,
                    ],
                ],
                'result_info' => [
                    'page' => 1,
                    'total_count' => 4,
                    'total_pages' => 2,
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
                        'id' => Uuid::v7()->toRfc4122(),
                        'title' => 'foo',
                        'supports_url_encoding' => false,
                    ],
                ],
                'result_info' => [
                    'page' => 2,
                    'total_count' => 4,
                    'total_pages' => 2,
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

        $this->assertSame(4, $httpClient->getRequestsCount());
    }

    public function testLoadReturnsAnEmptyBagWithTheGivenId()
    {
        $id = Uuid::v7();
        $httpClient = new MockHttpClient([
            $this->namespaces(),
            function (string $method, string $url, array $options) use ($id): JsonMockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.cloudflare.com/client/v4/accounts/bar/storage/kv/namespaces/ns/bulk/get', $url);
                $this->assertSame(['keys' => [$id->toRfc4122()]], json_decode($options['body'], true));

                return new JsonMockResponse(['result' => ['values' => [$id->toRfc4122() => null]], 'success' => true]);
            },
        ]);

        $messages = (new MessageStore($httpClient, 'foo', 'bar', 'random'))->load($id);

        $this->assertCount(0, $messages);
        $this->assertTrue($id->equals($messages->getId()));
    }

    public function testLoadDeserializesTheStoredConversation()
    {
        $messages = new MessageBag(Message::ofUser('Hello'), Message::ofAssistant('Hi'));
        $payload = $this->serializer()->serialize($messages, 'json');
        $httpClient = new MockHttpClient([
            $this->namespaces(),
            new JsonMockResponse(['result' => ['values' => [$messages->getId()->toRfc4122() => $payload]], 'success' => true]),
        ]);

        $loaded = (new MessageStore($httpClient, 'foo', 'bar', 'random'))->load($messages->getId());

        $this->assertSame($payload, $this->serializer()->serialize($loaded, 'json'));
    }

    public function testSaveWritesTheConversationUnderItsId()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $httpClient = new MockHttpClient([
            $this->namespaces(),
            new JsonMockResponse(['result' => ['values' => [$messages->getId()->toRfc4122() => null]], 'success' => true]),
            $this->namespaces(),
            function (string $method, string $url, array $options) use ($messages): JsonMockResponse {
                $this->assertSame('PUT', $method);
                $this->assertSame('https://api.cloudflare.com/client/v4/accounts/bar/storage/kv/namespaces/ns/bulk', $url);
                $this->assertSame([[
                    'key' => $messages->getId()->toRfc4122(),
                    'value' => $this->serializer()->serialize($messages, 'json'),
                ]], json_decode($options['body'], true));

                return new JsonMockResponse(['result' => ['successful_key_count' => 1, 'unsuccessful_keys' => []], 'success' => true]);
            },
        ]);

        (new MessageStore($httpClient, 'foo', 'bar', 'random'))->save($messages);

        $this->assertSame(4, $httpClient->getRequestsCount());
    }

    public function testSaveConflictsWhenTheMessagesDiverge()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $httpClient = new MockHttpClient([
            $this->namespaces(),
            new JsonMockResponse(['result' => ['values' => [
                $messages->getId()->toRfc4122() => $this->serializer()->serialize($messages->with(Message::ofAssistant('Hi')), 'json'),
            ]], 'success' => true]),
        ]);

        $this->expectException(ConflictException::class);

        (new MessageStore($httpClient, 'foo', 'bar', 'random'))->save($messages->with(Message::ofAssistant('Hey')));
    }

    public function testDeleteRemovesTheConversationKey()
    {
        $id = Uuid::v7();
        $httpClient = new MockHttpClient([
            $this->namespaces(),
            function (string $method, string $url, array $options) use ($id): JsonMockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.cloudflare.com/client/v4/accounts/bar/storage/kv/namespaces/ns/bulk/delete', $url);
                $this->assertSame([$id->toRfc4122()], json_decode($options['body'], true));

                return new JsonMockResponse(['result' => ['successful_key_count' => 1, 'unsuccessful_keys' => []], 'success' => true]);
            },
        ]);

        (new MessageStore($httpClient, 'foo', 'bar', 'random'))->delete($id);

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    private function namespaces(): JsonMockResponse
    {
        return new JsonMockResponse([
            'result' => [['id' => 'ns', 'title' => 'foo', 'supports_url_encoding' => false]],
            'result_info' => ['total_count' => 1, 'page' => 1, 'total_pages' => 1],
            'success' => true,
        ]);
    }

    private function serializer(): Serializer
    {
        return new Serializer([new MessageBagNormalizer()], [new JsonEncoder()]);
    }
}

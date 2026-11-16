<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests\Moderation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Factory;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Bridge\Mistral\Moderation;
use Symfony\AI\Platform\Bridge\Mistral\Moderation\ModelClient;
use Symfony\AI\Platform\Bridge\Mistral\Moderation\Result\ModerationResult;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelClientTest extends TestCase
{
    public function testItSupportsModerationModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new Moderation('mistral-moderation-latest')));
    }

    public function testItDoesNotSupportMistralModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertFalse($client->supports(new Mistral('mistral-large-latest')));
    }

    public function testItModeratesATextAsRawText()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.mistral.ai/v1/moderations', $url);
            $this->assertSame('Authorization: Bearer test-key', $options['normalized_headers']['authorization'][0]);
            $this->assertSame([
                'model' => 'mistral-moderation-latest',
                'input' => 'Hello world',
            ], json_decode($options['body'], true));

            return new JsonMockResponse(['results' => []]);
        });

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Moderation('mistral-moderation-latest'), 'Hello world');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItModeratesANormalizedTextContent()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('https://api.mistral.ai/v1/moderations', $url);
            $this->assertSame('Hello world', json_decode($options['body'], true)['input']);

            return new JsonMockResponse(['results' => []]);
        });

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Moderation('mistral-moderation-latest'), ['type' => 'text', 'text' => 'Hello world']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItModeratesSeveralTextsInOneRequest()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('https://api.mistral.ai/v1/moderations', $url);
            $this->assertSame(['Hello', 'World'], json_decode($options['body'], true)['input']);

            return new JsonMockResponse(['results' => []]);
        });

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Moderation('mistral-moderation-latest'), ['Hello', 'World']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItModeratesMessagesAsAConversation()
    {
        $messages = [
            ['role' => 'user', 'content' => 'Who wins the final?'],
            ['role' => 'assistant', 'content' => 'Probably the favourites.'],
        ];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($messages): JsonMockResponse {
            $this->assertSame('https://api.mistral.ai/v1/chat/moderations', $url);
            $this->assertSame([
                'model' => 'mistral-moderation-latest',
                'input' => $messages,
            ], json_decode($options['body'], true));

            return new JsonMockResponse(['results' => []]);
        });

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Moderation('mistral-moderation-latest'), ['messages' => $messages, 'model' => 'mistral-moderation-latest']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItModeratesSeveralNormalizedTextContents()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('https://api.mistral.ai/v1/moderations', $url);
            $this->assertSame(['Hello', 'World'], json_decode($options['body'], true)['input']);

            return new JsonMockResponse(['results' => []]);
        });

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Moderation('mistral-moderation-latest'), [
            ['type' => 'text', 'text' => 'Hello'],
            ['type' => 'text', 'text' => 'World'],
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItSendsTextsWithGapsInTheirKeysAsAList()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertStringContainsString('"input":["Hello","World"]', $options['body']);

            return new JsonMockResponse(['results' => []]);
        });

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Moderation('mistral-moderation-latest'), [0 => 'Hello', 2 => 'World']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItModeratesSeveralConversationsInOneRequest()
    {
        $first = [['role' => 'user', 'content' => 'Who wins the final?']];
        $second = [['role' => 'user', 'content' => 'Who lost the final?']];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($first, $second): JsonMockResponse {
            $this->assertSame('https://api.mistral.ai/v1/chat/moderations', $url);
            $this->assertSame([
                'model' => 'mistral-moderation-latest',
                'input' => [$first, $second],
            ], json_decode($options['body'], true));

            return new JsonMockResponse(['results' => []]);
        });

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Moderation('mistral-moderation-latest'), [
            ['messages' => $first, 'model' => 'mistral-moderation-latest'],
            ['messages' => $second, 'model' => 'mistral-moderation-latest'],
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    /**
     * @param array<int|string, mixed> $payload
     */
    #[DataProvider('provideUnsupportedPayloads')]
    public function testItRejectsAnUnsupportedPayload(array $payload)
    {
        $httpClient = new MockHttpClient();
        $client = new ModelClient($httpClient, 'test-key');

        try {
            $client->request(new Moderation('mistral-moderation-latest'), $payload);
            $this->fail('An unsupported payload must be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, $httpClient->getRequestsCount());
        }
    }

    /**
     * @return iterable<string, array{array<int|string, mixed>}>
     */
    public static function provideUnsupportedPayloads(): iterable
    {
        yield 'a message outside of a conversation' => [['role' => 'user', 'content' => 'Hello']];
        yield 'a list of numbers' => [[1, 2]];
        yield 'an image' => [[['type' => 'image_url', 'image_url' => 'https://example.com/image.png']]];
        yield 'texts mixed with conversations' => [['Hello', ['messages' => [['role' => 'user', 'content' => 'Hello']]]]];
    }

    public function testItReplacesMalformedUtf8InsteadOfFailing()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame("Hello\u{FFFD}", json_decode($options['body'], true)['input']);

            return new JsonMockResponse(['results' => []]);
        });

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Moderation('mistral-moderation-latest'), "Hello\xC3");

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItForwardsOptionsWithoutLettingThemReplaceTheModelOrTheInput()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame([
                'metadata' => ['source' => 'comment-form'],
                'model' => 'mistral-moderation-latest',
                'input' => 'Hello world',
            ], json_decode($options['body'], true));

            return new JsonMockResponse(['results' => []]);
        });

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Moderation('mistral-moderation-latest'), 'Hello world', [
            'metadata' => ['source' => 'comment-form'],
            'model' => 'another-model',
            'input' => 'Another input',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItUsesTheConfiguredBaseUrl()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('https://mistral.example.com/v1/moderations', $url);

            return new JsonMockResponse(['results' => []]);
        });

        $client = new ModelClient($httpClient, 'test-key', 'https://mistral.example.com/');

        $client->request(new Moderation('mistral-moderation-latest'), 'Hello world');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testFactoryUsesTheConfiguredBaseUrlForBothEndpoints()
    {
        $urls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$urls): JsonMockResponse {
            $urls[] = $url;

            return new JsonMockResponse(['results' => [['categories' => ['sexual' => false], 'category_scores' => ['sexual' => 0.001]]]]);
        });

        $platform = Factory::createPlatform('test-key', $httpClient, baseUrl: 'https://mistral.example.com/');

        $platform->invoke('mistral-moderation-latest', 'Hello world')->asObject(ModerationResult::class);
        $platform->invoke('mistral-moderation-latest', new MessageBag(Message::ofUser('Hello world')))->asObject(ModerationResult::class);

        $this->assertSame([
            'https://mistral.example.com/v1/moderations',
            'https://mistral.example.com/v1/chat/moderations',
        ], $urls);
    }

    public function testPlatformModeratesSeveralConversationsAndTextContents()
    {
        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): JsonMockResponse {
            $requests[] = [$url, json_decode($options['body'], true)['input']];

            return new JsonMockResponse(['results' => [['categories' => ['sexual' => false], 'category_scores' => ['sexual' => 0.001]]]]);
        });

        $platform = Factory::createPlatform('test-key', $httpClient);

        $platform->invoke('mistral-moderation-latest', [
            new MessageBag(Message::ofUser('Who wins the final?')),
            new MessageBag(Message::ofUser('Who lost the final?')),
        ])->asObject(ModerationResult::class);
        $platform->invoke('mistral-moderation-latest', [new Text('Hello'), new Text('World')])->asObject(ModerationResult::class);

        $this->assertSame([
            ['https://api.mistral.ai/v1/chat/moderations', [
                [['role' => 'user', 'content' => 'Who wins the final?']],
                [['role' => 'user', 'content' => 'Who lost the final?']],
            ]],
            ['https://api.mistral.ai/v1/moderations', ['Hello', 'World']],
        ], $requests);
    }
}

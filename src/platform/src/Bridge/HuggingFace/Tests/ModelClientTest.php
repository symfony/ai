<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Contract\FileNormalizer;
use Symfony\AI\Platform\Bridge\HuggingFace\Contract\MessageBagNormalizer;
use Symfony\AI\Platform\Bridge\HuggingFace\ModelClient;
use Symfony\AI\Platform\Bridge\HuggingFace\Provider;
use Symfony\AI\Platform\Bridge\HuggingFace\Task;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ModelClientTest extends TestCase
{
    #[DataProvider('urlTestCases')]
    public function testGetUrlForDifferentInputsAndTasks(?string $task, string $expectedUrl, ?string $provider = null)
    {
        $response = new MockResponse('{"result": "test"}', [
            'http_code' => 200,
        ]);

        $httpClient = new MockHttpClient(function (string $method, string $url) use ($expectedUrl, $response): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame($expectedUrl, $url);

            return $response;
        });

        $model = new Model('test-model');
        $modelClient = new ModelClient($httpClient, 'test-provider', 'test-api-key');

        // Make a request to trigger URL generation
        $options = $task ? ['task' => $task] : [];
        if (null !== $provider) {
            $options['provider'] = $provider;
        }
        $modelClient->request($model, 'test input', $options);
    }

    public static function urlTestCases(): \Iterator
    {
        $messageBag = new MessageBag();
        $messageBag->add(new UserMessage(new Text('Test message')));
        yield 'string input' => [
            'task' => null,
            'expectedUrl' => 'https://router.huggingface.co/test-provider/models/test-model',
        ];
        yield 'array input' => [
            'task' => null,
            'expectedUrl' => 'https://router.huggingface.co/test-provider/models/test-model',
        ];
        yield 'image input' => [
            'task' => null,
            'expectedUrl' => 'https://router.huggingface.co/test-provider/models/test-model',
        ];
        yield 'feature extraction' => [
            'task' => Task::FEATURE_EXTRACTION,
            'expectedUrl' => 'https://router.huggingface.co/test-provider/models/test-model',
        ];
        yield 'chat completion with hf-inference' => [
            'task' => Task::CHAT_COMPLETION,
            'expectedUrl' => 'https://router.huggingface.co/hf-inference/models/test-model/v1/chat/completions',
            'provider' => Provider::HF_INFERENCE,
        ];
        yield 'chat completion with third-party provider' => [
            'task' => Task::CHAT_COMPLETION,
            'expectedUrl' => 'https://router.huggingface.co/featherless-ai/v1/chat/completions',
            'provider' => Provider::FEATHERLESS_AI,
        ];
        yield 'feature extraction with third-party provider uses standard url' => [
            'task' => Task::FEATURE_EXTRACTION,
            'expectedUrl' => 'https://router.huggingface.co/together/models/test-model',
            'provider' => Provider::TOGETHER,
        ];
        yield 'text ranking' => [
            'task' => Task::TEXT_RANKING,
            'expectedUrl' => 'https://router.huggingface.co/test-provider/models/test-model',
        ];
    }

    public function testThirdPartyProviderInjectsModelNameInPayload()
    {
        $response = new MockResponse('{"result": "test"}');
        $httpClient = new MockHttpClient($response);

        $model = new Model('HuggingFaceH4/zephyr-7b-beta');
        $modelClient = new ModelClient($httpClient, Provider::FEATHERLESS_AI, 'test-api-key');

        $contract = Contract::create(
            new FileNormalizer(),
            new MessageBagNormalizer()
        );

        $messageBag = new MessageBag();
        $messageBag->add(new UserMessage(new Text('Test message')));

        $payload = $contract->createRequestPayload($model, $messageBag);

        $modelClient->request($model, $payload, [
            'task' => Task::CHAT_COMPLETION,
        ]);

        $requestOptions = $response->getRequestOptions();
        $body = json_decode($requestOptions['body'], true);

        $this->assertSame('HuggingFaceH4/zephyr-7b-beta', $body['model']);
    }

    public function testProviderOptionOverridesDefaultProvider()
    {
        $response = new MockResponse('{"result": "test"}', [
            'http_code' => 200,
        ]);

        $expectedUrl = 'https://router.huggingface.co/hyperbolic/v1/chat/completions';

        $httpClient = new MockHttpClient(function (string $method, string $url) use ($expectedUrl, $response): MockResponse {
            $this->assertSame($expectedUrl, $url);

            return $response;
        });

        $model = new Model('test-model');
        // Default provider is hf-inference, but we override with hyperbolic
        $modelClient = new ModelClient($httpClient, Provider::HF_INFERENCE, 'test-api-key');

        $modelClient->request($model, 'test input', [
            'task' => Task::CHAT_COMPLETION,
            'provider' => Provider::HYPERBOLIC,
        ]);

        $body = json_decode($response->getRequestOptions()['body'], true);
        $this->assertSame('test-model', $body['model']);
    }

    public function testThirdPartyProviderWithNonChatTaskDoesNotInjectModelName()
    {
        $response = new MockResponse('{"result": "test"}');
        $httpClient = new MockHttpClient($response);

        $model = new Model('BAAI/bge-small-en-v1.5');
        $modelClient = new ModelClient($httpClient, Provider::TOGETHER, 'test-api-key');

        $modelClient->request($model, 'Some text to embed', [
            'task' => Task::FEATURE_EXTRACTION,
        ]);

        $body = json_decode($response->getRequestOptions()['body'], true);
        $this->assertArrayNotHasKey('model', $body);
    }

    public function testHfInferenceDoesNotInjectModelNameInPayload()
    {
        $response = new MockResponse('{"result": "test"}');
        $httpClient = new MockHttpClient($response);

        $model = new Model('test-model');
        $modelClient = new ModelClient($httpClient, Provider::HF_INFERENCE, 'test-api-key');

        $contract = Contract::create(
            new FileNormalizer(),
            new MessageBagNormalizer()
        );

        $messageBag = new MessageBag();
        $messageBag->add(new UserMessage(new Text('Test message')));

        $payload = $contract->createRequestPayload($model, $messageBag);

        $modelClient->request($model, $payload, [
            'task' => Task::CHAT_COMPLETION,
        ]);

        $requestOptions = $response->getRequestOptions();
        $body = json_decode($requestOptions['body'], true);

        $this->assertArrayNotHasKey('model', $body);
    }

    /**
     * @param object|array<string|int, mixed>|string $input
     * @param array<string, mixed>                   $options
     * @param array<string>                          $expectedKeys
     * @param array<string, mixed>                   $expectedValues
     */
    #[DataProvider('payloadTestCases')]
    public function testGetPayloadForDifferentInputsAndTasks(object|array|string $input, array $options, array $expectedKeys, array $expectedValues = [])
    {
        $response = new MockResponse('{"result": "test"}');
        $httpClient = new MockHttpClient($response);

        $model = new Model('test-model');
        $modelClient = new ModelClient($httpClient, 'test-provider', 'test-api-key');

        // Contract handling first
        $contract = Contract::create(
            new FileNormalizer(),
            new MessageBagNormalizer()
        );

        $payload = $contract->createRequestPayload($model, $input);

        // Make a request to trigger payload generation
        $modelClient->request($model, $payload, $options);

        // Get the request options that were sent
        $requestOptions = $response->getRequestOptions();

        // Check that expected keys exist in the transformed structure
        foreach ($expectedKeys as $key) {
            if ('json' === $key) {
                // JSON gets transformed to body in HTTP client
                $this->assertArrayHasKey('body', $requestOptions);
            } elseif ('headers' === $key) {
                $this->assertArrayHasKey('headers', $requestOptions);
            }
        }

        // Check expected values if specified
        foreach ($expectedValues as $path => $value) {
            $keys = explode('.', $path);

            if ('headers' === $keys[0] && 'Content-Type' === $keys[1]) {
                // Check Content-Type header in the normalized structure
                $this->assertContains('Content-Type: application/json', $requestOptions['headers']);
            } elseif ('json' === $keys[0]) {
                // JSON content is in the body, need to decode
                $body = json_decode($requestOptions['body'], true);
                $current = $body;

                // Navigate through the remaining keys
                for ($i = 1; $i < \count($keys); ++$i) {
                    $this->assertArrayHasKey($keys[$i], $current);
                    $current = $current[$keys[$i]];
                }

                $this->assertEquals($value, $current);
            }
        }
    }

    public function testStringInputWithOptionsWrapsInParametersKey()
    {
        $response = new MockResponse('{"result": "test"}');
        $httpClient = new MockHttpClient($response);

        $model = new Model('test-model');
        $modelClient = new ModelClient($httpClient, 'test-provider', 'test-api-key');

        $modelClient->request($model, 'Hello world', [
            'temperature' => 0.7,
            'max_tokens' => 50,
        ]);

        $body = json_decode($response->getRequestOptions()['body'], true);

        $this->assertSame('Hello world', $body['inputs']);
        $this->assertSame(0.7, $body['parameters']['temperature']);
        $this->assertSame(50, $body['parameters']['max_tokens']);
    }

    public function testPreBuiltBodyPayloadIsPassedThrough()
    {
        $response = new MockResponse('{"result": "test"}');
        $httpClient = new MockHttpClient($response);

        $model = new Model('test-model');
        $modelClient = new ModelClient($httpClient, 'test-provider', 'test-api-key');

        $binaryContent = 'fake-binary-image-data';
        $modelClient->request($model, [
            'body' => $binaryContent,
            'headers' => ['Content-Type' => 'image/jpeg'],
        ]);

        $requestOptions = $response->getRequestOptions();

        $this->assertSame($binaryContent, $requestOptions['body']);
        $this->assertContains('Content-Type: image/jpeg', $requestOptions['headers']);
    }

    public static function payloadTestCases(): \Iterator
    {
        yield 'string input' => [
            'input' => 'Hello world',
            'options' => [],
            'expectedKeys' => ['headers', 'json'],
            'expectedValues' => [
                'headers.Content-Type' => 'application/json',
                'json.inputs' => 'Hello world',
            ],
        ];

        yield 'array input' => [
            'input' => ['text' => 'Hello world'],
            'options' => ['temperature' => 0.7],
            'expectedKeys' => ['headers', 'json'],
            'expectedValues' => [
                'headers.Content-Type' => 'application/json',
                'json.inputs' => ['text' => 'Hello world'],
                'json.parameters.temperature' => 0.7,
            ],
        ];

        $messageBag = new MessageBag();
        $messageBag->add(new UserMessage(new Text('Test message')));

        yield 'message bag' => [
            'input' => $messageBag,
            'options' => ['max_tokens' => 100],
            'expectedKeys' => ['headers', 'json'],
            'expectedValues' => [
                'headers.Content-Type' => 'application/json',
                'json.max_tokens' => 100,
            ],
        ];

        yield 'text ranking' => [
            'input' => ['query' => 'search query', 'texts' => ['doc one', 'doc two']],
            'options' => ['task' => Task::TEXT_RANKING],
            'expectedKeys' => ['headers', 'json'],
            'expectedValues' => [
                'headers.Content-Type' => 'application/json',
                'json.inputs' => [
                    ['text' => 'search query', 'text_pair' => 'doc one'],
                    ['text' => 'search query', 'text_pair' => 'doc two'],
                ],
            ],
        ];
    }
}

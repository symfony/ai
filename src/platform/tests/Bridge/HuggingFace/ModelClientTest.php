<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\HuggingFace;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Contract\FileNormalizer;
use Symfony\AI\Platform\Bridge\HuggingFace\Contract\MessageBagNormalizer;
use Symfony\AI\Platform\Bridge\HuggingFace\ModelClient;
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
    public function testGetUrlForDifferentInputsAndTasks(?string $task, string $expectedUrl)
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
            'expectedUrl' => 'https://router.huggingface.co/test-provider/pipeline/feature-extraction/test-model',
        ];
        yield 'message bag' => [
            'task' => Task::CHAT_COMPLETION,
            'expectedUrl' => 'https://router.huggingface.co/test-provider/models/test-model/v1/chat/completions',
        ];
    }

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
    }
}

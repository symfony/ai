<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Tests\Completions;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Classification;
use Symfony\AI\Platform\Bridge\HuggingFace\Completions;
use Symfony\AI\Platform\Bridge\HuggingFace\Completions\ModelClient;
use Symfony\AI\Platform\Bridge\HuggingFace\Provider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ModelClientTest extends TestCase
{
    public function testSupportsCompletionsModelsOnly()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'test-api-key');

        $this->assertTrue($modelClient->supports(new Completions('test/model')));
        $this->assertFalse($modelClient->supports(new Classification('test/model')));
    }

    public function testLetsHuggingFacePickTheProviderWithoutConfiguredProvider()
    {
        $response = new MockResponse('{}');
        $modelClient = new ModelClient(new MockHttpClient($response), 'test-api-key');

        $modelClient->request(new Completions('Qwen/Qwen3-32B'), ['model' => 'Qwen/Qwen3-32B', 'messages' => []]);

        $this->assertSame('POST', $response->getRequestMethod());
        $this->assertSame('https://router.huggingface.co/v1/chat/completions', $response->getRequestUrl());
        $this->assertContains('Authorization: Bearer test-api-key', $response->getRequestOptions()['headers']);
        $this->assertSame('Qwen/Qwen3-32B', json_decode($response->getRequestOptions()['body'], true)['model']);
    }

    public function testRoutesToTheConfiguredProvider()
    {
        $response = new MockResponse('{}');
        $modelClient = new ModelClient(new MockHttpClient($response), 'test-api-key', Provider::NSCALE);

        $modelClient->request(new Completions('Qwen/Qwen3-32B'), ['model' => 'Qwen/Qwen3-32B', 'messages' => []]);

        $this->assertSame('https://router.huggingface.co/v1/chat/completions', $response->getRequestUrl());
        $this->assertSame('Qwen/Qwen3-32B:nscale', json_decode($response->getRequestOptions()['body'], true)['model']);
    }

    public function testProviderOptionOverridesTheConfiguredProvider()
    {
        $response = new MockResponse('{}');
        $modelClient = new ModelClient(new MockHttpClient($response), 'test-api-key', Provider::NSCALE);

        $modelClient->request(new Completions('Qwen/Qwen3-32B'), ['model' => 'Qwen/Qwen3-32B', 'messages' => []], ['provider' => Provider::TOGETHER]);

        $body = json_decode($response->getRequestOptions()['body'], true);
        $this->assertSame('Qwen/Qwen3-32B:together', $body['model']);
        $this->assertArrayNotHasKey('provider', $body);
    }

    public function testKeepsTheProviderSuffixOfTheModelName()
    {
        $response = new MockResponse('{}');
        $modelClient = new ModelClient(new MockHttpClient($response), 'test-api-key', Provider::NSCALE);

        $modelClient->request(new Completions('Qwen/Qwen3-32B:fastest'), ['model' => 'Qwen/Qwen3-32B:fastest', 'messages' => []]);

        $this->assertSame('Qwen/Qwen3-32B:fastest', json_decode($response->getRequestOptions()['body'], true)['model']);
    }

    public function testUsesCustomBaseUrlWithoutTrailingSlash()
    {
        $response = new MockResponse('{}');
        $modelClient = new ModelClient(new MockHttpClient($response), 'test-api-key', baseUrl: 'https://hf.example.com/');

        $modelClient->request(new Completions('Qwen/Qwen3-32B'), ['model' => 'Qwen/Qwen3-32B', 'messages' => []]);

        $this->assertSame('https://hf.example.com/v1/chat/completions', $response->getRequestUrl());
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\ModelClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class ModelClientTest extends TestCase
{
    private MockHttpClient $httpClient;
    private ModelClient $modelClient;
    private Claude $model;

    protected function setUp(): void
    {
        $this->model = new Claude('claude-3-5-sonnet-latest');
    }

    public function testAnthropicBetaHeaderIsSetWithSingleBetaFeature()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $this->assertEquals('POST', $method);
            $this->assertEquals('https://api.anthropic.com/v1/messages', $url);

            $headers = $this->parseHeaders($options['headers']);

            $this->assertArrayHasKey('anthropic-beta', $headers);
            $this->assertEquals('feature-1', $headers['anthropic-beta']);

            return new JsonMockResponse('{"success": true}');
        });

        $this->modelClient = new ModelClient($this->httpClient, 'test-api-key');

        $options = ['beta_features' => ['feature-1']];
        $this->modelClient->request($this->model, ['message' => 'test'], $options);
    }

    public function testAnthropicBetaHeaderIsSetWithMultipleBetaFeatures()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $headers = $this->parseHeaders($options['headers']);

            $this->assertArrayHasKey('anthropic-beta', $headers);
            $this->assertEquals('feature-1,feature-2,feature-3', $headers['anthropic-beta']);

            return new JsonMockResponse('{"success": true}');
        });

        $this->modelClient = new ModelClient($this->httpClient, 'test-api-key');

        $options = ['beta_features' => ['feature-1', 'feature-2', 'feature-3']];
        $this->modelClient->request($this->model, ['message' => 'test'], $options);
    }

    public function testAnthropicBetaHeaderIsNotSetWhenBetaFeaturesIsEmpty()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $headers = $this->parseHeaders($options['headers']);

            $this->assertArrayNotHasKey('anthropic-beta', $headers);

            return new JsonMockResponse('{"success": true}');
        });

        $this->modelClient = new ModelClient($this->httpClient, 'test-api-key');

        $options = ['beta_features' => []];
        $this->modelClient->request($this->model, ['message' => 'test'], $options);
    }

    public function testAnthropicBetaHeaderIsNotSetWhenBetaFeaturesIsNotProvided()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $headers = $this->parseHeaders($options['headers']);

            $this->assertArrayNotHasKey('anthropic-beta', $headers);

            return new JsonMockResponse('{"success": true}');
        });

        $this->modelClient = new ModelClient($this->httpClient, 'test-api-key');

        $options = ['some_other_option' => 'value'];
        $this->modelClient->request($this->model, ['message' => 'test'], $options);
    }

    private function parseHeaders(array $headers): array
    {
        $parsed = [];
        foreach ($headers as $header) {
            if (str_contains($header, ':')) {
                [$key, $value] = explode(':', $header, 2);
                $parsed[trim($key)] = trim($value);
            }
        }

        return $parsed;
    }
}

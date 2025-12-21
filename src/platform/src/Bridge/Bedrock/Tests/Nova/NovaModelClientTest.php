<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Tests\Nova;

use AsyncAws\BedrockRuntime\BedrockRuntimeClient;
use AsyncAws\BedrockRuntime\Input\InvokeModelRequest;
use AsyncAws\BedrockRuntime\Result\InvokeModelResponse;
use AsyncAws\Core\Configuration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Nova;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\NovaModelClient;
use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResult;

final class NovaModelClientTest extends TestCase
{
    private MockObject&BedrockRuntimeClient $bedrockClient;
    private NovaModelClient $modelClient;
    private Nova $model;

    protected function setUp(): void
    {
        $this->model = new Nova('nova-pro');
        $this->bedrockClient = $this->getMockBuilder(BedrockRuntimeClient::class)
            ->setConstructorArgs([
                Configuration::create([Configuration::OPTION_REGION => Configuration::DEFAULT_REGION]),
            ])
            ->onlyMethods(['invokeModel'])
            ->getMock();
    }

    public function testPassesModelId()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('us.amazon.nova-pro-v1:0', $arg->getModelId());
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertTrue(json_validate($arg->getBody()));

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new NovaModelClient($this->bedrockClient);

        $response = $this->modelClient->request($this->model, ['message' => 'test']);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testUnsetsModelName()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertTrue(json_validate($arg->getBody()));

                $body = json_decode($arg->getBody(), true);
                $this->assertArrayNotHasKey('model', $body);

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new NovaModelClient($this->bedrockClient);

        $response = $this->modelClient->request($this->model, ['message' => 'test', 'model' => 'nova-pro']);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testSetsToolOptionsIfToolsEnabled()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertTrue(json_validate($arg->getBody()));

                $body = json_decode($arg->getBody(), true);
                $this->assertSame(['tools' => ['Tool']], $body['toolConfig']);

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new NovaModelClient($this->bedrockClient);

        $options = [
            'tools' => ['Tool'],
        ];

        $response = $this->modelClient->request($this->model, ['message' => 'test'], $options);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testPassesTemperature()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertTrue(json_validate($arg->getBody()));

                $body = json_decode($arg->getBody(), true);
                $this->assertArrayHasKey('inferenceConfig', $body);
                $this->assertSame(['temperature' => 0.35], $body['inferenceConfig']);

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new NovaModelClient($this->bedrockClient);

        $options = [
            'temperature' => 0.35,
        ];

        $response = $this->modelClient->request($this->model, ['message' => 'test'], $options);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testPassesMaxTokens()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertTrue(json_validate($arg->getBody()));

                $body = json_decode($arg->getBody(), true);
                $this->assertArrayHasKey('inferenceConfig', $body);
                $this->assertSame(['maxTokens' => 1000], $body['inferenceConfig']);

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new NovaModelClient($this->bedrockClient);

        $options = [
            'max_tokens' => 1000,
        ];

        $response = $this->modelClient->request($this->model, ['message' => 'test'], $options);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testPassesBothTemperatureAndMaxTokens()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertTrue(json_validate($arg->getBody()));

                $body = json_decode($arg->getBody(), true);
                $this->assertArrayHasKey('inferenceConfig', $body);
                $this->assertSame(['temperature' => 0.35, 'maxTokens' => 1000], $body['inferenceConfig']);

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new NovaModelClient($this->bedrockClient);

        $options = [
            'max_tokens' => 1000,
            'temperature' => 0.35,
        ];

        $response = $this->modelClient->request($this->model, ['message' => 'test'], $options);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }
}

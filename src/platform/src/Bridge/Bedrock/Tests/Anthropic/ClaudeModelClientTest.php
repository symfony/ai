<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Tests\Anthropic;

use AsyncAws\BedrockRuntime\BedrockRuntimeClient;
use AsyncAws\BedrockRuntime\Input\InvokeModelRequest;
use AsyncAws\BedrockRuntime\Result\InvokeModelResponse;
use AsyncAws\Core\Configuration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Bedrock\Anthropic\ClaudeModelClient;
use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResult;

final class ClaudeModelClientTest extends TestCase
{
    private const VERSION = '2023-05-31';

    private MockObject&BedrockRuntimeClient $bedrockClient;
    private ClaudeModelClient $modelClient;
    private Claude $model;

    protected function setUp(): void
    {
        $this->model = new Claude('claude-sonnet-4-5-20250929');
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
                $this->assertSame('us.anthropic.claude-sonnet-4-5-20250929-v1:0', $arg->getModelId());
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertTrue(json_validate($arg->getBody()));

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new ClaudeModelClient($this->bedrockClient, self::VERSION);

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

        $this->modelClient = new ClaudeModelClient($this->bedrockClient, self::VERSION);

        $response = $this->modelClient->request($this->model, ['message' => 'test', 'model' => 'claude']);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }

    public function testSetsAnthropicVersion()
    {
        $this->bedrockClient->expects($this->once())
            ->method('invokeModel')
            ->with($this->callback(function ($arg) {
                $this->assertInstanceOf(InvokeModelRequest::class, $arg);
                $this->assertSame('application/json', $arg->getContentType());
                $this->assertTrue(json_validate($arg->getBody()));

                $body = json_decode($arg->getBody(), true);
                $this->assertSame('bedrock-'.self::VERSION, $body['anthropic_version']);

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new ClaudeModelClient($this->bedrockClient, self::VERSION);

        $response = $this->modelClient->request($this->model, ['message' => 'test']);
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
                $this->assertSame(['type' => 'auto'], $body['tool_choice']);

                return true;
            }))
            ->willReturn($this->createMock(InvokeModelResponse::class));

        $this->modelClient = new ClaudeModelClient($this->bedrockClient, self::VERSION);

        $options = [
            'tools' => ['Tool'],
        ];

        $response = $this->modelClient->request($this->model, ['message' => 'test'], $options);
        $this->assertInstanceOf(RawBedrockResult::class, $response);
    }
}

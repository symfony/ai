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
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Nova;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\NovaModelClient;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Test\ModelClientOptionsTestCase;

final class NovaModelClientOptionsTest extends ModelClientOptionsTestCase
{
    public static function wireFields(): array
    {
        return [
            PlatformOptions::MAX_OUTPUT_TOKENS => 'inferenceConfig.maxTokens',
            PlatformOptions::TEMPERATURE => 'inferenceConfig.temperature',
        ];
    }

    protected function sendRequest(array $options): array
    {
        $body = [];
        $bedrockClient = $this->getMockBuilder(BedrockRuntimeClient::class)
            ->setConstructorArgs([Configuration::create([Configuration::OPTION_REGION => Configuration::DEFAULT_REGION])])
            ->onlyMethods(['invokeModel'])
            ->getMock();
        $bedrockClient->expects($this->once())->method('invokeModel')->willReturnCallback(function (InvokeModelRequest $request) use (&$body): InvokeModelResponse {
            $body = json_decode($request->getBody(), true);

            return $this->createStub(InvokeModelResponse::class);
        });

        (new NovaModelClient($bedrockClient))->request(new Nova('nova-pro'), ['messages' => []], $options);

        return $body;
    }
}

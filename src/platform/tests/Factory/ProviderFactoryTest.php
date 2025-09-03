<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Factory;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Azure\Meta\PlatformFactory as AzureMetaBridge;
use Symfony\AI\Platform\Bridge\Azure\OpenAi\PlatformFactory as AzureOpenAIBridge;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAIBridge;
use Symfony\AI\Platform\Factory\ProviderFactory;

#[Group('pf')]
#[CoversClass(ProviderFactory::class)]
final class ProviderFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        OpenAIBridge::$lastArgs = [];
        AzureOpenAIBridge::$lastArgs = [];
        AzureMetaBridge::$lastArgs = [];
    }

    public function testBuildsOpenAiWithBearerAuth()
    {
        $factory = new ProviderFactory();

        $obj = $factory->fromDsn('ai+openai://sk-test@api.openai.com?model=gpt-4o-mini');

        $this->assertIsObject($obj);
        $this->assertSame('openai', $obj->bridge ?? null);

        $args = OpenAIBridge::$lastArgs ?? [];
        $this->assertSame('sk-test', $args['apiKey'] ?? null);
        $this->assertSame('https://api.openai.com', $args['contract']['base_uri'] ?? null);
        $this->assertSame('openai', $args['contract']['provider'] ?? null);
        $this->assertSame('gpt-4o-mini', $args['contract']['options']['model'] ?? null);
        $headers = $args['contract']['headers'] ?? [];
        $this->assertSame('Bearer sk-test', $headers['Authorization'] ?? null);
        $this->assertArrayNotHasKey('api-key', $headers);
    }

    public function testBuildsAzureOpenAiWithApiKeyHeader()
    {
        $factory = new ProviderFactory();

        $obj = $factory->fromDsn(
            'ai+azure://AZ@my-resource.openai.azure.com?deployment=gpt-4o&version=2024-08-01-preview&engine=openai'
        );

        $this->assertIsObject($obj);
        $this->assertSame('azure-openai', $obj->bridge ?? null);

        $args = AzureOpenAIBridge::$lastArgs ?? [];
        $this->assertSame('AZ', $args['apiKey'] ?? null);
        $this->assertSame('https://my-resource.openai.azure.com', $args['contract']['base_uri'] ?? null);
        $this->assertSame('azure', $args['contract']['provider'] ?? null);
        $this->assertSame('gpt-4o', $args['contract']['options']['deployment'] ?? null);
        $this->assertSame('2024-08-01-preview', $args['contract']['options']['version'] ?? null);
        $this->assertSame('openai', $args['contract']['options']['engine'] ?? null);

        $headers = $args['contract']['headers'] ?? [];
        $this->assertSame('AZ', $headers['api-key'] ?? null);
        $this->assertArrayNotHasKey('Authorization', $headers);
    }

    public function testBuildsAzureMetaWhenEngineMeta()
    {
        $factory = new ProviderFactory();

        $obj = $factory->fromDsn(
            'ai+azure://AZ@my-resource.meta.azure.com?deployment=llama-3.1&version=2024-08-01-preview&engine=meta'
        );

        $this->assertIsObject($obj);
        $this->assertSame('azure-meta', $obj->bridge ?? null);

        $args = AzureMetaBridge::$lastArgs ?? [];
        $this->assertSame('AZ', $args['apiKey'] ?? null);
        $this->assertSame('https://my-resource.meta.azure.com', $args['contract']['base_uri'] ?? null);
        $this->assertSame('azure', $args['contract']['provider'] ?? null);
        $this->assertSame('meta', $args['contract']['options']['engine'] ?? null);
        $this->assertSame('llama-3.1', $args['contract']['options']['deployment'] ?? null);

        $headers = $args['contract']['headers'] ?? [];
        $this->assertSame('AZ', $headers['api-key'] ?? null);
    }

    public function testUnsupportedProviderThrows()
    {
        $this->expectException(\InvalidArgumentException::class);

        $factory = new ProviderFactory();
        $factory->fromDsn('ai+madeup://x@y.z');
    }

    public function testAzureMissingDeploymentOrVersionBubblesUp()
    {
        $this->expectException(\InvalidArgumentException::class);

        $factory = new ProviderFactory();
        $factory->fromDsn('ai+azure://AZ@my-resource.openai.azure.com?version=2024-08-01-preview');
    }
}

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
use Symfony\AI\Platform\Factory\ProviderConfigFactory;
use Symfony\AI\Platform\Bridge\OpenAI\PlatformFactory as OpenAIBridge;
use Symfony\AI\Platform\Bridge\Azure\OpenAI\PlatformFactory as AzureOpenAIBridge;
use Symfony\AI\Platform\Bridge\Azure\Meta\PlatformFactory as AzureMetaBridge;

#[Group('pf')]
#[CoversClass(ProviderConfigFactory::class)]
class ProviderConfigFactoryTest extends TestCase
{
    public function testOpenAiDefaults(): void
    {
        $cfg = ProviderConfigFactory::fromDsn(
            'ai+openai://sk-test@api.openai.com?model=gpt-4o-mini&organization=org_123&headers[x-foo]=bar'
        );

        $this->assertSame('openai', $cfg->provider);
        $this->assertSame('https://api.openai.com', $cfg->baseUri);
        $this->assertSame('sk-test', $cfg->apiKey);
        $this->assertSame('gpt-4o-mini', $cfg->options['model'] ?? null);
        $this->assertSame('org_123', $cfg->options['organization'] ?? null);
        $this->assertSame('bar', $cfg->headers['x-foo'] ?? null);
    }

    public function testOpenAiWithoutHostUsesDefault(): void
    {
        $cfg = ProviderConfigFactory::fromDsn('ai+openai://sk-test@/?model=gpt-4o-mini');

        $this->assertSame('https://api.openai.com', $cfg->baseUri);
        $this->assertSame('gpt-4o-mini', $cfg->options['model'] ?? null);
    }

    public function testAzureOpenAiHappyPath(): void
    {
        $cfg = ProviderConfigFactory::fromDsn(
            'ai+azure://AZ_KEY@my-resource.openai.azure.com?deployment=gpt-4o&version=2024-08-01-preview&engine=openai'
        );

        $this->assertSame('azure', $cfg->provider);
        $this->assertSame('https://my-resource.openai.azure.com', $cfg->baseUri);
        $this->assertSame('AZ_KEY', $cfg->apiKey);
        $this->assertSame('gpt-4o', $cfg->options['deployment'] ?? null);
        $this->assertSame('2024-08-01-preview', $cfg->options['version'] ?? null);
        $this->assertSame('openai', $cfg->options['engine'] ?? null);
    }

    public function testAzureMetaHappyPath(): void
    {
        $cfg = ProviderConfigFactory::fromDsn(
            'ai+azure://AZ_KEY@my-resource.meta.azure.com?deployment=llama-3.1&version=2024-08-01-preview&engine=meta'
        );

        $this->assertSame('azure', $cfg->provider);
        $this->assertSame('https://my-resource.meta.azure.com', $cfg->baseUri);
        $this->assertSame('meta', $cfg->options['engine'] ?? null);
        $this->assertSame('llama-3.1', $cfg->options['deployment'] ?? null);
    }

    public function testGenericOptionsAndBooleans(): void
    {
        $cfg = ProviderConfigFactory::fromDsn(
            'ai+openai://sk@/?model=gpt-4o-mini&timeout=10&verify_peer=true&proxy=http://proxy:8080'
        );

        $this->assertSame(10, $cfg->options['timeout'] ?? null);
        $this->assertTrue($cfg->options['verify_peer'] ?? false);
        $this->assertSame('http://proxy:8080', $cfg->options['proxy'] ?? null);
    }

    public function testUnknownProviderThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ProviderConfigFactory::fromDsn('ai+unknown://key@host');
    }

    public function testAzureMissingHostThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ProviderConfigFactory::fromDsn('ai+azure://AZ_KEY@/?deployment=gpt-4o&version=2024-08-01-preview');
    }

    public function testAzureMissingDeploymentThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ProviderConfigFactory::fromDsn('ai+azure://AZ_KEY@my.openai.azure.com?version=2024-08-01-preview');
    }

    public function testAzureMissingVersionThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ProviderConfigFactory::fromDsn('ai+azure://AZ_KEY@my.openai.azure.com?deployment=gpt-4o');
    }

    public function testAzureUnsupportedEngineThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ProviderConfigFactory::fromDsn(
            'ai+azure://AZ_KEY@my.openai.azure.com?deployment=gpt-4o&version=2024-08-01-preview&engine=unknown'
        );
    }
}

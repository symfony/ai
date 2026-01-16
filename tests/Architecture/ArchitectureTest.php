<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Architecture tests for the Symfony AI monorepo.
 *
 * These tests ensure that dependencies between components follow the intended architecture.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ArchitectureTest
{
    /**
     * Platform component should not depend on other Symfony AI components.
     */
    public function testPlatformComponentHasNoCrossComponentDependencies(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('/^Symfony\\\\AI\\\\Platform\\\\(?!Bridge\\\\)/', true))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('Symfony\AI\Agent'),
                Selector::inNamespace('Symfony\AI\Store'),
                Selector::inNamespace('Symfony\AI\Chat'),
            )
            ->because('Platform component is the foundation and should not depend on other components');
    }

    /**
     * Store component can only depend on Platform component.
     */
    public function testStoreComponentDependencies(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('/^Symfony\\\\AI\\\\Store\\\\(?!Bridge\\\\)/', true))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('Symfony\AI\Agent'),
                Selector::inNamespace('Symfony\AI\Chat'),
            )
            ->because('Store component can only depend on Platform component');
    }

    /**
     * Agent component can depend on Platform and Store components.
     */
    public function testAgentComponentDependencies(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('/^Symfony\\\\AI\\\\Agent\\\\(?!Bridge\\\\)/', true))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('Symfony\AI\Chat'),
            )
            ->because('Agent component should not depend on Chat component');
    }

    /**
     * Chat component can depend on Agent and Platform components.
     */
    public function testChatComponentDependencies(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('/^Symfony\\\\AI\\\\Chat\\\\(?!Bridge\\\\)/', true))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('Symfony\AI\Store'),
            )
            ->because('Chat component should not depend on Store component');
    }

    /**
     * Agent bridges should not depend on Chat or Store bridges.
     *
     * @return iterable<Rule>
     */
    public function testAgentBridgesIsolation(): iterable
    {
        $agentBridges = [
            'Brave',
            'Clock',
            'Firecrawl',
            'Mapbox',
            'OpenMeteo',
            'Scraper',
            'SerpApi',
            'SimilaritySearch',
            'Tavily',
            'Wikipedia',
            'Youtube',
        ];

        foreach ($agentBridges as $bridge) {
            yield PHPat::rule()
                ->classes(Selector::inNamespace('Symfony\AI\Agent\Bridge\\'.$bridge))
                ->shouldNotDependOn()
                ->classes(
                    Selector::inNamespace('Symfony\AI\Chat'),
                    Selector::inNamespace('/^Symfony\\\\AI\\\\Agent\\\\Bridge\\\\(?!'.$bridge.')/', true),
                )
                ->because($bridge.' bridge should only depend on Agent component and Platform');
        }
    }

    /**
     * Chat bridges should not depend on Agent, Store or other Chat bridges.
     *
     * @return iterable<Rule>
     */
    public function testChatBridgesIsolation(): iterable
    {
        $chatBridges = [
            'Cache',
            'Cloudflare',
            'Doctrine',
            'Session',
            'Meilisearch',
            'MongoDb',
            'Pogocache',
            'Redis',
            'SurrealDb',
        ];

        foreach ($chatBridges as $bridge) {
            yield PHPat::rule()
                ->classes(Selector::inNamespace('Symfony\AI\Chat\Bridge\\'.$bridge))
                ->shouldNotDependOn()
                ->classes(
                    Selector::inNamespace('Symfony\AI\Agent'),
                    Selector::inNamespace('Symfony\AI\Store'),
                    Selector::inNamespace('/^Symfony\\\\AI\\\\Chat\\\\Bridge\\\\(?!'.$bridge.')/', true),
                )
                ->because($bridge.' chat bridge should only depend on Chat component and Platform');
        }
    }

    /**
     * Store bridges should not depend on Agent, Chat or other Store bridges.
     *
     * @return iterable<Rule>
     */
    public function testStoreBridgesIsolation(): iterable
    {
        $storeBridges = [
            'AzureSearch',
            'Cache',
            'ChromaDb',
            'ClickHouse',
            'Cloudflare',
            'ManticoreSearch',
            'MariaDb',
            'Meilisearch',
            'Milvus',
            'MongoDb',
            'Neo4j',
            'OpenSearch',
            'Pinecone',
            'Postgres',
            'Qdrant',
            'Redis',
            'Supabase',
            'SurrealDb',
            'Typesense',
            'Weaviate',
        ];

        foreach ($storeBridges as $bridge) {
            yield PHPat::rule()
                ->classes(Selector::inNamespace('Symfony\AI\Store\Bridge\\'.$bridge))
                ->shouldNotDependOn()
                ->classes(
                    Selector::inNamespace('Symfony\AI\Agent'),
                    Selector::inNamespace('Symfony\AI\Chat'),
                    Selector::inNamespace('/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\(?!'.$bridge.')/', true),
                )
                ->because($bridge.' store bridge should only depend on Store component and Platform');
        }
    }

    /**
     * Platform bridges should not depend on Agent, Chat, Store or unrelated Platform bridges.
     */
    public function testPlatformBridgesNoExternalDependencies(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Symfony\AI\Platform\Bridge'))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('Symfony\AI\Agent'),
                Selector::inNamespace('Symfony\AI\Chat'),
                Selector::inNamespace('Symfony\AI\Store'),
            )
            ->because('Platform bridges should not depend on other components');
    }

    /**
     * Azure platform can depend on OpenAi, Generic, and Meta platforms.
     */
    public function testAzurePlatformAllowedDependencies(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Symfony\AI\Platform\Bridge\Azure'))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Anthropic'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Bedrock'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Cartesia'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Cerebras'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Decart'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\DeepSeek'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\DockerModelRunner'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\ElevenLabs'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Gemini'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\HuggingFace'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\LmStudio'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Mistral'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Ollama'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\OpenRouter'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Perplexity'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Replicate'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Scaleway'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\TransformersPhp'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\VertexAi'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Voyage'),
            )
            ->because('Azure platform can only depend on OpenAi, Generic, and Meta platforms');
    }

    /**
     * Bedrock platform can depend on Meta and Anthropic platforms.
     */
    public function testBedrockPlatformAllowedDependencies(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Symfony\AI\Platform\Bridge\Bedrock'))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('Symfony\AI\Platform\Bridge\AiMlApi'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Albert'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Azure'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Cartesia'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Cerebras'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Decart'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\DeepSeek'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\DockerModelRunner'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\ElevenLabs'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Gemini'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Generic'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\HuggingFace'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\LmStudio'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Mistral'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Ollama'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\OpenAi'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\OpenRouter'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Perplexity'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Replicate'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Scaleway'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\TransformersPhp'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\VertexAi'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Voyage'),
            )
            ->because('Bedrock platform can only depend on Meta and Anthropic platforms');
    }

    /**
     * VertexAi platform can depend on Gemini platform.
     */
    public function testVertexaiPlatformAllowedDependencies(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Symfony\AI\Platform\Bridge\VertexAi'))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('Symfony\AI\Platform\Bridge\AiMlApi'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Albert'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Anthropic'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Azure'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Bedrock'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Cartesia'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Cerebras'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Decart'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\DeepSeek'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\DockerModelRunner'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\ElevenLabs'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Generic'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\HuggingFace'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\LmStudio'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Meta'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Mistral'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Ollama'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\OpenAi'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\OpenRouter'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Perplexity'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Replicate'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Scaleway'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\TransformersPhp'),
                Selector::inNamespace('Symfony\AI\Platform\Bridge\Voyage'),
            )
            ->because('VertexAi platform can only depend on Gemini platform');
    }

    /**
     * SimilaritySearch tool can depend on Store component.
     */
    public function testSimilaritySearchToolCanDependOnStore(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Symfony\AI\Agent\Bridge\SimilaritySearch'))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('Symfony\AI\Chat'),
            )
            ->because('SimilaritySearch tool can depend on Agent, Platform and Store components');
    }

    /**
     * Standalone platforms should not depend on other platform bridges.
     *
     * @return iterable<Rule>
     */
    public function testStandalonePlatformBridges(): iterable
    {
        $standalonePlatforms = [
            'Anthropic',
            'Cartesia',
            'Cerebras',
            'Decart',
            'DeepSeek',
            'DockerModelRunner',
            'ElevenLabs',
            'Gemini',
            'HuggingFace',
            'Mistral',
            'Ollama',
            'OpenAi',
            'Perplexity',
            'Scaleway',
            'TransformersPhp',
            'Voyage',
        ];

        foreach ($standalonePlatforms as $platform) {
            yield PHPat::rule()
                ->classes(Selector::inNamespace('Symfony\AI\Platform\Bridge\\'.$platform))
                ->shouldNotDependOn()
                ->classes(
                    Selector::inNamespace('/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\(?!'.$platform.')/', true),
                )
                ->because($platform.' platform should not depend on other platform bridges');
        }
    }

    /**
     * Generic platform dependent platforms.
     *
     * @return iterable<Rule>
     */
    public function testGenericPlatformDependents(): iterable
    {
        $genericDependentPlatforms = [
            'AiMlApi',
            'Albert',
            'LmStudio',
            'OpenRouter',
        ];

        foreach ($genericDependentPlatforms as $platform) {
            yield PHPat::rule()
                ->classes(Selector::inNamespace('Symfony\AI\Platform\Bridge\\'.$platform))
                ->shouldNotDependOn()
                ->classes(
                    Selector::inNamespace('/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\(?!'.$platform.'|Generic)/', true),
                )
                ->because($platform.' platform can only depend on Generic platform bridge');
        }
    }

    /**
     * Replicate platform can depend on Meta platform.
     */
    public function testReplicatePlatformDependencies(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Symfony\AI\Platform\Bridge\Replicate'))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\(?!Replicate|Meta)/', true),
            )
            ->because('Replicate platform can only depend on Meta platform');
    }

    /**
     * Meta platform should not depend on other platform bridges.
     */
    public function testMetaPlatformDependencies(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Symfony\AI\Platform\Bridge\Meta'))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\(?!Meta)/', true),
            )
            ->because('Meta platform should not depend on other platform bridges');
    }

    /**
     * Generic platform should not depend on other platform bridges.
     */
    public function testGenericPlatformDependencies(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Symfony\AI\Platform\Bridge\Generic'))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\(?!Generic)/', true),
            )
            ->because('Generic platform should not depend on other platform bridges');
    }
}

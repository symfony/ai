<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AIBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\AIBundle\DependencyInjection\AIExtension;
use Symfony\AI\AIBundle\DependencyInjection\Configuration;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(Configuration::class)]
#[UsesClass(ContainerBuilder::class)]
#[UsesClass(AIExtension::class)]
class ConfigurationTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testExtensionLoad(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $extension = new AIExtension();

        $configs = $this->getFullConfig();
        $extension->load($configs, $container);
    }

    /**
     * @return array<string, mixed>
     */
    private function getFullConfig(): array
    {
        return [
            'ai' => [
                'platform' => [
                    'anthropic' => [
                        'api_key' => 'anthropic_key_full',
                    ],
                    'azure' => [
                        'my_azure_instance' => [
                            'api_key' => 'azure_key_full',
                            'base_url' => 'https://myazure.openai.azure.com/',
                            'deployment' => 'gpt-35-turbo',
                            'api_version' => '2024-02-15-preview',
                        ],
                        'another_azure_instance' => [
                            'api_key' => 'azure_key_2',
                            'base_url' => 'https://myazure2.openai.azure.com/',
                            'deployment' => 'gpt-4',
                            'api_version' => '2024-02-15-preview',
                        ],
                    ],
                    'google' => [
                        'api_key' => 'google_key_full',
                    ],
                    'openai' => [
                        'api_key' => 'openai_key_full',
                    ],
                    'mistral' => [
                        'api_key' => 'mistral_key_full',
                    ],
                    'openrouter' => [
                        'api_key' => 'openrouter_key_full',
                    ],
                ],
                'agent' => [
                    'my_chat_agent' => [
                        'platform' => 'openai_platform_service_id',
                        'model' => [
                            'name' => 'gpt',
                            'version' => 'gpt-3.5-turbo',
                            'options' => [
                                'temperature' => 0.7,
                                'max_tokens' => 150,
                                'nested' => ['options' => ['work' => 'too']],
                            ],
                        ],
                        'structured_output' => false,
                        'system_prompt' => 'You are a helpful assistant.',
                        'include_tools' => true,
                        'tools' => [
                            'enabled' => true,
                            'services' => [
                                ['service' => 'my_tool_service_id', 'name' => 'myTool', 'description' => 'A test tool'],
                                'another_tool_service_id', // String format
                            ],
                        ],
                        'fault_tolerant_toolbox' => false,
                    ],
                    'another_agent' => [
                        'model' => ['name' => 'claude', 'version' => 'claude-3-opus-20240229'],
                        'system_prompt' => 'Be concise.',
                    ],
                ],
                'store' => [
                    'azure_search' => [
                        'my_azure_search_store' => [
                            'endpoint' => 'https://mysearch.search.windows.net',
                            'api_key' => 'azure_search_key',
                            'index_name' => 'my-documents',
                            'api_version' => '2023-11-01',
                            'vector_field' => 'contentVector',
                        ],
                    ],
                    'chroma_db' => [
                        'my_chroma_store' => [
                            'collection' => 'my_collection',
                        ],
                    ],
                    'mongodb' => [
                        'my_mongo_store' => [
                            'database' => 'my_db',
                            'collection' => 'my_collection',
                            'index_name' => 'vector_index',
                            'vector_field' => 'embedding',
                            'bulk_write' => true,
                        ],
                    ],
                    'pinecone' => [
                        'my_pinecone_store' => [
                            'namespace' => 'my_namespace',
                            'filter' => ['category' => 'books'],
                            'top_k' => 10,
                        ],
                    ],
                ],
                'indexer' => [
                    'my_text_indexer' => [
                        'store' => 'my_azure_search_store_service_id',
                        'platform' => 'google_platform_service_id',
                        'model' => [
                            'name' => 'embeddings',
                            'version' => 'text-embedding-004',
                            'options' => ['dimension' => 768],
                        ],
                    ],
                ],
            ],
        ];
    }
}

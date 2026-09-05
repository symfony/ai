<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Boundwize\StructArmed\Architecture;

return Architecture::define()
    ->layer('Source', 'src/')
    ->skipPaths([
        '*/tests/*',
        '*/Tests/*',
        '*/Test/*',
        '*/vendor/*',
    ])
    ->layerPattern('Fixtures', '/^Symfony\\\\AI\\\\Fixtures\\\\.*$/')
    ->layerPattern('AgentComponent', '/^Symfony\\\\AI\\\\Agent\\\\(?!Bridge\\\\).*$/')
    ->layerPattern('BraveTool', '/^Symfony\\\\AI\\\\Agent\\\\Bridge\\\\Brave\\\\.*$/')
    ->layerPattern('ClockTool', '/^Symfony\\\\AI\\\\Agent\\\\Bridge\\\\Clock\\\\.*$/')
    ->layerPattern('FilesystemTool', '/^Symfony\\\\AI\\\\Agent\\\\Bridge\\\\Filesystem\\\\.*$/')
    ->layerPattern('FirecrawlTool', '/^Symfony\\\\AI\\\\Agent\\\\Bridge\\\\Firecrawl\\\\.*$/')
    ->layerPattern('MapboxTool', '/^Symfony\\\\AI\\\\Agent\\\\Bridge\\\\Mapbox\\\\.*$/')
    ->layerPattern('OllamaTool', '/^Symfony\\\\AI\\\\Agent\\\\Bridge\\\\Ollama\\\\.*$/')
    ->layerPattern('OpenMeteoTool', '/^Symfony\\\\AI\\\\Agent\\\\Bridge\\\\OpenMeteo\\\\.*$/')
    ->layerPattern('ScraperTool', '/^Symfony\\\\AI\\\\Agent\\\\Bridge\\\\Scraper\\\\.*$/')
    ->layerPattern('SerpApiTool', '/^Symfony\\\\AI\\\\Agent\\\\Bridge\\\\SerpApi\\\\.*$/')
    ->layerPattern('SimilaritySearchTool', '/^Symfony\\\\AI\\\\Agent\\\\Bridge\\\\SimilaritySearch\\\\.*$/')
    ->layerPattern('TavilyTool', '/^Symfony\\\\AI\\\\Agent\\\\Bridge\\\\Tavily\\\\.*$/')
    ->layerPattern('WikipediaTool', '/^Symfony\\\\AI\\\\Agent\\\\Bridge\\\\Wikipedia\\\\.*$/')
    ->layerPattern('YoutubeTool', '/^Symfony\\\\AI\\\\Agent\\\\Bridge\\\\Youtube\\\\.*$/')
    ->layerPattern('ChatComponent', '/^Symfony\\\\AI\\\\Chat\\\\(?!Bridge\\\\).*$/')
    ->layerPattern('ChatCacheBridge', '/^Symfony\\\\AI\\\\Chat\\\\Bridge\\\\Cache\\\\.*$/')
    ->layerPattern('ChatCloudflareBridge', '/^Symfony\\\\AI\\\\Chat\\\\Bridge\\\\Cloudflare\\\\.*$/')
    ->layerPattern('ChatDoctrineBridge', '/^Symfony\\\\AI\\\\Chat\\\\Bridge\\\\Doctrine\\\\.*$/')
    ->layerPattern('ChatSessionBridge', '/^Symfony\\\\AI\\\\Chat\\\\Bridge\\\\Session\\\\.*$/')
    ->layerPattern('ChatMeilisearchBridge', '/^Symfony\\\\AI\\\\Chat\\\\Bridge\\\\Meilisearch\\\\.*$/')
    ->layerPattern('ChatMongoDbBridge', '/^Symfony\\\\AI\\\\Chat\\\\Bridge\\\\MongoDb\\\\.*$/')
    ->layerPattern('ChatPogocacheBridge', '/^Symfony\\\\AI\\\\Chat\\\\Bridge\\\\Pogocache\\\\.*$/')
    ->layerPattern('ChatRedisBridge', '/^Symfony\\\\AI\\\\Chat\\\\Bridge\\\\Redis\\\\.*$/')
    ->layerPattern('ChatSurrealDbBridge', '/^Symfony\\\\AI\\\\Chat\\\\Bridge\\\\SurrealDb\\\\.*$/')
    ->layerPattern('PlatformComponent', '/^Symfony\\\\AI\\\\Platform\\\\(?!Bridge\\\\).*$/')
    ->layerPattern('AiMlApiPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\AiMlApi\\\\.*$/')
    ->layerPattern('AlbertPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Albert\\\\.*$/')
    ->layerPattern('AmazeeAiPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\AmazeeAi\\\\.*$/')
    ->layerPattern('AnthropicPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Anthropic\\\\.*$/')
    ->layerPattern('AzurePlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Azure\\\\.*$/')
    ->layerPattern('BedrockPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Bedrock\\\\.*$/')
    ->layerPattern('CachePlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Cache\\\\.*$/')
    ->layerPattern('CartesiaPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Cartesia\\\\.*$/')
    ->layerPattern('CerebrasPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Cerebras\\\\.*$/')
    ->layerPattern('ClaudeCodePlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\ClaudeCode\\\\.*$/')
    ->layerPattern('CodexPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Codex\\\\.*$/')
    ->layerPattern('CoherePlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Cohere\\\\.*$/')
    ->layerPattern('DecartPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Decart\\\\.*$/')
    ->layerPattern('DeepgramPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Deepgram\\\\.*$/')
    ->layerPattern('DeepSeekPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\DeepSeek\\\\.*$/')
    ->layerPattern('DockerModelRunnerPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\DockerModelRunner\\\\.*$/')
    ->layerPattern('ElevenLabsPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\ElevenLabs\\\\.*$/')
    ->layerPattern('FailoverPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Failover\\\\.*$/')
    ->layerPattern('GeminiPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Gemini\\\\.*$/')
    ->layerPattern('GenericPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Generic\\\\.*$/')
    ->layerPattern('HuggingFacePlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\HuggingFace\\\\.*$/')
    ->layerPattern('LmStudioPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\LmStudio\\\\.*$/')
    ->layerPattern('MetaPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Meta\\\\.*$/')
    ->layerPattern('MiniMaxPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\MiniMax\\\\.*$/')
    ->layerPattern('MistralPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Mistral\\\\.*$/')
    ->layerPattern('ModelsDevPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\ModelsDev\\\\.*$/')
    ->layerPattern('OllamaPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Ollama\\\\.*$/')
    ->layerPattern('OpenAiPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\OpenAi\\\\.*$/')
    ->layerPattern('OpenResponsesPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\OpenResponses\\\\.*$/')
    ->layerPattern('OpenRouterPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\OpenRouter\\\\.*$/')
    ->layerPattern('OvhPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Ovh\\\\.*$/')
    ->layerPattern('PerplexityPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Perplexity\\\\.*$/')
    ->layerPattern('ReplicatePlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Replicate\\\\.*$/')
    ->layerPattern('ScalewayPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Scaleway\\\\.*$/')
    ->layerPattern('TransformersPhpPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\TransformersPhp\\\\.*$/')
    ->layerPattern('VertexAiPlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\VertexAi\\\\.*$/')
    ->layerPattern('VoyagePlatform', '/^Symfony\\\\AI\\\\Platform\\\\Bridge\\\\Voyage\\\\.*$/')
    ->layerPattern('StoreComponent', '/^Symfony\\\\AI\\\\Store\\\\(?!Bridge\\\\).*$/')
    ->layerPattern('AzureSearchStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\AzureSearch\\\\.*$/')
    ->layerPattern('CacheStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\Cache\\\\.*$/')
    ->layerPattern('ChromaDbStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\ChromaDb\\\\.*$/')
    ->layerPattern('ClickHouseStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\ClickHouse\\\\.*$/')
    ->layerPattern('CloudflareStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\Cloudflare\\\\.*$/')
    ->layerPattern('ElasticsearchStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\Elasticsearch\\\\.*$/')
    ->layerPattern('ManticoreSearchStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\ManticoreSearch\\\\.*$/')
    ->layerPattern('MariaDbStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\MariaDb\\\\.*$/')
    ->layerPattern('S3Vectors', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\S3Vectors\\\\.*$/')
    ->layerPattern('MeilisearchStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\Meilisearch\\\\.*$/')
    ->layerPattern('MilvusStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\Milvus\\\\.*$/')
    ->layerPattern('MongoDbStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\MongoDb\\\\.*$/')
    ->layerPattern('Neo4jStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\Neo4j\\\\.*$/')
    ->layerPattern('OpenSearchStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\OpenSearch\\\\.*$/')
    ->layerPattern('PineconeStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\Pinecone\\\\.*$/')
    ->layerPattern('PostgresStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\Postgres\\\\.*$/')
    ->layerPattern('QdrantStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\Qdrant\\\\.*$/')
    ->layerPattern('RedisStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\Redis\\\\.*$/')
    ->layerPattern('SqliteStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\Sqlite\\\\.*$/')
    ->layerPattern('SupabaseStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\Supabase\\\\.*$/')
    ->layerPattern('SurrealDbStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\SurrealDb\\\\.*$/')
    ->layerPattern('TypesenseStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\Typesense\\\\.*$/')
    ->layerPattern('VektorStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\Vektor\\\\.*$/')
    ->layerPattern('WeaviateStore', '/^Symfony\\\\AI\\\\Store\\\\Bridge\\\\Weaviate\\\\.*$/')
    ->layerPattern('MateComponent', '/^Symfony\\\\AI\\\\Mate\\\\(?!Bridge\\\\).*$/')
    ->layerPattern('MateMonologBridge', '/^Symfony\\\\AI\\\\Mate\\\\Bridge\\\\Monolog\\\\.*$/')
    ->layerPattern('MateSymfonyBridge', '/^Symfony\\\\AI\\\\Mate\\\\Bridge\\\\Symfony\\\\.*$/')
    ->ruleset([
        'Fixtures' => [],
        'AgentComponent' => ['+StoreComponent'],
        'BraveTool' => ['AgentComponent', 'PlatformComponent'],
        'ClockTool' => ['AgentComponent'],
        'FilesystemTool' => ['AgentComponent'],
        'FirecrawlTool' => ['AgentComponent'],
        'MapboxTool' => ['AgentComponent', 'PlatformComponent'],
        'OllamaTool' => ['AgentComponent'],
        'OpenMeteoTool' => ['AgentComponent', 'PlatformComponent'],
        'ScraperTool' => ['AgentComponent'],
        'SerpApiTool' => ['AgentComponent'],
        'SimilaritySearchTool' => ['AgentComponent', 'StoreComponent'],
        'TavilyTool' => ['AgentComponent'],
        'WikipediaTool' => ['AgentComponent'],
        'YoutubeTool' => ['AgentComponent'],
        'ChatComponent' => ['AgentComponent', 'PlatformComponent'],
        'ChatCacheBridge' => ['ChatComponent', 'PlatformComponent'],
        'ChatCloudflareBridge' => ['ChatComponent', 'PlatformComponent'],
        'ChatDoctrineBridge' => ['ChatComponent', 'PlatformComponent'],
        'ChatSessionBridge' => ['ChatComponent', 'PlatformComponent'],
        'ChatMeilisearchBridge' => ['ChatComponent', 'PlatformComponent'],
        'ChatMongoDbBridge' => ['ChatComponent', 'PlatformComponent'],
        'ChatPogocacheBridge' => ['ChatComponent', 'PlatformComponent'],
        'ChatRedisBridge' => ['ChatComponent', 'PlatformComponent'],
        'ChatSurrealDbBridge' => ['ChatComponent', 'PlatformComponent'],
        'PlatformComponent' => [],
        'AiMlApiPlatform' => ['+GenericPlatform'],
        'AlbertPlatform' => ['+GenericPlatform'],
        'AmazeeAiPlatform' => ['+GenericPlatform'],
        'AnthropicPlatform' => ['PlatformComponent'],
        'AzurePlatform' => ['+OpenAiPlatform', '+GenericPlatform', '+MetaPlatform'],
        'BedrockPlatform' => ['+MetaPlatform', '+AnthropicPlatform'],
        'CachePlatform' => ['PlatformComponent'],
        'CartesiaPlatform' => ['PlatformComponent'],
        'CerebrasPlatform' => ['+GenericPlatform'],
        'ClaudeCodePlatform' => ['PlatformComponent'],
        'CodexPlatform' => ['PlatformComponent'],
        'CoherePlatform' => ['PlatformComponent'],
        'DecartPlatform' => ['PlatformComponent'],
        'DeepgramPlatform' => ['PlatformComponent'],
        'DeepSeekPlatform' => ['+GenericPlatform'],
        'DockerModelRunnerPlatform' => ['+GenericPlatform'],
        'ElevenLabsPlatform' => ['PlatformComponent'],
        'FailoverPlatform' => ['PlatformComponent'],
        'GeminiPlatform' => ['PlatformComponent'],
        'GenericPlatform' => ['PlatformComponent'],
        'HuggingFacePlatform' => ['PlatformComponent'],
        'LmStudioPlatform' => ['+GenericPlatform'],
        'MetaPlatform' => ['PlatformComponent'],
        'MiniMaxPlatform' => ['PlatformComponent'],
        'MistralPlatform' => ['+GenericPlatform'],
        'ModelsDevPlatform' => ['+GenericPlatform', '+AnthropicPlatform', 'BedrockPlatform', '+VertexAiPlatform'],
        'OllamaPlatform' => ['PlatformComponent'],
        'OpenAiPlatform' => ['+OpenResponsesPlatform'],
        'OpenResponsesPlatform' => ['PlatformComponent'],
        'OpenRouterPlatform' => ['+GenericPlatform'],
        'OvhPlatform' => ['+GenericPlatform'],
        'PerplexityPlatform' => ['PlatformComponent'],
        'ReplicatePlatform' => ['+MetaPlatform'],
        'ScalewayPlatform' => ['+GenericPlatform', '+OpenResponsesPlatform'],
        'TransformersPhpPlatform' => ['PlatformComponent'],
        'VertexAiPlatform' => ['+GeminiPlatform'],
        'VoyagePlatform' => ['PlatformComponent'],
        'StoreComponent' => ['PlatformComponent'],
        'AzureSearchStore' => ['+StoreComponent'],
        'CacheStore' => ['+StoreComponent'],
        'ChromaDbStore' => ['+StoreComponent'],
        'ClickHouseStore' => ['+StoreComponent'],
        'CloudflareStore' => ['+StoreComponent'],
        'ElasticsearchStore' => ['+StoreComponent'],
        'ManticoreSearchStore' => ['+StoreComponent'],
        'MariaDbStore' => ['+StoreComponent'],
        'S3Vectors' => ['+StoreComponent'],
        'MeilisearchStore' => ['+StoreComponent'],
        'MilvusStore' => ['+StoreComponent'],
        'MongoDbStore' => ['+StoreComponent'],
        'Neo4jStore' => ['+StoreComponent'],
        'OpenSearchStore' => ['+StoreComponent'],
        'PineconeStore' => ['+StoreComponent'],
        'PostgresStore' => ['+StoreComponent'],
        'QdrantStore' => ['+StoreComponent'],
        'RedisStore' => ['+StoreComponent'],
        'SqliteStore' => ['+StoreComponent'],
        'SupabaseStore' => ['+StoreComponent'],
        'SurrealDbStore' => ['+StoreComponent'],
        'TypesenseStore' => ['+StoreComponent'],
        'VektorStore' => ['+StoreComponent'],
        'WeaviateStore' => ['+StoreComponent'],
        'MateComponent' => [],
        'MateMonologBridge' => ['MateComponent'],
        'MateSymfonyBridge' => ['MateComponent'],
    ]);

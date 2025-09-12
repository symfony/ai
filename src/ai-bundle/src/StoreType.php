<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
enum StoreType: string
{
    case AzureSearch = 'azure_search';
    case Cache = 'cache';
    case ChromaDb = 'chroma_db';
    case ClickHouse = 'clickhouse';
    case Cloudflare = 'cloudflare';
    case Meilisearch = 'meilisearch';
    case Memory = 'memory';
    case Milvus = 'milvus';
    case MongoDb = 'mongodb';
    case Neo4j = 'neo4j';
    case Pinecone = 'pinecone';
    case Qdrant = 'qdrant';
    case SurrealDb = 'surreal_db';
    case Typesense = 'typesense';
    case Weaviate = 'weaviate';
}

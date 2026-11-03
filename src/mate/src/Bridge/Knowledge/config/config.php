<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Mate\Bridge\Knowledge\Capability\ReadTool;
use Symfony\AI\Mate\Bridge\Knowledge\Capability\SearchTool;
use Symfony\AI\Mate\Bridge\Knowledge\Capability\TocTool;
use Symfony\AI\Mate\Bridge\Knowledge\Provider\ProviderRegistry;
use Symfony\AI\Mate\Bridge\Knowledge\Service\ChunkBuilder;
use Symfony\AI\Mate\Bridge\Knowledge\Service\GitFetcher;
use Symfony\AI\Mate\Bridge\Knowledge\Service\KeywordSearcher;
use Symfony\AI\Mate\Bridge\Knowledge\Service\KnowledgeCache;
use Symfony\AI\Mate\Bridge\Knowledge\Service\SearcherInterface;
use Symfony\AI\Mate\Bridge\Knowledge\Service\TocBuilder;
use Symfony\AI\Store\Document\Loader\RstLoader;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $configurator) {
    $configurator->parameters()
        ->set('ai_mate_knowledge.cache_dir', '%mate.root_dir%/var/cache/knowledge')
        ->set('ai_mate_knowledge.cache_ttl_seconds', 86400);

    $services = $configurator->services();

    $services->set(GitFetcher::class);
    $services->set(RstLoader::class);

    $services->set(TocBuilder::class);

    $services->set(ChunkBuilder::class)
        ->args([service(RstLoader::class)]);

    $services->set(KnowledgeCache::class)
        ->args([
            '%ai_mate_knowledge.cache_dir%',
            service(TocBuilder::class),
            service(ChunkBuilder::class),
            '%ai_mate_knowledge.cache_ttl_seconds%',
        ]);

    $services->set(KeywordSearcher::class);
    $services->alias(SearcherInterface::class, KeywordSearcher::class);

    $services->set(ProviderRegistry::class)
        ->args([tagged_iterator('ai_mate.knowledge_provider')]);

    $services->set(TocTool::class)
        ->args([
            service(ProviderRegistry::class),
            service(KnowledgeCache::class),
        ]);

    $services->set(ReadTool::class)
        ->args([
            service(ProviderRegistry::class),
            service(KnowledgeCache::class),
        ]);

    $services->set(SearchTool::class)
        ->args([
            service(ProviderRegistry::class),
            service(KnowledgeCache::class),
            service(SearcherInterface::class),
        ]);
};

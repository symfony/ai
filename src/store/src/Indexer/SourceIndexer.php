<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Indexer;

use Symfony\AI\Store\Document\LoaderInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\IndexerInterface;

/**
 * Indexer that loads documents from sources (file paths, URLs, etc.) and processes them.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SourceIndexer implements IndexerInterface
{
    public function __construct(
        private LoaderInterface $loader,
        private DocumentProcessor $processor,
    ) {
    }

    /**
     * @param string|iterable<string> $input Source identifier (file path, URL, etc.) or iterable of sources
     */
    public function index(string|iterable|object $input, array $options = []): void
    {
        if (\is_object($input) && !$input instanceof \Traversable) {
            throw new InvalidArgumentException(\sprintf('SourceIndexer expects a string or iterable of strings, got "%s".', $input::class));
        }

        $sources = \is_string($input) ? [$input] : $input;

        $documents = (function () use ($sources) {
            foreach ($sources as $source) {
                if (!\is_string($source)) {
                    throw new InvalidArgumentException(\sprintf('SourceIndexer expects sources to be strings, got "%s".', get_debug_type($source)));
                }
                yield from $this->loader->load($source);
            }
        })();

        $this->processor->process($documents, $options);
    }
}

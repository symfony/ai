<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Knowledge\Provider;

/**
 * Describes a documentation source that can be cloned, indexed, and crawled.
 *
 * Implementations are registered as services tagged `ai_mate.knowledge_provider`.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface DocsProviderInterface
{
    /**
     * Unique slug used in tool arguments (e.g. "symfony").
     */
    public function getName(): string;

    /**
     * Human-readable title (e.g. "Symfony Documentation").
     */
    public function getTitle(): string;

    /**
     * Short description shown by the `knowledge-providers` tool.
     */
    public function getDescription(): string;

    /**
     * Source format. Currently only "rst" is supported.
     */
    public function getFormat(): string;

    /**
     * Idempotently fetch (clone or pull) the documentation source under $cacheDir.
     *
     * @return string Absolute path to the entry-point file (the file containing
     *                the top-level `.. toctree::`, typically index.rst)
     */
    public function sync(string $cacheDir): string;
}

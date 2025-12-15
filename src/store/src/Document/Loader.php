<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document;

use Symfony\AI\Store\Exception\RuntimeException;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Loader
{
    /**
     * @param iterable<class-string<SourceInterface>, SourceLoaderInterface> $sourceLoaders
     */
    public function __construct(
        private readonly iterable $sourceLoaders,
    ) {
    }

    /**
     * @return iterable<EmbeddableDocumentInterface>
     */
    public function load(iterable $sources): iterable
    {
        foreach ($sources as $source) {
            if (!$source instanceof SourceInterface) {
                throw new RuntimeException(\sprintf('Source must implement "%s", "%s" given.', SourceInterface::class, $source::class));
            }

            if (!isset($this->sourceLoaders[$source::class])) {
                throw new RuntimeException(\sprintf('No loader registered for source of type "%s".', $source::class));
            }

            yield from $this->sourceLoaders[$source::class]->load($source);
        }
    }
}

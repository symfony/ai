<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document\Loader;

use Symfony\AI\Store\Document\Source\DocumentCollection;
use Symfony\AI\Store\Document\SourceInterface;
use Symfony\AI\Store\Document\SourceLoaderInterface;

/**
 * Loader that returns preloaded documents from memory.
 * Useful for testing or when documents are already available as objects.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class DocumentCollectionLoader implements SourceLoaderInterface
{
    public static function createSource(array|string $source): iterable
    {
        if (!\is_array($source)) {
            throw new \InvalidArgumentException('Source must be an array of EmbeddableDocumentInterface instances.');
        }

        yield new DocumentCollection($source);
    }

    public static function supportedSource(): string
    {
        return DocumentCollection::class;
    }

    public function load(SourceInterface|DocumentCollection $source, array $options = []): iterable
    {
        yield from $source->getDocuments();
    }
}

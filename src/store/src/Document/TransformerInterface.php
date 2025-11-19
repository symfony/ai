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

/**
 * A Transformer is designed to mutate a stream of embeddable with the purpose of preparing them for indexing.
 * It can reduce or expand the number of documents, modify their content or metadata.
 * It should not act blocking, but is expected to iterate over incoming documents and yield prepared ones.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface TransformerInterface
{
    /**
     * @param iterable<EmbeddableDocumentInterface> $documents
     * @param array<string, mixed>                  $options
     *
     * @return iterable<EmbeddableDocumentInterface>
     */
    public function transform(iterable $documents, array $options = []): iterable;
}

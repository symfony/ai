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

use Symfony\AI\Platform\Vector\Vector;

/**
 * Interface for converting a collection of Embeddable documents into VectorDocuments
 * and for vectorizing individual strings.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
interface VectorizerInterface
{
    /**
     * @param EmbeddableDocumentInterface[] $documents
     * @param array<string, mixed>          $options   Options to pass to the underlying platform
     *
     * @return VectorDocument[]
     */
    public function vectorizeEmbeddableDocuments(array $documents, array $options = []): array;

    /**
     * Vectorizes a single string or Stringable object into a Vector.
     *
     * @param array<string, mixed> $options Options to pass to the underlying platform
     */
    public function vectorize(string|\Stringable $string, array $options = []): Vector;
}

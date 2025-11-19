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
 * Interface for vectorizing strings and EmbeddableDocuments into Vectors and VectorDocuments.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
interface VectorizerInterface
{
    /**
     * Vectorizes strings or EmbeddableDocuments into Vectors or VectorDocuments.
     *
     * @param string|\Stringable|EmbeddableDocumentInterface|array<string|\Stringable>|array<EmbeddableDocumentInterface> $values  The values to vectorize
     * @param array<string, mixed>                                                                                        $options Options to pass to the underlying platform
     *
     * @return Vector|VectorDocument|array<Vector>|array<VectorDocument>
     *
     * @phpstan-return (
     *     $values is string|\Stringable ? Vector : (
     *         $values is EmbeddableDocumentInterface ? VectorDocument : (
     *             $values is array<string|\Stringable> ? array<Vector> : array<VectorDocument>
     *         )
     *     )
     * )
     */
    public function vectorize(string|\Stringable|EmbeddableDocumentInterface|array $values, array $options = []): Vector|VectorDocument|array;
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document\Transformer;

use Symfony\AI\Store\Document\TransformerInterface;

final class ChainTransformer implements TransformerInterface
{
    /**
     * @var TransformerInterface[]
     */
    private readonly array $transformers;

    /**
     * @param iterable<TransformerInterface> $transformers
     */
    public function __construct(iterable $transformers)
    {
        $this->transformers = $transformers instanceof \Traversable ? iterator_to_array($transformers) : $transformers;
    }

    public function transform(iterable $documents, array $options = []): iterable
    {
        foreach ($this->transformers as $transformer) {
            $documents = $transformer->transform($documents, $options);
        }

        return $documents;
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Document\Transformer;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Transformer\ChainTransformer;
use Symfony\AI\Store\Document\TransformerInterface;
use Symfony\Component\Uid\Uuid;

final class ChainTransformerTest extends TestCase
{
    public function testChainTransformerAppliesAllTransformersInOrder()
    {
        $transformerA = new class implements TransformerInterface {
            public function transform(iterable $documents, array $options = []): iterable
            {
                foreach ($documents as $document) {
                    yield new TextDocument($document->getId(), $document->getContent().'-A');
                }
            }
        };

        $transformerB = new class implements TransformerInterface {
            public function transform(iterable $documents, array $options = []): iterable
            {
                foreach ($documents as $document) {
                    yield new TextDocument($document->getId(), $document->getContent().'-B');
                }
            }
        };

        $chain = new ChainTransformer([$transformerA, $transformerB]);
        $documents = [
            new TextDocument(Uuid::v4(), 'foo'),
            new TextDocument(Uuid::v4(), 'bar'),
        ];

        $result = iterator_to_array($chain->transform($documents));

        $this->assertSame('foo-A-B', $result[0]->getContent());
        $this->assertSame('bar-A-B', $result[1]->getContent());
    }

    public function testChainTransformerWithNoTransformersReturnsInput()
    {
        $chain = new ChainTransformer([]);
        $documents = [new TextDocument(Uuid::v4(), 'baz')];

        $result = iterator_to_array($chain->transform($documents));

        $this->assertSame('baz', $result[0]->getContent());
    }
}

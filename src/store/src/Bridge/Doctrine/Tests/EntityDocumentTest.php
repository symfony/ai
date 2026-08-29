<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Doctrine\EntityDocument;
use Symfony\AI\Store\Bridge\Doctrine\EntityVectorDocument;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Article;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Exception\InvalidArgumentException;

final class EntityDocumentTest extends TestCase
{
    public function testTakesItsContentFromTheEntity(): void
    {
        $document = EntityDocument::fromEntity(new Article('Reality TV', 'A show about people.'), 1);

        $this->assertSame(1, $document->getId());
        $this->assertSame("Reality TV\nA show about people.", $document->getContent());
    }

    public function testAcceptsContentBuiltElsewhere(): void
    {
        $document = EntityDocument::fromEntity(new Article('Reality TV'), 1, 'something else entirely');

        $this->assertSame('something else entirely', $document->getContent());
    }

    public function testRefusesAnEntityThatDoesNotSayWhatToEmbed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Cannot derive indexable content/');

        EntityDocument::fromEntity(new \stdClass(), 1);
    }

    public function testRefusesEmptyContent(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EntityDocument::fromEntity(new Article('Reality TV'), 1, '   ');
    }

    public function testKeepsItsEntityAndMetadata(): void
    {
        $article = new Article('Reality TV');
        $metadata = new Metadata(['source' => 'test']);

        $document = EntityDocument::fromEntity($article, 42, metadata: $metadata);

        $this->assertSame($article, $document->getEntity());
        $this->assertSame($metadata, $document->getMetadata());
    }

    public function testScoringKeepsEverythingElse(): void
    {
        $article = new Article('Reality TV');
        $document = new EntityVectorDocument($article, 42, new Vector([1.0]));

        $scored = $document->withScore(0.25);

        $this->assertSame(0.25, $scored->getScore());
        $this->assertSame($article, $scored->getEntity());
        $this->assertSame(42, $scored->getId());
        $this->assertNull($document->getScore(), 'the original document is left untouched');
    }
}

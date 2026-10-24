<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Tests\Mapping;

use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\Embedding;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\EmbeddingMapping;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Article;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Note;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Page;
use Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures\Untracked;
use Symfony\AI\Store\Bridge\Doctrine\Tests\SqliteEntityManagerTrait;
use Symfony\AI\Store\Exception\InvalidArgumentException;

final class EmbeddingMappingTest extends TestCase
{
    use SqliteEntityManagerTrait;

    public function testFindsTheFieldMarkedAsEmbedding()
    {
        $this->assertSame('embedding', $this->mapping(Article::class)->getVectorField());
    }

    public function testFindsAPrivateEmbeddingOfAParentClass()
    {
        $mapping = EmbeddingMapping::fromClassMetadata($this->createEntityManager()->getClassMetadata(Page::class));

        $this->assertSame('embedding', $mapping?->getVectorField());
        $this->assertSame('Cats', $mapping->getContent(new Page('Cats')));
    }

    public function testIgnoresAnEntityWithoutEmbedding()
    {
        $this->assertNull(EmbeddingMapping::fromClassMetadata($this->createEntityManager()->getClassMetadata(Untracked::class)));
    }

    public function testJoinsTheValuesOfTheSourceFields()
    {
        $mapping = $this->mapping(Article::class);

        $this->assertSame("Cats\nAbout cats.", $mapping->getContent(new Article('Cats', 'About cats.')));
        $this->assertSame('Cats', $mapping->getContent(new Article('Cats')));
    }

    public function testPutsEachItemOfAListOnItsOwnLine()
    {
        $article = new Article('Cats');
        $article->setTags(['pets', ['name' => 'Tom', 'kind' => 'cat'], '']);

        $this->assertSame("Cats\npets\nTom, cat", $this->mapping(Article::class)->getContent($article));
    }

    public function testRefusesAValueWithoutText()
    {
        $metadata = $this->metadataWithEmbedding(new class {
            #[Embedding(from: ['publishedAt'])]
            public mixed $embedding = null;
        }, ['embedding', 'publishedAt']);
        $metadata->method('getFieldValue')->willReturn(new \DateTimeImmutable());

        $mapping = EmbeddingMapping::fromClassMetadata($metadata);
        $this->assertNotNull($mapping);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is built from "publishedAt", which holds a "DateTimeImmutable" that has no text');

        $mapping->getContent(new \stdClass());
    }

    public function testAsksAnIndexableEntityForItsContent()
    {
        $this->assertSame('Note: Fish', $this->mapping(Note::class)->getContent(new Note('Fish')));
    }

    public function testIsAffectedOnlyByItsSourceFields()
    {
        $mapping = $this->mapping(Article::class);

        $this->assertTrue($mapping->isAffectedBy(['title' => ['Cats', 'Dogs']]));
        $this->assertFalse($mapping->isAffectedBy(['views' => [1, 2]]));
    }

    public function testIsAffectedOnlyByItsSourceFieldsWhenTheEntityBuildsTheContent()
    {
        $mapping = $this->mapping(Note::class);

        $this->assertTrue($mapping->isAffectedBy(['title' => ['Cats', 'Dogs']]));
        $this->assertFalse($mapping->isAffectedBy(['updatedAt' => [null, new \DateTimeImmutable()]]));
    }

    public function testRefusesAnEmbeddingWithoutAnythingToEmbed()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('needs the fields it is built from');

        EmbeddingMapping::fromClassMetadata($this->metadataWithEmbedding(new class {
            /** @phpstan-ignore argument.type (attribute arguments are not type checked at runtime) */
            #[Embedding(from: [])]
            public mixed $embedding = null;
        }, ['embedding']));
    }

    public function testRefusesASourceFieldThatIsNotMapped()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"missing", which is not a mapped field');

        EmbeddingMapping::fromClassMetadata($this->metadataWithEmbedding(new class {
            #[Embedding(from: ['missing'])]
            public mixed $embedding = null;
        }, ['embedding']));
    }

    public function testRefusesAnEmbeddingThatIsNotMapped()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a mapped field');

        EmbeddingMapping::fromClassMetadata($this->metadataWithEmbedding(new class {
            #[Embedding(from: ['title'])]
            public mixed $embedding = null;
        }, ['title']));
    }

    /**
     * @param class-string $class
     */
    private function mapping(string $class): EmbeddingMapping
    {
        $mapping = EmbeddingMapping::fromClassMetadata($this->createEntityManager()->getClassMetadata($class));
        $this->assertNotNull($mapping);

        return $mapping;
    }

    /**
     * @param list<string> $mappedFields
     *
     * @return ClassMetadata<object>&MockObject
     */
    private function metadataWithEmbedding(object $entity, array $mappedFields): ClassMetadata&MockObject
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getReflectionClass')->willReturn(new \ReflectionClass($entity));
        $metadata->method('getName')->willReturn($entity::class);
        $metadata->method('hasField')->willReturnCallback(static fn (string $field): bool => \in_array($field, $mappedFields, true));

        return $metadata;
    }
}

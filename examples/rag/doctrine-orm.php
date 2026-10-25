<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\AI\Fixtures\Movies;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Types\VectorType;
use Symfony\AI\Store\Bridge\Doctrine\EmbeddingListener;
use Symfony\AI\Store\Bridge\Doctrine\EntityEmbedder;
use Symfony\AI\Store\Bridge\Doctrine\EntityIndexer;
use Symfony\AI\Store\Bridge\Doctrine\EntityVectorDocument;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\Embedding;
use Symfony\AI\Store\Bridge\Doctrine\Orm\Query\VectorDistance;
use Symfony\AI\Store\Bridge\Doctrine\OrmStore;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Retriever;

require_once dirname(__DIR__).'/bootstrap.php';

#[ORM\Entity]
#[ORM\Table(name: 'movie')]
#[ORM\Index(name: 'movie_embedding_idx', columns: ['embedding'])]
final class Movie
{
    #[ORM\Id]
    #[ORM\Column]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(type: 'vector', nullable: true)]
    #[Embedding(from: ['title', 'director', 'description'])]
    public ?VectorInterface $embedding = null;

    public function __construct(
        #[ORM\Column]
        public string $title,
        #[ORM\Column]
        public string $director,
        #[ORM\Column(type: 'text')]
        public string $description,
    ) {
    }
}

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());
$embedder = new EntityEmbedder($vectorizer = new Vectorizer($platform, 'text-embedding-3-small', logger()));

// initialize the entity manager
Type::addType(VectorType::NAME, VectorType::class);
$configuration = ORMSetup::createAttributeMetadataConfiguration([__DIR__], true);
$configuration->addCustomNumericFunction(VectorDistance::NAME, VectorDistance::class);
if (\PHP_VERSION_ID >= 80400) {
    $configuration->enableNativeLazyObjects(true);
}
$connection = DriverManager::getConnection((new DsnParser())->parse('pdo-pgsql://postgres:postgres@127.0.0.1:5432/my_database'), $configuration);
$entityManager = new EntityManager($connection, $configuration, $eventManager = new EventManager());

// create the table, then what a migration would add: the vector size and an HNSW index
$connection->executeStatement('CREATE EXTENSION IF NOT EXISTS vector');
$schemaTool = new SchemaTool($entityManager);
$schemaTool->dropSchema([$entityManager->getClassMetadata(Movie::class)]);
$schemaTool->createSchema([$entityManager->getClassMetadata(Movie::class)]);
$connection->executeStatement('ALTER TABLE movie ALTER COLUMN embedding TYPE vector(1536)');
$connection->executeStatement('DROP INDEX movie_embedding_idx');
$connection->executeStatement('CREATE INDEX movie_embedding_idx ON movie USING hnsw (embedding vector_cosine_ops)');

// an existing table: the movies are there, their embeddings not yet
foreach (Movies::all() as $movie) {
    $entityManager->persist(new Movie($movie['title'], $movie['director'], $movie['description']));
}
$entityManager->flush();
$entityManager->clear();

// a) embed the whole table in batches
(new EntityIndexer($entityManager, $embedder))->index(Movie::class, ['only_missing' => true]);

// b) from now on, embed movies whenever they are created or their embedded fields change
$eventManager->addEventListener(Events::onFlush, new EmbeddingListener($embedder));
$entityManager->persist(new Movie('Paprika', 'Satoshi Kon', 'A device that lets therapists enter dreams is stolen, and dreams start leaking into reality.'));
$entityManager->flush();

// search the movies by a text, through the store
$retriever = new Retriever(new OrmStore($entityManager, Movie::class), $vectorizer);

echo 'Movies about dreams:'.\PHP_EOL;
foreach ($retriever->retrieve('movies about dreams', ['limit' => 3]) as $document) {
    if ($document instanceof EntityVectorDocument) {
        echo sprintf(' - %s (%.3f)', $document->getEntity()->title, $document->getScore()).\PHP_EOL;
    }
}

// or query the entities directly with DQL, e.g. the movies most similar to "Inception", without itself
$inception = $entityManager->getRepository(Movie::class)->findOneBy(['title' => 'Inception']);
$similar = $entityManager->createQuery('SELECT m FROM Movie m WHERE m.id != :id ORDER BY VECTOR_DISTANCE(m.embedding, :vector) ASC')
    ->setParameter('id', $inception->id)
    ->setParameter('vector', $inception->embedding, VectorType::NAME)
    ->setMaxResults(3)
    ->getResult();

echo \PHP_EOL.'Movies similar to Inception:'.\PHP_EOL;
foreach ($similar as $movie) {
    echo ' - '.$movie->title.\PHP_EOL;
}

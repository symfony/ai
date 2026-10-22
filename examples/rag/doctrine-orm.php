<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\AI\Fixtures\Movies;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\EntityLoader;
use Symfony\AI\Store\Bridge\Doctrine\IndexableEntityInterface;
use Symfony\AI\Store\Bridge\Doctrine\Store;
use Symfony\AI\Store\Bridge\Doctrine\Type\VectorType;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\Indexer\SourceIndexer;
use Symfony\AI\Store\Query\VectorQuery;

require_once dirname(__DIR__).'/bootstrap.php';

#[ORM\Entity]
#[ORM\Table(name: 'movie')]
#[ORM\Index(name: 'movie_embedding_idx', columns: ['embedding'])]
final class Movie implements IndexableEntityInterface
{
    #[ORM\Id]
    #[ORM\Column]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(type: 'vector', nullable: true)]
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

    public function getIndexableContent(): string
    {
        return 'Title: '.$this->title.\PHP_EOL.'Director: '.$this->director.\PHP_EOL.'Description: '.$this->description;
    }
}

// initialize the entity manager
Type::addType('vector', VectorType::class);
$configuration = ORMSetup::createAttributeMetadataConfiguration([__DIR__], true);
if (\PHP_VERSION_ID >= 80400) {
    $configuration->enableNativeLazyObjects(true);
}
$connection = DriverManager::getConnection((new DsnParser())->parse('pdo-pgsql://postgres:postgres@127.0.0.1:5432/my_database'), $configuration);
$connection->getDatabasePlatform()->registerDoctrineTypeMapping('vector', 'vector');
$entityManager = new EntityManager($connection, $configuration);

// create the table, which needs the pgvector extension first
$connection->executeStatement('CREATE EXTENSION IF NOT EXISTS vector');
$schemaTool = new SchemaTool($entityManager);
$schemaTool->dropSchema([$entityManager->getClassMetadata(Movie::class)]);
$schemaTool->createSchema([$entityManager->getClassMetadata(Movie::class)]);

foreach (Movies::all() as $movie) {
    $entityManager->persist(new Movie($movie['title'], $movie['director'], $movie['description']));
}
$entityManager->flush();
$entityManager->clear();

// size the vector column and create the HNSW index
$store = new Store($entityManager, Movie::class);
$store->setup(['dimensions' => 1536]);

// embed every movie
$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());
$vectorizer = new Vectorizer($platform, 'text-embedding-3-small', logger());
$indexer = new SourceIndexer(new EntityLoader($entityManager), new DocumentProcessor($vectorizer, $store, logger: logger()));
$indexer->index(Movie::class);

// find the movies most similar to "Inception", without "Inception" itself
$inception = $entityManager->getRepository(Movie::class)->findOneBy(['title' => 'Inception']);
$query = new VectorQuery($vectorizer->vectorize($inception->getIndexableContent()));

$documents = $store->query($query, ['limit' => 3, 'where' => 'id != :self', 'params' => ['self' => $inception->id]]);

foreach ($documents as $document) {
    echo sprintf('%s (%.3f)', $document->getEntity()->title, $document->getScore()).\PHP_EOL;
}

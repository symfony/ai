<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Symfony\AI\Chat\Exception\InvalidArgumentException;
use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class DoctrineDbalMessageStore implements ManagedStoreInterface, MessageStoreInterface
{
    public function __construct(
        private readonly string $tableName,
        private readonly DBALConnection $dbalConnection,
        private readonly SerializerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageNormalizer(),
        ], [new JsonEncoder()]),
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $schema = $this->dbalConnection->createSchemaManager()->introspectSchema();

        if ($schema->hasTable($this->tableName)) {
            return;
        }

        $this->addTableToSchema($schema);
    }

    public function drop(): void
    {
        $schema = $this->dbalConnection->createSchemaManager()->introspectSchema();

        if (!$schema->hasTable($this->tableName)) {
            return;
        }

        $queryBuilder = $this->dbalConnection->createQueryBuilder()
            ->delete($this->tableName);

        $this->dbalConnection->transactional(fn (Connection $connection): Result => $connection->executeQuery(
            $queryBuilder->getSQL(),
        ));
    }

    public function save(MessageBag $messages): void
    {
        $queryBuilder = $this->dbalConnection->createQueryBuilder()
            ->insert($this->tableName)
            ->values([
                'messages' => '?',
            ]);

        $this->dbalConnection->transactional(fn (Connection $connection): Result => $connection->executeQuery(
            $queryBuilder->getSQL(),
            [
                $this->serializer->serialize($messages->getMessages(), 'json'),
            ],
            $queryBuilder->getParameterTypes(),
        ));
    }

    public function load(): MessageBag
    {
        $queryBuilder = $this->dbalConnection->createQueryBuilder()
            ->select('messages')
            ->from($this->tableName)
        ;

        $result = $this->dbalConnection->transactional(static fn (Connection $connection): Result => $connection->executeQuery(
            $queryBuilder->getSQL(),
        ));

        $messages = array_map(
            fn (array $payload): array => $this->serializer->deserialize($payload['messages'], MessageInterface::class.'[]', 'json'),
            $result->fetchAllAssociative(),
        );

        return new MessageBag(...array_merge(...$messages));
    }

    private function addTableToSchema(Schema $schema): void
    {
        $table = $schema->createTable($this->tableName);
        $table->addOption('_symfony_ai_chat_table_name', $this->tableName);
        $idColumn = $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true);
        $table->addColumn('messages', Types::TEXT)
            ->setNotnull(true);
        if (class_exists(PrimaryKeyConstraint::class)) {
            $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(null, [
                new UnqualifiedName(Identifier::unquoted('id')),
            ], true));
        } else {
            $table->setPrimaryKey(['id']);
        }

        // We need to create a sequence for Oracle and set the id column to get the correct nextval
        if ($this->dbalConnection->getDatabasePlatform() instanceof OraclePlatform) {
            $serverVersion = $this->dbalConnection->executeQuery("SELECT version FROM product_component_version WHERE product LIKE 'Oracle Database%'")->fetchOne();
            if (version_compare($serverVersion, '12.1.0', '>=')) {
                $idColumn->setAutoincrement(false); // disable the creation of SEQUENCE and TRIGGER
                $idColumn->setDefault($this->tableName.'_seq.nextval');

                $schema->createSequence($this->tableName.'_seq');
            }
        }

        foreach ($schema->toSql($this->dbalConnection->getDatabasePlatform()) as $sql) {
            $this->dbalConnection->executeQuery($sql);
        }
    }
}

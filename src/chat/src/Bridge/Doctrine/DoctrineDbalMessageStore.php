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

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Psr\Clock\ClockInterface;
use Symfony\AI\Chat\Exception\InvalidArgumentException;
use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageBagNormalizer;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
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
        private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageBagNormalizer(new MessageNormalizer()),
            new MessageNormalizer(),
        ], [new JsonEncoder()]),
        private readonly ClockInterface $clock = new MonotonicClock(),
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

    public function drop(?string $identifier = null): void
    {
        $schema = $this->dbalConnection->createSchemaManager()->introspectSchema();

        if (!$schema->hasTable($this->tableName)) {
            return;
        }

        $queryBuilder = $this->dbalConnection->createQueryBuilder()
            ->delete($this->tableName);

        if (null !== $identifier) {
            $queryBuilder->where('chat_identifier = ?');
            $this->dbalConnection->executeStatement($queryBuilder->getSQL(), [$identifier]);

            return;
        }

        $this->dbalConnection->executeStatement($queryBuilder->getSQL());
    }

    public function save(MessageBag $messages, ?string $identifier = null): void
    {
        $queryBuilder = $this->dbalConnection->createQueryBuilder()
            ->insert($this->tableName)
            ->values([
                'chat_identifier' => '?',
                'messages' => '?',
                'added_at' => '?',
            ]);

        $this->dbalConnection->executeStatement($queryBuilder->getSQL(), [
            $identifier,
            $this->serializer->serialize($this->serializer->normalize($messages), 'json'),
            $this->clock->now()->getTimestamp(),
        ]);
    }

    public function load(?string $identifier = null): MessageBag
    {
        $queryBuilder = $this->dbalConnection->createQueryBuilder()
            ->select('messages')
            ->from($this->tableName)
            ->orderBy('added_at', 'DESC')
            ->setMaxResults(1);

        if (null !== $identifier) {
            $queryBuilder->where('chat_identifier = ?');
        }

        $result = $this->dbalConnection->executeQuery(
            $queryBuilder->getSQL(),
            null !== $identifier ? [$identifier] : [],
        );

        $row = $result->fetchAssociative();

        if (false === $row) {
            return new MessageBag();
        }

        $payload = json_decode($row['messages'], true);

        return $this->serializer->denormalize($payload, MessageBag::class);
    }

    private function addTableToSchema(Schema $schema): void
    {
        $table = $schema->createTable($this->tableName);
        $table->addOption('_symfony_ai_chat_table_name', $this->tableName);
        $idColumn = $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true);
        $table->addColumn('chat_identifier', Types::STRING)
            ->setNotnull(false);
        $table->addColumn('messages', Types::TEXT)
            ->setNotnull(true);
        $table->addColumn('added_at', Types::INTEGER)
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

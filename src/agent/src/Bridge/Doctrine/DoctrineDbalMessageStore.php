<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Doctrine;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Psr\Clock\ClockInterface;
use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\MessageStore\ConflictDetector;
use Symfony\AI\Agent\MessageStore\ManagedMessageStoreInterface;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Agent\Toolbox\Source\SourceCollectionNormalizer;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Serializer\MessageBagNormalizer;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;

/**
 * Keeps each conversation in a row, updated only while its version is the one that was loaded.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class DoctrineDbalMessageStore implements ManagedMessageStoreInterface, MessageStoreInterface
{
    public function __construct(
        private readonly string $tableName,
        private readonly DBALConnection $dbalConnection,
        private readonly SerializerInterface $serializer = new Serializer([
            new MessageBagNormalizer(),
            new SourceCollectionNormalizer(),
        ], [new JsonEncoder()]),
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $schemaManager = $this->dbalConnection->createSchemaManager();

        $schema = $schemaManager->introspectSchema();

        if ($schema->hasTable($this->tableName)) {
            return;
        }

        if (class_exists(ComparatorConfig::class)) {
            $comparator = $schemaManager->createComparator(new ComparatorConfig(false, false));
        } else {
            // Backwards compatibility for doctrine/dbal 3.x
            $comparator = $schemaManager->createComparator();
        }

        $migrations = $this->dbalConnection->getDatabasePlatform()->getAlterSchemaSQL($comparator->compareSchemas($schema, $this->addTableToSchema($schema)));

        foreach ($migrations as $sql) {
            $this->dbalConnection->executeQuery($sql);
        }
    }

    public function drop(): void
    {
        $schema = $this->dbalConnection->createSchemaManager()->introspectSchema();

        if (!$schema->hasTable($this->tableName)) {
            return;
        }

        $this->dbalConnection->executeStatement($this->dbalConnection->createQueryBuilder()->delete($this->tableName)->getSQL());
    }

    public function delete(AbstractUid&TimeBasedUidInterface $id): void
    {
        $this->dbalConnection->createQueryBuilder()
            ->delete($this->tableName)
            ->where('id = ?')
            ->setParameter(0, $id->toRfc4122())
            ->executeStatement();
    }

    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBag
    {
        $row = $this->fetch($id);

        if (null === $row) {
            return MessageBag::forId($id);
        }

        return $this->serializer->deserialize($row['messages'], MessageBag::class, 'json');
    }

    public function save(MessageBag $messages): void
    {
        $row = $this->fetch($messages->getId());
        $stored = null === $row ? (new MessageBag())->withId($messages->getId()) : $this->serializer->deserialize($row['messages'], MessageBag::class, 'json');

        ConflictDetector::assertContinues($stored, $messages);

        $values = [
            'id' => $messages->getId()->toRfc4122(),
            'version' => ConflictDetector::version($messages),
            'messages' => $this->serializer->serialize($messages, 'json'),
            'updated_at' => $this->clock->now()->getTimestamp(),
        ];

        if (null === $row) {
            try {
                $this->dbalConnection->insert($this->tableName, $values);
            } catch (UniqueConstraintViolationException $exception) {
                throw ConflictException::forMessages($messages, $exception);
            }

            return;
        }

        $updated = $this->dbalConnection->update($this->tableName, $values, [
            'id' => $values['id'],
            'version' => $row['version'],
        ]);

        // Some platforms count only changed rows, so an unchanged row is no conflict
        if (0 === $updated && $row['version'] !== ($this->fetch($messages->getId())['version'] ?? null)) {
            throw ConflictException::forMessages($messages);
        }
    }

    /**
     * @return array{version: string, messages: string}|null
     */
    private function fetch(AbstractUid $id): ?array
    {
        $row = $this->dbalConnection->createQueryBuilder()
            ->select('version', 'messages')
            ->from($this->tableName)
            ->where('id = ?')
            ->setParameter(0, $id->toRfc4122())
            ->executeQuery()
            ->fetchAssociative();

        return false === $row ? null : $row;
    }

    private function addTableToSchema(Schema $currentSchema): Schema
    {
        $schema = clone $currentSchema;

        $table = $schema->createTable($this->tableName);
        $table->addOption('_symfony_ai_message_store_table_name', $this->tableName);
        $table->addColumn('id', Types::STRING, ['length' => 36])
            ->setNotnull(true);
        $table->addColumn('version', Types::STRING, ['length' => 36])
            ->setNotnull(true);
        $table->addColumn('messages', Types::TEXT)
            ->setNotnull(true);
        $table->addColumn('updated_at', Types::INTEGER)
            ->setNotnull(true);

        if (class_exists(PrimaryKeyConstraint::class)) {
            $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(null, [
                new UnqualifiedName(Identifier::unquoted('id')),
            ], true));
        } else {
            $table->setPrimaryKey(['id']);
        }

        return $schema;
    }
}

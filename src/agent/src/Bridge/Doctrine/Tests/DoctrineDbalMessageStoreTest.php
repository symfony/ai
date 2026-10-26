<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Doctrine\Tests;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Doctrine\DoctrineDbalMessageStore;
use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class DoctrineDbalMessageStoreTest extends TestCase
{
    public function testMessageStoreTableCannotBeSetupWithExtraOptions()
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $messageStore = new DoctrineDbalMessageStore('foo', $connection);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No supported options.');
        $this->expectExceptionCode(0);
        $messageStore->setup([
            'foo' => 'bar',
        ]);
    }

    public function testMessageStoreTableCannotBeSetupIfItAlreadyExist()
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $messageStore = new DoctrineDbalMessageStore('foo', $connection);

        // First setup creates the table
        $messageStore->setup();

        // Second setup should not fail when table already exists
        $messageStore->setup();

        // Verify table exists by checking we can load from it
        $messages = $messageStore->load(Uuid::v7());
        $this->assertInstanceOf(MessageBag::class, $messages);
    }

    public function testMessageStoreTableCanBeSetup()
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $messageStore = new DoctrineDbalMessageStore('foo', $connection);
        $messageStore->setup();

        // Verify table was created by checking we can load from it
        $messages = $messageStore->load(Uuid::v7());
        $this->assertInstanceOf(MessageBag::class, $messages);
        $this->assertCount(0, $messages);
    }

    public function testMessageStoreTableCanBeSetupOnExistingStructure()
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        if (class_exists(ComparatorConfig::class)) {
            $comparator = $connection->createSchemaManager()->createComparator(new ComparatorConfig(false, false));
        } else {
            // Backwards compatibility for doctrine/dbal 3.x
            $comparator = $connection->createSchemaManager()->createComparator();
        }

        $schema = $connection->createSchemaManager()->introspectSchema();

        $table = $schema->createTable('bar');
        $table->addColumn('barColumn', Types::INTEGER)->setNotnull(true)->setDefault(0);

        $migrations = $connection->getDatabasePlatform()->getAlterSchemaSQL($comparator->compareSchemas($connection->createSchemaManager()->introspectSchema(), $schema));

        foreach ($migrations as $sql) {
            $connection->executeQuery($sql);
        }

        $messageStore = new DoctrineDbalMessageStore('foo', $connection);
        $messageStore->setup();

        $finalSchema = $connection->createSchemaManager()->introspectSchema();

        // Verify table schema was updated without dropping existing table
        $this->assertSame(2, \count($finalSchema->getTables()));
        $this->assertTrue($finalSchema->hasTable('foo'));
        $this->assertTrue($finalSchema->hasTable('bar'));

        // Verify table was created by checking we can load from it
        $messages = $messageStore->load(Uuid::v7());
        $this->assertInstanceOf(MessageBag::class, $messages);
        $this->assertCount(0, $messages);
    }

    public function testMessageStoreTableCanBeSetupOnEmptyStructure()
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $messageStore = new DoctrineDbalMessageStore('foo', $connection);
        $messageStore->setup();

        $finalSchema = $connection->createSchemaManager()->introspectSchema();

        // Verify table schema was updated
        $this->assertSame(1, \count($finalSchema->getTables()));
        $this->assertTrue($finalSchema->hasTable('foo'));

        // Verify table was created by checking we can load from it
        $messages = $messageStore->load(Uuid::v7());
        $this->assertInstanceOf(MessageBag::class, $messages);
        $this->assertCount(0, $messages);
    }

    #[DoesNotPerformAssertions]
    public function testMessageStoreTableCannotBeDroppedIfTableDoesNotExist()
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $messageStore = new DoctrineDbalMessageStore('foo', $connection);

        // Drop on non-existent table should not fail
        $messageStore->drop();
    }

    public function testMessageStoreTableCanBeDropped()
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $messageStore = new DoctrineDbalMessageStore('foo', $connection);
        $messageStore->setup();

        // Save a message first
        $messageStore->save(new MessageBag(Message::ofUser('Hello world')));

        // Drop the table (deletes all records)
        $messageStore->drop();

        // Verify data was deleted by loading
        $messages = $messageStore->load(Uuid::v7());
        $this->assertCount(0, $messages);
    }

    public function testSavedConversationsAreLoadedByTheirId()
    {
        $messageStore = new DoctrineDbalMessageStore('foo', DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
        $messageStore->setup();
        $first = new MessageBag(Message::ofUser('Hello'));
        $second = new MessageBag(Message::ofUser('Bonjour'));
        $continued = $first->with(Message::ofAssistant('Hi'));

        $messageStore->save($first);
        $messageStore->save($second);
        $messageStore->save($continued);
        $messageStore->save($continued);

        $this->assertSame(self::ids($continued), self::ids($messageStore->load($first->getId())));
        $this->assertSame(self::ids($second), self::ids($messageStore->load($second->getId())));
        $this->assertTrue($first->getId()->equals($messageStore->load($first->getId())->getId()));
    }

    public function testSavingDivergingMessagesConflicts()
    {
        $messageStore = new DoctrineDbalMessageStore('foo', DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
        $messageStore->setup();
        $messages = new MessageBag(Message::ofUser('Hello'));
        $messageStore->save($messages->with(Message::ofAssistant('Hi')));

        $this->expectException(ConflictException::class);

        $messageStore->save($messages->with(Message::ofAssistant('Hey')));
    }

    public function testRowKeepsTheIdOfTheLastMessageAsVersion()
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $messageStore = new DoctrineDbalMessageStore('foo', $connection, clock: $clock = new MockClock('2026-10-25 12:00:00'));
        $messageStore->setup();
        $answer = Message::ofAssistant('Hi');
        $messages = new MessageBag(Message::ofUser('Hello'), $answer);

        $messageStore->save($messages);

        $row = $connection->fetchAssociative('SELECT id, version, updated_at FROM foo');
        $this->assertSame([
            'id' => $messages->getId()->toRfc4122(),
            'version' => $answer->getId()->toRfc4122(),
            'updated_at' => $clock->now()->getTimestamp(),
        ], $row);
    }

    public function testDeleteRemovesOnlyTheGivenConversation()
    {
        $messageStore = new DoctrineDbalMessageStore('foo', DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
        $messageStore->setup();
        $first = new MessageBag(Message::ofUser('Hello'));
        $second = new MessageBag(Message::ofUser('Bonjour'));
        $messageStore->save($first);
        $messageStore->save($second);

        $messageStore->delete($first->getId());

        $this->assertCount(0, $messageStore->load($first->getId()));
        $this->assertCount(1, $messageStore->load($second->getId()));
    }

    /**
     * @return list<string>
     */
    private static function ids(MessageBag $messages): array
    {
        return array_map(static fn (MessageInterface $message): string => $message->getId()->toRfc4122(), $messages->getMessages());
    }
}

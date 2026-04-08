<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\Doctrine\Tests;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Chat\Bridge\Doctrine\DoctrineDbalMessageStore;
use Symfony\AI\Chat\Exception\InvalidArgumentException;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;

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
        $messages = $messageStore->load();
        $this->assertInstanceOf(MessageBag::class, $messages);
    }

    public function testMessageStoreTableCanBeSetup()
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $messageStore = new DoctrineDbalMessageStore('foo', $connection);
        $messageStore->setup();

        // Verify table was created by checking we can load from it
        $messages = $messageStore->load();
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
        $messages = $messageStore->load();
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
        $messages = $messageStore->load();
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
        $messages = $messageStore->load();
        $this->assertCount(0, $messages);
    }

    public function testMessageBagCanBeSaved()
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $messageStore = new DoctrineDbalMessageStore('foo', $connection);
        $messageStore->setup();

        $messageStore->save(new MessageBag(Message::ofUser('Hello world')));

        $messages = $messageStore->load();
        $this->assertCount(1, $messages);
    }

    public function testMessageBagCanBeLoaded()
    {
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageNormalizer(),
        ], [new JsonEncoder()]);

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $messageStore = new DoctrineDbalMessageStore('foo', $connection, $serializer);
        $messageStore->setup();

        $messageStore->save(new MessageBag(
            Message::forSystem('You are an helpful assistant'),
            Message::ofUser('Hello world'),
        ));

        $messages = $messageStore->load();

        $this->assertCount(2, $messages);
        $this->assertInstanceOf(SystemMessage::class, $messages->getMessages()[0]);
        $this->assertInstanceOf(UserMessage::class, $messages->getMessages()[1]);
        $this->assertSame('You are an helpful assistant', $messages->getSystemMessage()->getContent());
        $this->assertSame('Hello world', $messages->getUserMessage()->asText());
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Tests\Bridge\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Chat\Bridge\Doctrine\DoctrineDbalMessageStore;
use Symfony\AI\Chat\Exception\InvalidArgumentException;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;

final class DoctrineDbalMessageStoreTest extends TestCase
{
    public function testMessageStoreTableCannotBeSetupWithExtraOptions()
    {
        $connection = $this->createMock(Connection::class);

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
        $schema = $this->createMock(Schema::class);
        $schema->expects($this->once())->method('hasTable')->willReturn(true);

        $sqliteSchemaManager = $this->createMock(AbstractSchemaManager::class);
        $sqliteSchemaManager->expects($this->once())->method('introspectSchema')->willReturn($schema);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createSchemaManager')->willReturn($sqliteSchemaManager);

        $messageStore = new DoctrineDbalMessageStore('foo', $connection);
        $messageStore->setup();
    }

    public function testMessageStoreTableCanBeSetup()
    {
        $platform = $this->createMock(AbstractPlatform::class);

        $column = $this->createMock(Column::class);
        $column->expects($this->once())->method('setAutoincrement')->willReturnSelf();

        $table = $this->createMock(Table::class);
        $table->expects($this->once())->method('addOption')
            ->with('_symfony_ai_chat_table_name', 'foo')
            ->willReturnSelf();
        $table->expects($this->exactly(2))->method('addColumn')->willReturn($column);

        $schema = $this->createMock(Schema::class);
        $schema->expects($this->once())->method('hasTable')->willReturn(false);
        $schema->expects($this->once())->method('createTable')->with('foo')->willReturn($table);
        $schema->expects($this->once())->method('toSql')->with($platform)->willReturn([]);

        $sqliteSchemaManager = $this->createMock(AbstractSchemaManager::class);
        $sqliteSchemaManager->expects($this->once())->method('introspectSchema')->willReturn($schema);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createSchemaManager')->willReturn($sqliteSchemaManager);
        $connection->expects($this->exactly(2))->method('getDatabasePlatform')->willReturn($platform);

        $messageStore = new DoctrineDbalMessageStore('foo', $connection);
        $messageStore->setup();
    }

    public function testMessageStoreTableCannotBeDroppedIfTableDoesNotExist()
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->never())->method('delete');

        $schema = $this->createMock(Schema::class);
        $schema->expects($this->once())->method('hasTable')->willReturn(false);

        $sqliteSchemaManager = $this->createMock(AbstractSchemaManager::class);
        $sqliteSchemaManager->expects($this->once())->method('introspectSchema')->willReturn($schema);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createSchemaManager')->willReturn($sqliteSchemaManager);
        $connection->expects($this->never())->method('createQueryBuilder');
        $connection->expects($this->never())->method('transactional');

        $messageStore = new DoctrineDbalMessageStore('foo', $connection);
        $messageStore->drop();
    }

    public function testMessageStoreTableCanBeDropped()
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('delete')->with('foo');

        $schema = $this->createMock(Schema::class);
        $schema->expects($this->once())->method('hasTable')->willReturn(true);

        $sqliteSchemaManager = $this->createMock(AbstractSchemaManager::class);
        $sqliteSchemaManager->expects($this->once())->method('introspectSchema')->willReturn($schema);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createSchemaManager')->willReturn($sqliteSchemaManager);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects($this->once())->method('transactional');

        $messageStore = new DoctrineDbalMessageStore('foo', $connection);
        $messageStore->drop();
    }

    public function testMessageBagCanBeSaved()
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('insert')->with('foo')->willReturnSelf();
        $queryBuilder->expects($this->once())->method('values')->with([
            'messages' => '?',
        ])->willReturnSelf();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects($this->once())->method('transactional');

        $messageStore = new DoctrineDbalMessageStore('foo', $connection);
        $messageStore->save(new MessageBag(
            Message::ofUser('Hello world'),
        ));
    }

    public function testMessageBagCanBeLoaded()
    {
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageNormalizer(),
        ], [new JsonEncoder()]);

        $messageBag = new MessageBag(
            Message::ofUser('Hello world'),
        );

        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchAllAssociative')->willReturn([
            [
                'messages' => $serializer->serialize($messageBag->getMessages(), 'json'),
            ],
        ]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')->with('messages')->willReturnSelf();
        $queryBuilder->expects($this->once())->method('from')->with('foo')->willReturnSelf();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects($this->once())->method('transactional')->willReturn($result);

        $messageStore = new DoctrineDbalMessageStore('foo', $connection, $serializer);

        $messages = $messageStore->load();

        $this->assertCount(1, $messages);
    }
}

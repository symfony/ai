<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Capability;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Capability\MessengerFailedTool;
use Symfony\AI\Mate\Bridge\Symfony\Exception\ContainerNotDumpedException;
use Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Messenger\FailingHandler;
use Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Messenger\ImportPrice;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class MessengerFailedToolTest extends TestCase
{
    private const FAILED = ['name' => 'failed', 'dsn' => 'doctrine://default?queue_name=failed', 'failure' => true];
    private const ASYNC = ['name' => 'async', 'dsn' => '%env(MESSENGER_TRANSPORT_DSN)%', 'failure' => false];

    private string $projectDir;

    /**
     * @var list<string>
     */
    private array $envKeysTouched = [];

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/mate_messenger_failed_'.uniqid();
        mkdir($this->projectDir.'/var/cache/dev', 0777, true);
        file_put_contents($this->projectDir.'/.env', "DATABASE_URL=\"sqlite:///%kernel.project_dir%/var/data.db\"\nMESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0\n");
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->projectDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->projectDir);
        foreach ($this->envKeysTouched as $key) {
            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);
        }
    }

    public function testGroupsByExceptionClassMessagePatternAndFailingFrame()
    {
        $this->container();
        $rows = [];
        foreach (['order-1', 'order-2', 'order-3'] as $order) {
            $rows[] = $this->php(FailingHandler::writeFile('/mnt/inbox/'.$order.'.json'));
        }
        $rows[] = $this->php(FailingHandler::parsePrice('24,56'));
        $rows[] = $this->php(FailingHandler::parsePrice('1.984,25'));
        $rows[] = $this->php(FailingHandler::parsePriceElsewhere('7,10'));
        $rows[] = $this->php(FailingHandler::fail('Connection refused'));
        $this->database($rows);

        $transport = $this->call()['transports'][0];

        $this->assertSame('failed', $transport['transport']);
        $this->assertSame('messenger_messages (queue_name=failed)', $transport['storage']);
        $this->assertSame(7, $transport['message_count']);
        $this->assertSame(4, $transport['group_count']);
        $groups = $transport['groups'];
        $this->assertSame([3, 2, 1, 1], array_column($groups, 'count'));
        $this->assertSame([1, 2, 3, 4], array_column($groups, 'group'));

        $this->assertSame(\RuntimeException::class, $groups[0]['exception_class']);
        $this->assertSame([ImportPrice::class], $groups[0]['message_classes']);
        $this->assertSame(['Could not write "/mnt/inbox/order-1.json": No such file or directory', 'Could not write "/mnt/inbox/order-2.json": No such file or directory', 'Could not write "/mnt/inbox/order-3.json": No such file or directory'], $groups[0]['sample_messages']);
        $this->assertSame(['1', '2', '3'], $groups[0]['ids']);
        $this->assertSame(['async'], $groups[0]['original_transports']);

        // Same class and pattern, thrown from a different frame: a separate group.
        $this->assertSame(\InvalidArgumentException::class, $groups[1]['exception_class']);
        $this->assertSame(\InvalidArgumentException::class, $groups[2]['exception_class']);
        $this->assertNotSame($groups[1]['failed_in'], $groups[2]['failed_in']);
        $this->assertSame(['"24,56" is not a decimal amount.', '"1.984,25" is not a decimal amount.'], $groups[1]['sample_messages']);
        $this->assertStringContainsString('FailingHandler.php:', $groups[1]['failed_in']);
        $this->assertStringStartsWith($groups[1]['failed_in'], $groups[1]['trace'][0]);

        // Same class and frame, different message: a separate group.
        $this->assertSame(\RuntimeException::class, $groups[3]['exception_class']);
        $this->assertSame(['Connection refused'], $groups[3]['sample_messages']);
    }

    public function testReportsRetryCountsAndFirstAndLastFailureTimes()
    {
        $this->container();
        $this->database([
            $this->php(FailingHandler::fail('Timeout'), retries: 3, firstRetry: '2026-09-25 10:00:00', storedAt: '2026-09-25 10:05:00'),
            $this->php(FailingHandler::fail('Timeout'), retries: 1, firstRetry: '2026-09-25 11:00:00', storedAt: '2026-09-25 12:30:00'),
            $this->php(FailingHandler::fail('Timeout'), retries: 0, storedAt: '2026-09-25 09:00:00'),
        ]);

        $group = $this->call()['transports'][0]['groups'][0];

        $this->assertSame(['min' => 0, 'max' => 3], $group['retry_count']);
        $this->assertSame('2026-09-25T09:00:00+00:00', $group['first_failed_at']);
        $this->assertSame('2026-09-25T12:30:00+00:00', $group['last_failed_at']);

        $messages = $this->call(group: 1)['transports'][0]['groups'][0]['messages'];
        $this->assertSame([3, 1, 0], array_column($messages, 'retry_count'));
        $this->assertSame('2026-09-25T10:00:00+00:00', $messages[0]['first_failed_at']);
        $this->assertSame('2026-09-25T10:05:00+00:00', $messages[0]['last_failed_at']);
    }

    public function testASingleRetryCountIsAScalar()
    {
        $this->container();
        $this->database([$this->php(FailingHandler::fail('Timeout'), retries: 2), $this->php(FailingHandler::fail('Timeout'), retries: 2)]);

        $this->assertSame(2, $this->call()['transports'][0]['groups'][0]['retry_count']);
    }

    public function testTrimsSamplesIdsAndLongMessages()
    {
        $this->container();
        $rows = [];
        for ($i = 1; $i <= 25; ++$i) {
            $rows[] = $this->php(FailingHandler::fail(\sprintf('Order %d failed: %s', $i, str_repeat('x', 400))));
        }
        $this->database($rows);

        $group = $this->call()['transports'][0]['groups'][0];

        $this->assertSame(25, $group['count']);
        $this->assertCount(5, $group['sample_messages']);
        $this->assertSame(300, mb_strwidth($group['sample_messages'][0]));
        $this->assertStringEndsWith('…', $group['sample_messages'][0]);
        $this->assertCount(20, $group['ids']);
        $this->assertTrue($group['ids_truncated']);
        $this->assertLessThanOrEqual(6, \count($group['trace']));

        $all = $this->call(group: 1)['transports'][0]['groups'][0];
        $this->assertCount(25, $all['ids']);
        $this->assertFalse($all['ids_truncated']);
        $this->assertCount(25, $all['messages']);
    }

    public function testEmptyTransport()
    {
        $this->container();
        $this->database([]);

        $transport = $this->call()['transports'][0];

        $this->assertSame(0, $transport['message_count']);
        $this->assertSame(0, $transport['group_count']);
        $this->assertSame([], $transport['groups']);
        $this->assertSame(0, $transport['undecodable']['count']);
    }

    public function testMissingTableIsReportedAndNotCreated()
    {
        $this->container();
        $pdo = new \PDO('sqlite:'.$this->projectDir.'/var/data.db');
        $pdo->exec('CREATE TABLE other (id INTEGER)');
        unset($pdo);

        $transport = $this->call()['transports'][0];

        $this->assertSame(0, $transport['message_count']);
        $this->assertStringContainsString('table does not exist yet', $transport['storage']);
        $this->assertSame(['other'], (new \PDO('sqlite:'.$this->projectDir.'/var/data.db'))->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function testMissingDatabaseFileIsAnErrorAndNotCreated()
    {
        $this->container();

        $transport = $this->call()['transports'][0];

        $this->assertStringContainsString('var/data.db" of the Doctrine connection "default" does not exist', $transport['error']);
        $this->assertFileDoesNotExist($this->projectDir.'/var/data.db');
    }

    public function testNeverChangesTheStorage()
    {
        $this->container();
        $this->database([$this->php(FailingHandler::fail('Timeout')), $this->php(FailingHandler::fail('Timeout'))]);
        $before = md5_file($this->projectDir.'/var/data.db');

        $this->call();
        $this->call(group: 1);

        $this->assertSame($before, md5_file($this->projectDir.'/var/data.db'));
    }

    public function testReadsMessagesWrittenByTheSymfonySerializer()
    {
        $this->container();
        $this->database([
            $this->json(FailingHandler::parsePrice('24,56')),
            $this->php(FailingHandler::parsePrice('3,10')),
        ]);

        $transport = $this->call()['transports'][0];

        $this->assertSame(0, $transport['undecodable']['count']);
        $this->assertSame(1, $transport['group_count']);
        $this->assertSame(['"24,56" is not a decimal amount.', '"3,10" is not a decimal amount.'], $transport['groups'][0]['sample_messages']);
        $this->assertSame(3, $transport['groups'][0]['retry_count']);
    }

    public function testCountsMalformedRowsAndKeepsTheRest()
    {
        $this->container();
        $valid = $this->php(FailingHandler::fail('Timeout'));
        $this->database([
            $valid,
            ['body' => 'garbage', 'headers' => '[]'],
            ['body' => substr($valid['body'], 0, 200).'}', 'headers' => '[]'],
            ['body' => '{"id": 1}', 'headers' => 'not json'],
        ]);

        $transport = $this->call()['transports'][0];

        $this->assertSame(4, $transport['message_count']);
        $this->assertSame(1, $transport['groups'][0]['count']);
        $this->assertSame(3, $transport['undecodable']['count']);
        $this->assertSame(['2', '3', '4'], array_column($transport['undecodable']['messages'], 'id'));
        $this->assertStringContainsString('corrupt', $transport['undecodable']['messages'][1]['reason']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsupportedProvider(): iterable
    {
        yield 'redis' => ['redis://localhost:6379/messages', 'Redis transports are not read'];
        yield 'amqp' => ['amqp://guest:guest@localhost:5672/%2f/failed', 'cannot be read without receiving its messages'];
        yield 'sqs' => ['sqs://localhost/failed', 'cannot be read without receiving its messages'];
        yield 'in-memory' => ['in-memory://', 'keeps its messages in the memory'];
        yield 'beanstalkd' => ['beanstalkd://localhost', 'Beanstalkd transports are not read'];
        yield 'custom' => ['acme://queue', 'Unknown transport kind "acme"'];
    }

    #[DataProvider('unsupportedProvider')]
    public function testUnsupportedTransportPointsAtMessengerFailedShow(string $dsn, string $reason)
    {
        $this->setEnv('FAILED_DSN', $dsn);
        $this->container([['name' => 'failed', 'dsn' => '%env(FAILED_DSN)%', 'failure' => true]]);

        $transport = $this->call()['transports'][0];

        $this->assertStringContainsString($reason, $transport['error']);
        $this->assertStringContainsString('bin/console messenger:failed:show --transport=failed', $transport['error']);
        $this->assertArrayNotHasKey('groups', $transport);
    }

    public function testReadsEveryFailureTransportByDefault()
    {
        $this->container([self::ASYNC, self::FAILED, ['name' => 'failed_high', 'dsn' => 'doctrine://default', 'failure' => true, 'options' => ['queue_name' => 'failed_high']]]);
        $this->database([
            $this->php(FailingHandler::fail('Timeout')),
            $this->php(FailingHandler::fail('Refused'), queue: 'failed_high'),
            $this->php(FailingHandler::fail('Refused'), queue: 'failed_high'),
        ]);

        $transports = $this->call()['transports'];

        $this->assertSame(['failed', 'failed_high'], array_column($transports, 'transport'));
        $this->assertSame([1, 2], array_column($transports, 'message_count'));
        $this->assertSame('messenger_messages (queue_name=failed_high)', $transports[1]['storage']);

        $this->assertSame(['failed_high'], array_column($this->call('failed_high')['transports'], 'transport'));
        $this->assertSame(0, $this->call('async')['transports'][0]['message_count']);
    }

    public function testGroupNeedsATransportWhenThereAreSeveral()
    {
        $this->container([self::FAILED, ['name' => 'failed_high', 'dsn' => 'doctrine://default?queue_name=failed_high', 'failure' => true]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('pass "transport" together with "group"');

        $this->call(group: 1);
    }

    public function testUnknownGroup()
    {
        $this->container();
        $this->database([$this->php(FailingHandler::fail('Timeout'))]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transport "failed" has no group 2.');

        $this->call(group: 2);
    }

    public function testUnknownTransportListsTheConfiguredOnes()
    {
        $this->container([self::ASYNC, self::FAILED]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transport "nope" not found. Configured transports: "async", "failed".');

        $this->call('nope');
    }

    public function testNoFailureTransport()
    {
        $this->container([self::ASYNC]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No failure transport is configured');

        $this->call();
    }

    public function testNoCompiledContainer()
    {
        $this->expectException(ContainerNotDumpedException::class);
        $this->expectExceptionMessage('messenger:failed:show');

        $this->call();
    }

    public function testUnresolvableEnvironmentVariableIsAnError()
    {
        $this->container([['name' => 'failed', 'dsn' => '%env(MISSING_FAILED_DSN)%', 'failure' => true]]);

        $this->assertStringContainsString('Environment variable not found: "MISSING_FAILED_DSN"', $this->call()['transports'][0]['error']);
    }

    public function testRealEnvironmentWinsOverDotenvFiles()
    {
        mkdir($this->projectDir.'/other');
        $this->setEnv('DATABASE_URL', 'sqlite:///%kernel.project_dir%/other/data.db');
        $this->container();
        $this->database([$this->php(FailingHandler::fail('Timeout'))], 'other/data.db');

        $this->assertSame(1, $this->call()['transports'][0]['message_count']);
    }

    public function testLaterDotenvFilesWin()
    {
        mkdir($this->projectDir.'/local');
        file_put_contents($this->projectDir.'/.env.dev.local', "DATABASE_URL=\"sqlite:///%kernel.project_dir%/local/data.db\"\n");
        $this->container();
        $this->database([$this->php(FailingHandler::fail('Timeout'))], 'local/data.db');

        $this->assertSame(1, $this->call()['transports'][0]['message_count']);
    }

    public function testResolvesTheConnectionFromDiscreteParameters()
    {
        $this->container(connection: ['driver' => 'pdo_sqlite', 'path' => '%kernel.project_dir%/var/data.db']);
        $this->database([$this->php(FailingHandler::fail('Timeout'))]);

        $this->assertSame(1, $this->call()['transports'][0]['message_count']);
    }

    public function testUnknownDoctrineConnection()
    {
        $this->container([['name' => 'failed', 'dsn' => 'doctrine://reporting', 'failure' => true]]);

        $this->assertStringContainsString('Doctrine connection "reporting", which is not in the container', $this->call()['transports'][0]['error']);
    }

    public function testPayloadIsWrappedAsUntrustedData()
    {
        $this->container();
        $this->database([]);

        $decoded = ResponseEncoder::decode($this->tool()->failed());

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('_security_notice', $decoded);
        $this->assertArrayHasKey('untrusted_data', $decoded);
    }

    /**
     * @return array<string, mixed>
     */
    private function call(?string $transport = null, ?int $group = null): array
    {
        $decoded = ResponseEncoder::decode($this->tool()->failed($transport, $group));
        $this->assertIsArray($decoded);
        $this->assertIsArray($decoded['untrusted_data']);

        return $decoded['untrusted_data'];
    }

    private function tool(): MessengerFailedTool
    {
        return new MessengerFailedTool($this->projectDir, $this->projectDir.'/var/cache');
    }

    /**
     * A container as a debug kernel dumps it, compiled in another directory.
     *
     * @param list<array{name: string, dsn: string, failure: bool, options?: array<string, string>}> $transports
     * @param array<string, string>                                                                  $connection
     */
    private function container(array $transports = [self::ASYNC, self::FAILED], array $connection = ['url' => '%env(resolve:DATABASE_URL)%', 'driver' => 'pdo_mysql', 'host' => 'localhost']): void
    {
        $services = '';
        foreach ($transports as $t) {
            $options = '';
            foreach (['transport_name' => $t['name']] + ($t['options'] ?? []) as $key => $value) {
                $options .= \sprintf('<argument key="%s">%s</argument>', $key, htmlspecialchars($value));
            }
            $services .= \sprintf(
                '<service id="messenger.transport.%1$s" class="Symfony\Component\Messenger\Transport\TransportInterface"><tag name="messenger.receiver" alias="%1$s" is_failure_transport="%2$s"/><argument>%3$s</argument><argument type="collection">%4$s</argument><argument type="service" id="messenger.default_serializer"/></service>',
                $t['name'], $t['failure'] ? 'true' : 'false', htmlspecialchars($t['dsn']), $options,
            );
        }
        $params = '';
        foreach ($connection + ['port' => 'null'] as $key => $value) {
            $params .= \sprintf('<argument key="%s">%s</argument>', $key, htmlspecialchars($value));
        }
        $services .= \sprintf('<service id="doctrine.dbal.default_connection" class="Doctrine\DBAL\Connection" public="true"><argument type="collection">%s<argument key="driverOptions" type="collection"/></argument></service>', $params);

        file_put_contents($this->projectDir.'/var/cache/dev/App_KernelDevDebugContainer.xml', <<<XML
            <?xml version="1.0" encoding="utf-8"?>
            <container xmlns="http://symfony.com/schema/dic/services">
              <parameters>
                <parameter key="kernel.project_dir">/srv/compiled-elsewhere</parameter>
                <parameter key="kernel.environment">dev</parameter>
              </parameters>
              <services>{$services}</services>
            </container>
            XML);
    }

    /**
     * @param list<array{body: string, headers: string, queue?: string, created_at?: string}> $rows
     */
    private function database(array $rows, string $path = 'var/data.db'): void
    {
        $pdo = new \PDO('sqlite:'.$this->projectDir.'/'.$path);
        $pdo->exec('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $insert = $pdo->prepare('INSERT INTO messenger_messages (body, headers, queue_name, created_at, available_at) VALUES (?, ?, ?, ?, ?)');
        foreach ($rows as $row) {
            $createdAt = $row['created_at'] ?? '2026-09-25 22:13:01';
            $insert->execute([$row['body'], $row['headers'], $row['queue'] ?? 'failed', $createdAt, $createdAt]);
        }
    }

    /**
     * @return array{body: string, headers: string, queue: string, created_at: string}
     */
    private function php(\Throwable $exception, int $retries = 3, string $firstRetry = '2026-09-25 22:12:30', string $storedAt = '2026-09-25 22:13:01', string $queue = 'failed'): array
    {
        $encoded = (new PhpSerializer())->encode($this->envelope($exception, $retries, $firstRetry));

        return ['body' => $encoded['body'], 'headers' => json_encode($encoded['headers'] ?? [], \JSON_THROW_ON_ERROR), 'queue' => $queue, 'created_at' => $storedAt];
    }

    /**
     * @return array{body: string, headers: string}
     */
    private function json(\Throwable $exception): array
    {
        $encoded = Serializer::create()->encode($this->envelope($exception, 3, '2026-09-25 22:12:30'));

        return ['body' => $encoded['body'], 'headers' => json_encode($encoded['headers'], \JSON_THROW_ON_ERROR)];
    }

    private function envelope(\Throwable $exception, int $retries, string $firstRetry): Envelope
    {
        $stamps = [];
        for ($i = 1; $i <= $retries; ++$i) {
            $stamps[] = new RedeliveryStamp($i, (new \DateTimeImmutable($firstRetry, new \DateTimeZone('UTC')))->modify(\sprintf('+%d seconds', 10 * ($i - 1))));
        }
        $stamps[] = ErrorDetailsStamp::create($exception);
        $stamps[] = new SentToFailureTransportStamp('async');

        return new Envelope(new ImportPrice('24,56'), $stamps);
    }

    private function setEnv(string $key, string $value): void
    {
        $_SERVER[$key] = $value;
        $this->envKeysTouched[] = $key;
    }
}

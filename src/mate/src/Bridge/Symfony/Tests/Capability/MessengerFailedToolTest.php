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
use Symfony\AI\Mate\Bridge\Symfony\Messenger\DoctrineTransportReader;
use Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Messenger\FailingHandler;
use Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Messenger\ImportPrice;
use Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Messenger\PrioritizedImport;
use Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Messenger\Priority;
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
        $this->assertCount(4, array_unique(array_column($groups, 'group_id')));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $groups[0]['group_id']);

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

        $messages = $this->call(group: $this->groupId())['transports'][0]['groups'][0]['messages'];
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
            $rows[] = $this->php(FailingHandler::fail(\sprintf('Order %d failed: %s', 1000 + $i, str_repeat('x', 400))));
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

        $all = $this->call(group: $this->groupId())['transports'][0]['groups'][0];
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
        $this->call(group: $this->groupId());

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

        $this->call(group: 'abcd1234');
    }

    public function testUnknownGroup()
    {
        $this->container();
        $this->database([$this->php(FailingHandler::fail('Timeout'))]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transport "failed" has no group "abcd1234".');

        $this->call(group: 'abcd1234');
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

    public function testFindsFailureTransportsDumpedAsOneAndEmptyString()
    {
        // symfony/dependency-injection 7.3.0 dumps bool tag attributes as "1" and "".
        $this->container(bool: ['1', '']);
        $this->database([$this->php(FailingHandler::fail('Timeout'))]);

        $this->assertSame(['failed'], array_column($this->call()['transports'], 'transport'));
    }

    public function testAppEnvFromDotenvLocalPicksThatContainerAndItsEnvFile()
    {
        mkdir($this->projectDir.'/prod');
        file_put_contents($this->projectDir.'/.env.local', "APP_ENV=prod\n");
        file_put_contents($this->projectDir.'/.env.prod', "DATABASE_URL=\"sqlite:///%kernel.project_dir%/prod/data.db\"\n");
        $this->container([['name' => 'failed', 'dsn' => 'doctrine://default?queue_name=failed', 'failure' => true]], env: 'prod');
        $this->container([['name' => 'dev_only', 'dsn' => 'doctrine://default', 'failure' => true]]);
        $this->database([$this->php(FailingHandler::fail('Timeout')), $this->php(FailingHandler::fail('Timeout'))], 'prod/data.db');

        $transport = $this->call()['transports'][0];

        $this->assertSame('failed', $transport['transport']);
        $this->assertSame(2, $transport['message_count']);
    }

    public function testReadsDotenvLocalPhp()
    {
        unlink($this->projectDir.'/.env');
        mkdir($this->projectDir.'/dumped');
        file_put_contents($this->projectDir.'/.env.local.php', "<?php return ['APP_ENV' => 'dev', 'DATABASE_URL' => 'sqlite:///%kernel.project_dir%/dumped/data.db'];\n");
        $this->container();
        $this->database([$this->php(FailingHandler::fail('Timeout'))], 'dumped/data.db');

        $this->assertSame(1, $this->call()['transports'][0]['message_count']);
    }

    public function testResolvesVariableReferencesAcrossDotenvFiles()
    {
        mkdir($this->projectDir.'/shared');
        file_put_contents($this->projectDir.'/.env', "DB_DIR=shared\n", \FILE_APPEND);
        file_put_contents($this->projectDir.'/.env.local', "DATABASE_URL=\"sqlite:///%kernel.project_dir%/\${DB_DIR}/data.db\"\n");
        $this->container();
        $this->database([$this->php(FailingHandler::fail('Timeout'))], 'shared/data.db');

        $this->assertSame(1, $this->call()['transports'][0]['message_count']);
    }

    public function testDotenvSyntaxErrorIsReported()
    {
        file_put_contents($this->projectDir.'/.env.local', "DATABASE_URL=\"unterminated\n");
        $this->container();

        $error = $this->call()['transports'][0]['error'];

        $this->assertStringContainsString('The .env files could not be loaded', $error);
        $this->assertStringContainsString('.env.local', $error);
    }

    public function testDoesNotLeakDotenvValuesIntoTheProcess()
    {
        file_put_contents($this->projectDir.'/.env', "MATE_TEST_ONLY_VAR=1\n", \FILE_APPEND);
        $this->container();
        $this->database([]);

        $this->call();

        $this->assertArrayNotHasKey('MATE_TEST_ONLY_VAR', $_SERVER);
        $this->assertArrayNotHasKey('MATE_TEST_ONLY_VAR', $_ENV);
        $this->assertFalse(getenv('MATE_TEST_ONLY_VAR'));
    }

    public function testReadsASchemaQualifiedTable()
    {
        $this->container([['name' => 'failed', 'dsn' => 'doctrine://default?queue_name=failed&table_name=main.messenger_messages', 'failure' => true]]);
        $this->database([$this->php(FailingHandler::fail('Timeout'))]);

        $transport = $this->call()['transports'][0];

        $this->assertSame('main.messenger_messages (queue_name=failed)', $transport['storage']);
        $this->assertSame(1, $transport['message_count']);
    }

    public function testSkipsMessagesAboveTheRowLimitUnread()
    {
        $this->container();
        $small = $this->php(FailingHandler::fail('Timeout'));
        $this->database([$small, $this->php(FailingHandler::fail(str_repeat('y', 50000)))]);
        $limit = \strlen($small['body']) + \strlen($small['headers']) + 2048;

        $transport = $this->call(reader: new DoctrineTransportReader(rowBytes: $limit))['transports'][0];

        $this->assertSame(1, $transport['scanned']);
        $this->assertSame(1, $transport['groups'][0]['count']);
        $this->assertSame('2', $transport['undecodable']['messages'][0]['id']);
        $this->assertStringContainsString(\sprintf('above the %d KB this tool reads per message', intdiv($limit, 1024)), $transport['undecodable']['messages'][0]['reason']);
    }

    public function testStopsAtTheByteBudget()
    {
        $this->container();
        $row = $this->php(FailingHandler::fail('Timeout'));
        $this->database([$row, $row, $row, $row]);
        $size = \strlen($row['body']) + \strlen($row['headers']);

        $transport = $this->call(reader: new DoctrineTransportReader(budgetBytes: 2 * $size + 1))['transports'][0];

        $this->assertSame(4, $transport['message_count']);
        $this->assertSame(2, $transport['scanned']);
        $this->assertTrue($transport['scan_truncated']);
        $this->assertStringContainsString('budget', $transport['scan_truncated_reason']);
        $this->assertSame(['3', '4'], $transport['groups'][0]['ids']);
    }

    public function testReadsThroughTheSqlite3Driver()
    {
        if (!class_exists(\SQLite3::class)) {
            $this->markTestSkipped('ext-sqlite3 is not installed.');
        }
        $this->container(connection: ['driver' => 'sqlite3', 'path' => '%kernel.project_dir%/var/data.db']);
        $this->database([$this->php(FailingHandler::fail('Timeout'))]);

        $this->assertSame(1, $this->call()['transports'][0]['message_count']);
    }

    public function testPassesDriverOptionsThrough()
    {
        // PDO::ATTR_TIMEOUT
        $this->container(driverOptions: ['2' => '%env(DB_TIMEOUT)%']);
        file_put_contents($this->projectDir.'/.env', "DB_TIMEOUT=5\n", \FILE_APPEND);
        $this->database([$this->php(FailingHandler::fail('Timeout'))]);

        $this->assertSame(1, $this->call()['transports'][0]['message_count']);
    }

    public function testParametersDerivedFromTheCompiledProjectDirPointIntoTheProject()
    {
        $this->container(connection: ['driver' => 'pdo_sqlite', 'path' => '%app.data_dir%/data.db'], parameters: ['app.data_dir' => '/srv/compiled-elsewhere/var']);
        $this->database([$this->php(FailingHandler::fail('Timeout'))]);

        $this->assertSame(1, $this->call()['transports'][0]['message_count']);
    }

    public function testSeveralKernelContextsNeedAContext()
    {
        $this->container();
        $this->database([$this->php(FailingHandler::fail('Timeout'))]);
        $tool = $this->tool(cacheDir: ['admin' => $this->projectDir.'/var/cache', 'website' => $this->projectDir.'/var/cache-website']);

        $decoded = ResponseEncoder::decode($tool->failed(context: 'admin'));
        $this->assertIsArray($decoded);
        $this->assertSame(1, $decoded['untrusted_data']['transports'][0]['message_count']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Several kernel contexts are configured; pass "context" (one of "admin", "website").');
        $tool->failed();
    }

    public function testReadsMessagesCarryingEnums()
    {
        $this->container();
        $encoded = (new PhpSerializer())->encode(new Envelope(new PrioritizedImport(Priority::High, [Priority::Low, Priority::High]), [ErrorDetailsStamp::create(FailingHandler::fail('Timeout'))]));
        $this->database([['body' => $encoded['body'], 'headers' => '[]']]);

        $transport = $this->call()['transports'][0];

        $this->assertSame(0, $transport['undecodable']['count']);
        $this->assertSame([PrioritizedImport::class], $transport['groups'][0]['message_classes']);
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

    public function testGroupIdsAreStableWhenMessagesArrive()
    {
        $this->container();
        $this->database([$this->php(FailingHandler::fail('Timeout')), $this->php(FailingHandler::fail('Connection refused'))]);
        $before = array_column($this->call()['transports'][0]['groups'], 'group_id');

        $pdo = new \PDO('sqlite:'.$this->projectDir.'/var/data.db');
        $row = $this->php(FailingHandler::fail('Connection refused'));
        $pdo->prepare('INSERT INTO messenger_messages (body, headers, queue_name, created_at, available_at) VALUES (?, ?, ?, ?, ?)')->execute([$row['body'], $row['headers'], 'failed', '2026-09-26 10:00:00', '2026-09-26 10:00:00']);
        $groups = $this->call()['transports'][0]['groups'];

        $this->assertSame(['Connection refused', 'Timeout'], array_map(static fn (array $g): string => $g['sample_messages'][0], $groups));
        $this->assertEqualsCanonicalizing($before, array_column($groups, 'group_id'));
        $selected = $this->call(group: $groups[1]['group_id'])['transports'][0]['groups'];
        $this->assertCount(1, $selected);
        $this->assertSame('Timeout', $selected[0]['sample_messages'][0]);
    }

    public function testListsAtMostFiftyGroupsAndCountsTheRest()
    {
        $this->container();
        $rows = [];
        for ($i = 0; $i < 55; ++$i) {
            $rows[] = $this->php(FailingHandler::fail('Reason '.\chr(97 + $i % 26).\chr(97 + intdiv($i, 26))));
        }
        $rows[] = $this->php(FailingHandler::fail('Reason aa'));
        $this->database($rows);

        $transport = $this->call()['transports'][0];

        $this->assertSame(55, $transport['group_count']);
        $this->assertCount(50, $transport['groups']);
        $this->assertTrue($transport['groups_truncated']);
        $this->assertSame(5, $transport['omitted_groups']);
        $this->assertSame(5, $transport['omitted_messages']);
        $this->assertSame(2, $transport['groups'][0]['count']);
    }

    public function testCapsTheMessagesOfOneGroup()
    {
        $this->container();
        $rows = [];
        for ($i = 0; $i < 105; ++$i) {
            $rows[] = $this->php(FailingHandler::fail('Timeout'));
        }
        $this->database($rows);

        $group = $this->call(group: $this->groupId())['transports'][0]['groups'][0];

        $this->assertSame(105, $group['count']);
        $this->assertCount(100, $group['messages']);
        $this->assertCount(100, $group['ids']);
        $this->assertTrue($group['messages_truncated']);
        $this->assertTrue($group['ids_truncated']);
    }

    public function testNoTruncationFlagsForASmallResult()
    {
        $this->container();
        $this->database([$this->php(FailingHandler::fail('Timeout'))]);

        $transport = $this->call()['transports'][0];

        $this->assertFalse($transport['groups_truncated']);
        $this->assertSame(0, $transport['omitted_groups']);
        $this->assertSame(0, $transport['omitted_messages']);
    }

    public function testMasksTokensInSampleMessages()
    {
        $this->container();
        $this->database([$this->php(FailingHandler::fail('Token eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.abc rejected for api_key=sk-live-1234'))]);

        $sample = $this->call()['transports'][0]['groups'][0]['sample_messages'][0];

        $this->assertStringNotContainsString('eyJhbGci', $sample);
        $this->assertStringNotContainsString('sk-live', $sample);
        $this->assertStringContainsString('rejected', $sample);
    }

    public function testAMalformedConnectionUrlIsAnErrorOfThatTransportOnly()
    {
        $this->container([self::FAILED, ['name' => 'failed_other', 'dsn' => 'doctrine://broken?queue_name=failed_other', 'failure' => true]]);
        $file = $this->projectDir.'/var/cache/dev/App_KernelDevDebugContainer.xml';
        file_put_contents($file, str_replace('</services>', '<service id="doctrine.dbal.broken_connection" class="Doctrine\DBAL\Connection"><argument type="collection"><argument key="url">mysql://localhost:99999999</argument></argument></service></services>', (string) file_get_contents($file)));
        $this->database([$this->php(FailingHandler::fail('Timeout'))]);

        $transports = $this->call()['transports'];

        $this->assertSame(1, $transports[0]['group_count']);
        $this->assertSame('failed_other', $transports[1]['transport']);
        $this->assertStringContainsString('not a valid DSN', $transports[1]['error']);
    }

    public function testReportsTheContainerItReadAndSkipsABrokenOne()
    {
        $this->container();
        file_put_contents($this->projectDir.'/var/cache/dev/App_KernelDevDebugContainer.xml', '<container');
        $this->container(env: 'test');
        $this->database([$this->php(FailingHandler::fail('Timeout'))]);

        $container = $this->call()['container'];

        $this->assertSame('var/cache/test/App_KernelTestDebugContainer.xml', $container['path']);
        $this->assertCount(1, $container['skipped']);
        $this->assertStringContainsString('App_KernelDevDebugContainer.xml', $container['skipped'][0]);
    }

    public function testNoUsableContainerNamesTheBrokenOnes()
    {
        $this->container();
        file_put_contents($this->projectDir.'/var/cache/dev/App_KernelDevDebugContainer.xml', '<container');

        $this->expectException(ContainerNotDumpedException::class);
        $this->expectExceptionMessage('Could not load: ');

        $this->call();
    }

    private function groupId(int $index = 0): string
    {
        return $this->call()['transports'][0]['groups'][$index]['group_id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function call(?string $transport = null, ?string $group = null, ?DoctrineTransportReader $reader = null): array
    {
        $decoded = ResponseEncoder::decode($this->tool($reader)->failed($transport, $group));
        $this->assertIsArray($decoded);
        $this->assertIsArray($decoded['untrusted_data']);

        return $decoded['untrusted_data'];
    }

    /**
     * @param string|array<string, string>|null $cacheDir
     */
    private function tool(?DoctrineTransportReader $reader = null, string|array|null $cacheDir = null): MessengerFailedTool
    {
        return new MessengerFailedTool($this->projectDir, $cacheDir ?? $this->projectDir.'/var/cache', doctrine: $reader ?? new DoctrineTransportReader());
    }

    /**
     * A container as a debug kernel dumps it, compiled in another directory.
     *
     * @param list<array{name: string, dsn: string, failure: bool, options?: array<string, string>}> $transports
     * @param array<string, string>                                                                  $connection
     * @param array{0: string, 1: string}                                                            $bool          how the dumper writes true and false tag attributes
     * @param array<string, string>                                                                  $parameters
     * @param array<int|string, string>                                                              $driverOptions
     */
    private function container(array $transports = [self::ASYNC, self::FAILED], array $connection = ['url' => '%env(resolve:DATABASE_URL)%', 'driver' => 'pdo_mysql', 'host' => 'localhost'], string $env = 'dev', array $bool = ['true', 'false'], array $parameters = [], array $driverOptions = []): void
    {
        $services = '';
        foreach ($transports as $t) {
            $options = '';
            foreach (['transport_name' => $t['name']] + ($t['options'] ?? []) as $key => $value) {
                $options .= \sprintf('<argument key="%s">%s</argument>', $key, htmlspecialchars($value));
            }
            $services .= \sprintf(
                '<service id="messenger.transport.%1$s" class="Symfony\Component\Messenger\Transport\TransportInterface"><tag name="messenger.receiver" alias="%1$s" is_failure_transport="%2$s"/><argument>%3$s</argument><argument type="collection">%4$s</argument><argument type="service" id="messenger.default_serializer"/></service>',
                $t['name'], $t['failure'] ? $bool[0] : $bool[1], htmlspecialchars($t['dsn']), $options,
            );
        }
        $params = '';
        foreach ($connection + ['port' => 'null'] as $key => $value) {
            $params .= \sprintf('<argument key="%s">%s</argument>', $key, htmlspecialchars($value));
        }
        $options = '';
        foreach ($driverOptions as $key => $value) {
            $options .= \sprintf('<argument key="%s">%s</argument>', $key, htmlspecialchars($value));
        }
        $services .= \sprintf('<service id="doctrine.dbal.default_connection" class="Doctrine\DBAL\Connection" public="true"><argument type="collection">%s<argument key="driverOptions" type="collection">%s</argument></argument></service>', $params, $options);
        $extra = '';
        foreach ($parameters as $key => $value) {
            $extra .= \sprintf('<parameter key="%s">%s</parameter>', $key, htmlspecialchars($value));
        }

        @mkdir($this->projectDir.'/var/cache/'.$env, 0777, true);
        file_put_contents(\sprintf('%s/var/cache/%s/App_Kernel%sDebugContainer.xml', $this->projectDir, $env, ucfirst($env)), <<<XML
            <?xml version="1.0" encoding="utf-8"?>
            <container xmlns="http://symfony.com/schema/dic/services">
              <parameters>
                <parameter key="kernel.project_dir">/srv/compiled-elsewhere</parameter>
                <parameter key="kernel.environment">{$env}</parameter>
                {$extra}
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

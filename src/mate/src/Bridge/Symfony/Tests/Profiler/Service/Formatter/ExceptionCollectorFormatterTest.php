<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Profiler\Service\Formatter;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\ExceptionCollectorFormatter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ExceptionCollectorFormatterTest extends TestCase
{
    private ExceptionCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ExceptionCollectorFormatter();
    }

    public function testGetName()
    {
        $this->assertSame('exception', $this->formatter->getName());
    }

    public function testFormatWithoutException()
    {
        $collector = new ExceptionDataCollector();
        $collector->collect(new Request(), new Response());

        $result = $this->formatter->format($collector);

        $this->assertFalse($result['has_exception']);
    }

    public function testFormatKeepsActionableFieldsAndScrubsSecretsFromMessage()
    {
        $message = 'Connection to mysql://app:s3cr3t-pw@db:3306 failed; token=ABC123DEF; Authorization: Bearer XYZ.789.abc';
        $collector = $this->collect(new \RuntimeException($message));

        $result = $this->formatter->format($collector);

        $this->assertTrue($result['has_exception']);
        $this->assertStringContainsString('RuntimeException', $result['class']);
        $this->assertArrayHasKey('file', $result);
        $this->assertArrayHasKey('line', $result);
        $this->assertIsArray($result['trace']);

        // High-confidence secret shapes are scrubbed.
        $this->assertStringContainsString('REDACTED', $result['message']);
        $serialized = json_encode($result);
        $this->assertStringNotContainsString('s3cr3t-pw', $serialized);
        $this->assertStringNotContainsString('ABC123DEF', $serialized);
        $this->assertStringNotContainsString('XYZ.789.abc', $serialized);

        // Non-secret context in the message is preserved.
        $this->assertStringContainsString('Connection to', $result['message']);
        $this->assertStringContainsString('failed', $result['message']);
    }

    public function testFormatScrubsPasswordOnlyDsnAndCompoundSecretKeys()
    {
        $message = 'redis://:r3disPw@localhost:6379 down; client_secret=cs-LEAK, secret_key=sk-LEAK, private_key=pk-LEAK';
        $collector = $this->collect(new \RuntimeException($message));

        $result = $this->formatter->format($collector);

        $serialized = json_encode($result);
        $this->assertStringNotContainsString('r3disPw', $serialized);
        $this->assertStringNotContainsString('cs-LEAK', $serialized);
        $this->assertStringNotContainsString('sk-LEAK', $serialized);
        $this->assertStringNotContainsString('pk-LEAK', $serialized);

        // Non-secret context survives.
        $this->assertStringContainsString('localhost', $result['message']);
        $this->assertStringContainsString('down', $result['message']);
    }

    public function testFormatScrubsBasicAuthJsonAndQuotedValues()
    {
        $message = 'HTTP 401 Authorization: Basic dXNlcjpwYXNzd29yZA== body {"password":"jsonPW","user":"alice"} cfg password=\'my secret pw\'';
        $collector = $this->collect(new \RuntimeException($message));

        $result = $this->formatter->format($collector);

        $serialized = json_encode($result);
        $this->assertStringNotContainsString('dXNlcjpwYXNzd29yZA', $serialized);
        $this->assertStringNotContainsString('jsonPW', $serialized);
        $this->assertStringNotContainsString('my secret pw', $serialized);

        // A non-secret JSON field next to the redacted one survives.
        $this->assertStringContainsString('alice', $result['message']);
    }

    public function testFormatDoesNotRedactClassNameScopeResolution()
    {
        $collector = $this->collect(new \RuntimeException('Access denied by Security\\TokenStorage::getToken()'));

        $result = $this->formatter->format($collector);

        // `TokenStorage::getToken` must not be mistaken for a `token:` key=value.
        $this->assertStringContainsString('TokenStorage::getToken', $result['message']);
    }

    public function testFormatLeavesPlainMessageUntouched()
    {
        $collector = $this->collect(new \RuntimeException('Call to undefined method App\\Service::handle()'));

        $result = $this->formatter->format($collector);

        $this->assertSame('Call to undefined method App\\Service::handle()', $result['message']);
    }

    public function testGetSummaryScrubsSecretsFromMessage()
    {
        $collector = $this->collect(new \RuntimeException('auth failed: password=hunter2'));

        $result = $this->formatter->getSummary($collector);

        $this->assertTrue($result['has_exception']);
        $this->assertStringNotContainsString('hunter2', json_encode($result));
        $this->assertStringContainsString('REDACTED', $result['message']);
    }

    public function testGetSummaryWithoutException()
    {
        $collector = new ExceptionDataCollector();
        $collector->collect(new Request(), new Response());

        $result = $this->formatter->getSummary($collector);

        $this->assertFalse($result['has_exception']);
    }

    private function collect(\Throwable $exception): ExceptionDataCollector
    {
        $collector = new ExceptionDataCollector();
        $collector->collect(new Request(), new Response(), $exception);

        return $collector;
    }
}

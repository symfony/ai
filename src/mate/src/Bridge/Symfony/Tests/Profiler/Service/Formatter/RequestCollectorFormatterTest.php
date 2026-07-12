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
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\RequestCollectorFormatter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RequestCollectorFormatterTest extends TestCase
{
    private RequestCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new RequestCollectorFormatter();
    }

    public function testGetName()
    {
        $this->assertSame('request', $this->formatter->getName());
    }

    public function testFormatRedactsSensitivePostParameters()
    {
        $data = $this->format($this->collect(Request::create('/login', 'POST', [
            'username' => 'alice',
            'password' => 'hunter2',
            'pwd' => 'hunter2',
            '_csrf_token' => 'csrf-value',
            'api_key' => 'sk-12345',
            'remember' => '1',
        ])));

        $this->assertSame('***REDACTED***', $data['request_request']['password']);
        $this->assertSame('***REDACTED***', $data['request_request']['pwd']);
        $this->assertSame('***REDACTED***', $data['request_request']['_csrf_token']);
        $this->assertSame('***REDACTED***', $data['request_request']['api_key']);

        // Non-sensitive fields stay visible so the AI can still reason over them.
        $this->assertSame('alice', $data['request_request']['username']);
        $this->assertSame('1', $data['request_request']['remember']);
    }

    public function testFormatRedactsSensitiveQueryParameters()
    {
        $data = $this->format($this->collect(Request::create('/search?q=cats&api_key=topsecret&access_token=abc&page=2')));

        $this->assertSame('***REDACTED***', $data['request_query']['api_key']);
        $this->assertSame('***REDACTED***', $data['request_query']['access_token']);
        $this->assertSame('cats', $data['request_query']['q']);
        $this->assertSame('2', $data['request_query']['page']);
    }

    public function testFormatRedactsNestedSensitiveParameters()
    {
        $data = $this->format($this->collect(Request::create('/api', 'POST', [
            'user' => [
                'name' => 'bob',
                'password' => 'nested-secret',
            ],
        ])));

        $this->assertSame('bob', $data['request_request']['user']['name']);
        $this->assertSame('***REDACTED***', $data['request_request']['user']['password']);
    }

    public function testFormatOmitsRawRequestBody()
    {
        $request = Request::create('/api/login', 'POST', [], [], [], [], '{"username":"alice","password":"hunter2"}');
        $request->headers->set('Content-Type', 'application/json');

        $data = $this->format($this->collect($request));

        $this->assertIsString($data['content']);
        $this->assertStringContainsString('REDACTED', $data['content']);
        // The secret must not survive anywhere in the formatted output (e.g. via
        // the reconstructed curl command, which re-embeds the raw body).
        $this->assertStringNotContainsString('hunter2', json_encode($data));
    }

    public function testFormatDoesNotLeakSecretsViaReconstructedCurlCommand()
    {
        $request = Request::create('/login?api_key=topsecret', 'POST', [], [], [], [], '{"password":"hunter2"}');
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Authorization', 'Bearer leak-me');

        $data = $this->format($this->collect($request));

        // The reconstructed curl command re-embeds the raw body, query string and
        // auth header. Whether Symfony's RequestDataCollector emits it depends on
        // the installed version, so assert its redaction only when it is present.
        if (isset($data['curlCommand'])) {
            $this->assertIsString($data['curlCommand']);
            $this->assertStringContainsString('REDACTED', $data['curlCommand']);
        }

        // No secret from the body, query string or auth header may survive in the
        // full serialized output through any field, curlCommand included.
        $serialized = json_encode($data);
        $this->assertStringNotContainsString('hunter2', $serialized);
        $this->assertStringNotContainsString('topsecret', $serialized);
        $this->assertStringNotContainsString('leak-me', $serialized);
    }

    public function testFormatRedactsRawQueryStringInServerBag()
    {
        $data = $this->format($this->collect(Request::create('/page?api_key=topsecret&p=2')));

        // REQUEST_URI / QUERY_STRING embed the raw query string under
        // non-sensitive key names, so they are redacted wholesale.
        $this->assertSame('***REDACTED***', $data['request_server']['REQUEST_URI']);
        $this->assertSame('***REDACTED***', $data['request_server']['QUERY_STRING']);
        $this->assertStringNotContainsString('topsecret', json_encode($data));
    }

    public function testFormatRedactsCloudAccessKeyParameters()
    {
        $data = $this->format($this->collect(Request::create('/api', 'POST', [
            'aws_access_key_id' => 'AKIA-example',
            'signing_key' => 'sign-me',
            'encryption_key' => 'enc-me',
            'oauth_verifier' => 'verify-me',
            'limit' => '20',
        ])));

        $this->assertSame('***REDACTED***', $data['request_request']['aws_access_key_id']);
        $this->assertSame('***REDACTED***', $data['request_request']['signing_key']);
        $this->assertSame('***REDACTED***', $data['request_request']['encryption_key']);
        $this->assertSame('***REDACTED***', $data['request_request']['oauth_verifier']);
        $this->assertSame('20', $data['request_request']['limit']);
    }

    public function testFormatRedactsSensitiveHeadersCookiesAndEnvironment()
    {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer super-secret-token');
        $request->cookies->set('PHPSESSID', 'session-value');
        $request->server->set('APP_SECRET', 'the-app-secret');
        $request->server->set('DATABASE_PASSWORD', 'db-pass');

        $data = $this->format($this->collect($request));

        $this->assertSame('***REDACTED***', $data['request_headers']['authorization']);
        $this->assertSame('***REDACTED***', $data['request_cookies']['PHPSESSID']);
        $this->assertSame('***REDACTED***', $data['request_server']['APP_SECRET']);
        $this->assertSame('***REDACTED***', $data['request_server']['DATABASE_PASSWORD']);
    }

    public function testFormatRedactsBroadSetOfSensitiveHeaders()
    {
        $request = Request::create('/');
        $request->headers->set('Proxy-Authorization', 'Basic abc');
        $request->headers->set('Www-Authenticate', 'Bearer realm="x"');
        $request->headers->set('X-Csrf-Token', 'csrf-123');
        $request->headers->set('X-Xsrf-Token', 'xsrf-123');
        $request->headers->set('X-Api-Key', 'key-123');
        $request->headers->set('X-Amz-Security-Token', 'amz-123');
        $request->headers->set('X-Custom-Auth', 'auth-123');
        $request->headers->set('Accept', 'application/json');

        $data = $this->format($this->collect($request));

        $this->assertSame('***REDACTED***', $data['request_headers']['proxy-authorization']);
        $this->assertSame('***REDACTED***', $data['request_headers']['www-authenticate']);
        $this->assertSame('***REDACTED***', $data['request_headers']['x-csrf-token']);
        $this->assertSame('***REDACTED***', $data['request_headers']['x-xsrf-token']);
        $this->assertSame('***REDACTED***', $data['request_headers']['x-api-key']);
        $this->assertSame('***REDACTED***', $data['request_headers']['x-amz-security-token']);
        $this->assertSame('***REDACTED***', $data['request_headers']['x-custom-auth']);

        // Non-sensitive headers stay untouched.
        $this->assertNotSame('***REDACTED***', $data['request_headers']['accept']);
    }

    public function testGetSummaryExposesOnlyNonSensitiveMetadata()
    {
        $summary = $this->formatter->getSummary($this->collect(Request::create('/login', 'POST', ['password' => 'hunter2'])));

        $this->assertSame('POST', $summary['method']);
        $this->assertSame('/login', $summary['path']);
        $this->assertArrayHasKey('status_code', $summary);
        $this->assertArrayNotHasKey('request_request', $summary);
    }

    private function collect(Request $request): RequestDataCollector
    {
        $collector = new RequestDataCollector();
        $collector->collect($request, new Response());
        // Mirror the profiler lifecycle: data is cloned into a VarDumper Data
        // object during lateCollect(), which is what the formatter reads back.
        $collector->lateCollect();

        return $collector;
    }

    /**
     * @return array<string, mixed>
     */
    private function format(RequestDataCollector $collector): array
    {
        return $this->formatter->format($collector);
    }
}

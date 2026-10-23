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
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\HttpClientCollectorFormatter;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\TraceableHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class HttpClientCollectorFormatterTest extends TestCase
{
    private HttpClientCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new HttpClientCollectorFormatter();
    }

    public function testGetName()
    {
        $this->assertSame('http_client', $this->formatter->getName());
    }

    public function testFormatWithNoRequests()
    {
        $collector = $this->collect([]);

        $result = $this->formatter->format($collector);

        $this->assertSame(0, $result['request_count']);
        $this->assertSame(0, $result['failed_count']);
        $this->assertSame(0, $result['total_time_ms']);
        $this->assertSame([], $result['calls']);
        $this->assertFalse($result['calls_truncated']);
    }

    public function testSummaryWithNoRequests()
    {
        $summary = $this->formatter->getSummary($this->collect([]));

        $this->assertSame(0, $summary['request_count']);
        $this->assertSame(0, $summary['total_time_ms']);
        $this->assertSame(0, $summary['failed_count']);
        $this->assertNull($summary['slowest']);
    }

    public function testFormatSuccessfulRequest()
    {
        $collector = $this->collect([
            ['GET', 'https://api.example.com/rates', new MockResponse('{"EUR":1}', ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']])],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(1, $result['request_count']);
        $this->assertSame(0, $result['failed_count']);
        $this->assertCount(1, $result['calls']);

        $call = $result['calls'][0];
        $this->assertSame('http_client', $call['client']);
        $this->assertSame('GET', $call['method']);
        $this->assertSame('https://api.example.com/rates', $call['url']);
        $this->assertSame(200, $call['http_code']);
        $this->assertFalse($call['failed']);
        $this->assertNull($call['error']);
        $this->assertNull($call['redirect_count']);
        $this->assertIsInt($call['total_time_ms']);
    }

    public function testFormatTransportError()
    {
        // a real transport failure, as recorded by the curl handler
        $collector = $this->collectFromTraces([
            ['method' => 'GET', 'url' => 'http://127.0.0.1:8779/shipping', 'http_code' => 0, 'info' => ['error' => 'Idle timeout reached for "http://127.0.0.1:8779/shipping".']],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(1, $result['failed_count']);

        $call = $result['calls'][0];
        $this->assertSame(0, $call['http_code']);
        $this->assertTrue($call['failed']);
        $this->assertSame('Idle timeout reached for "http://127.0.0.1:8779/shipping".', $call['error']);
    }

    public function testFormatClientAndServerErrors()
    {
        $collector = $this->collect([
            ['GET', 'https://api.example.com/missing', new MockResponse('', ['http_code' => 404])],
            ['POST', 'https://api.example.com/boom', new MockResponse('', ['http_code' => 503])],
            ['GET', 'https://api.example.com/redirected', new MockResponse('', ['http_code' => 302])],
            ['GET', 'https://api.example.com/ok', new MockResponse('', ['http_code' => 204])],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(4, $result['request_count']);
        $this->assertSame(2, $result['failed_count']);

        $byUrl = array_column($result['calls'], null, 'url');
        $this->assertTrue($byUrl['https://api.example.com/missing']['failed']);
        $this->assertSame(404, $byUrl['https://api.example.com/missing']['http_code']);
        $this->assertSame('POST', $byUrl['https://api.example.com/boom']['method']);
        $this->assertTrue($byUrl['https://api.example.com/boom']['failed']);
        $this->assertFalse($byUrl['https://api.example.com/redirected']['failed']);
        $this->assertFalse($byUrl['https://api.example.com/ok']['failed']);
    }

    public function testFormatRedirectCount()
    {
        $collector = $this->collect([
            ['GET', 'https://api.example.com/old', new MockResponse('', ['http_code' => 200, 'redirect_count' => 2])],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(2, $result['calls'][0]['redirect_count']);
    }

    public function testFormatConvertsSecondsToRoundedMilliseconds()
    {
        $collector = $this->collect([
            ['GET', 'https://api.example.com/slow', new MockResponse('', ['http_code' => 200, 'total_time' => 1.2346])],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(1235, $result['calls'][0]['total_time_ms']);
        $this->assertSame(1235, $result['total_time_ms']);
    }

    public function testFormatStripsUserinfoFragmentAndMasksSecrets()
    {
        $collector = $this->collect([
            ['GET', 'https://user:p4ssw0rd@api.example.com:8443/v1/items?page=2&api_key=abc123&access_token=xyz&q=twig&sig=deadbeef&flag#frag', new MockResponse('', ['http_code' => 200])],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(
            'https://api.example.com:8443/v1/items?page=2&api_key=***&access_token=***&q=twig&sig=***&flag',
            $result['calls'][0]['url']
        );
    }

    public function testFormatMasksTokenLookingValuesAndPathSegments()
    {
        $collector = $this->collect([
            ['GET', 'https://api.example.com/reset/0123456789abcdef0123456789abcdef/confirm?ref=Zm9vYmFyYmF6cXV4MTIzNDU2&lang=en&slug=my-descriptive-slug-value', new MockResponse('', ['http_code' => 200])],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(
            'https://api.example.com/reset/***/confirm?ref=***&lang=en&slug=my-descriptive-slug-value',
            $result['calls'][0]['url']
        );
    }

    public function testFormatSanitizesUrlsQuotedInErrors()
    {
        $collector = $this->collectFromTraces([
            ['method' => 'GET', 'url' => 'https://api.example.com/x?token=secret', 'http_code' => 0, 'info' => ['error' => 'Idle timeout reached for "https://u:p@api.example.com/x?token=secret".']],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame('Idle timeout reached for "https://api.example.com/x?token=***".', $result['calls'][0]['error']);
    }

    public function testFormatNeverExposesHeadersOrBodies()
    {
        $collector = $this->collect([
            ['POST', 'https://api.example.com/charge', new MockResponse('{"card":"4111111111111111"}', ['http_code' => 200, 'response_headers' => ['Set-Cookie' => 'session=topsecret']]), [
                'headers' => ['Authorization' => 'Bearer supersecret'],
                'body' => '{"password":"hunter2"}',
            ]],
        ]);

        $encoded = json_encode([$this->formatter->format($collector), $this->formatter->getSummary($collector)], \JSON_THROW_ON_ERROR);

        foreach (['supersecret', 'hunter2', '4111111111111111', 'topsecret', 'Authorization'] as $needle) {
            $this->assertStringNotContainsString($needle, $encoded);
        }
    }

    public function testFormatSortsSlowestFirstKeepingOrderOnTies()
    {
        $collector = $this->collect([
            ['GET', 'https://api.example.com/a', new MockResponse('', ['http_code' => 200, 'total_time' => 0.1])],
            ['GET', 'https://api.example.com/b', new MockResponse('', ['http_code' => 200, 'total_time' => 0.5])],
            ['GET', 'https://api.example.com/c', new MockResponse('', ['http_code' => 200, 'total_time' => 0.1])],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(
            ['https://api.example.com/b', 'https://api.example.com/a', 'https://api.example.com/c'],
            array_column($result['calls'], 'url')
        );
    }

    public function testFormatTruncatesCallsButKeepsTotals()
    {
        $requests = [];
        for ($i = 1; $i <= 55; ++$i) {
            $requests[] = ['GET', 'https://api.example.com/item/'.$i, new MockResponse('', ['http_code' => 500, 'total_time' => $i / 1000])];
        }

        $result = $this->formatter->format($this->collect($requests));

        $this->assertSame(55, $result['request_count']);
        $this->assertSame(55, $result['failed_count']);
        $this->assertSame(array_sum(range(1, 55)), $result['total_time_ms']);
        $this->assertCount(50, $result['calls']);
        $this->assertTrue($result['calls_truncated']);
        // The slowest call survives the cut, the fastest are dropped
        $this->assertSame('https://api.example.com/item/55', $result['calls'][0]['url']);
        $this->assertNotContains('https://api.example.com/item/1', array_column($result['calls'], 'url'));
    }

    public function testGetSummary()
    {
        $collector = $this->collect([
            ['GET', 'https://api.example.com/fast', new MockResponse('', ['http_code' => 200, 'total_time' => 0.05])],
            ['GET', 'https://api.example.com/slow?token=abc', new MockResponse('', ['http_code' => 200, 'total_time' => 2.0])],
            ['GET', 'https://api.example.com/gone', new MockResponse('', ['http_code' => 404, 'total_time' => 0.1])],
        ]);

        $summary = $this->formatter->getSummary($collector);

        $this->assertSame(3, $summary['request_count']);
        $this->assertSame(2150, $summary['total_time_ms']);
        $this->assertSame(1, $summary['failed_count']);
        $this->assertSame(['url' => 'https://api.example.com/slow?token=***', 'total_time_ms' => 2000], $summary['slowest']);
    }

    public function testGetSummaryCountsTransportErrorsAsFailed()
    {
        $collector = $this->collectFromTraces([
            ['method' => 'GET', 'url' => 'https://api.example.com/ok', 'http_code' => 200, 'total_time' => 0.01, 'info' => []],
            ['method' => 'GET', 'url' => 'https://api.example.com/down', 'http_code' => 0, 'info' => ['error' => 'Connection refused']],
        ]);

        $summary = $this->formatter->getSummary($collector);

        $this->assertSame(2, $summary['request_count']);
        $this->assertSame(1, $summary['failed_count']);
    }

    public function testFormatReadsTotalTimeFromClonedInfoOfOlderSymfonyVersions()
    {
        $collector = $this->collectFromTraces([
            ['method' => 'GET', 'url' => 'https://api.example.com/shipping', 'http_code' => 200, 'info' => ['info' => ['total_time' => 2.002161]]],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(2002, $result['calls'][0]['total_time_ms']);
    }

    public function testFormatListsMultipleClients()
    {
        $collector = new HttpClientDataCollector();
        $collector->registerClient('first', $first = new TraceableHttpClient(new MockHttpClient(new MockResponse('', ['http_code' => 200]))));
        $collector->registerClient('second', $second = new TraceableHttpClient(new MockHttpClient(new MockResponse('', ['http_code' => 500]))));
        $first->request('GET', 'https://one.example.com/')->getStatusCode();
        $second->request('GET', 'https://two.example.com/')->getStatusCode();
        $collector->collect(new Request(), new Response());

        $result = $this->formatter->format($collector);

        $this->assertSame(2, $result['request_count']);
        $this->assertSame(['first', 'second'], array_column($result['calls'], 'client'));
    }

    /**
     * @param list<array{0: string, 1: string, 2: MockResponse, 3?: array<string, mixed>}> $requests
     */
    private function collect(array $requests): HttpClientDataCollector
    {
        $client = new TraceableHttpClient(new MockHttpClient(array_column($requests, 2)));
        $collector = new HttpClientDataCollector();
        $collector->registerClient('http_client', $client);

        foreach ($requests as $request) {
            $traced = $client->request($request[0], $request[1], $request[3] ?? []);

            try {
                $traced->getStatusCode();
            } catch (ExceptionInterface) {
                // transport errors are part of what is being traced
            }
        }

        $collector->collect(new Request(), new Response());

        return $collector;
    }

    /**
     * @param list<array{method: string, url: string, http_code: int, total_time?: float, info: array<string, mixed>}> $traces
     */
    private function collectFromTraces(array $traces): HttpClientDataCollector
    {
        $cloner = new VarCloner();
        foreach ($traces as $i => $trace) {
            $traces[$i]['options'] = $cloner->cloneVar([]);
            $traces[$i]['info'] = $cloner->cloneVar($trace['info']);
        }

        $collector = new HttpClientDataCollector();
        (new \ReflectionProperty(DataCollector::class, 'data'))->setValue($collector, [
            'request_count' => \count($traces),
            'error_count' => 0,
            'total_time' => 0.0,
            'clients' => ['http_client' => ['traces' => $traces, 'error_count' => 0]],
        ]);

        return $collector;
    }
}

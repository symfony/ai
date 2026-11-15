<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Decision;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Decision;
use Symfony\AI\Platform\Bridge\Gemini\Decision\ModelClient;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ModelClientTest extends TestCase
{
    public function testItSupportsOnlyDecisionModels()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new Decision('gemini-decision-1')));
        $this->assertFalse($client->supports(new Gemini('gemini-2.5-flash')));
    }

    public function testItSendsTheDecisionToTheDecisionsEndpoint()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://gemini.example.com/v1beta/decisions', $url);
            $this->assertContains('x-goog-api-key: test-key', $options['headers']);
            $this->assertSame('{"model":"gemini-decision-1","input":"My invoice is wrong.","questions":{"is_bug":{"type":"noul","instructions":"Is this a software defect?"}}}', $options['body']);

            return new MockResponse();
        });

        (new ModelClient($httpClient, 'test-key', 'https://gemini.example.com/'))->request(new Decision('gemini-decision-1'), ['input' => 'My invoice is wrong.'], [
            'questions' => ['is_bug' => ['type' => 'noul', 'instructions' => 'Is this a software defect?']],
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItRejectsAnInvocationWithoutQuestions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The decision model "gemini-decision-1" requires the "questions" option.');

        (new ModelClient(new MockHttpClient(), 'test-key'))->request(new Decision('gemini-decision-1'), 'My invoice is wrong.');
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Decision;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Decision\DecisionClient;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DecisionClientTest extends TestCase
{
    public function testItSendsTheQuestionsAsANamedListToTheDecisionsEndpoint()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://eu.api.openai.com/v1/decisions', $url);
            $this->assertContains('Authorization: Bearer sk-api-key', $options['headers']);
            $this->assertSame('{"model":"gpt-6-luna","input":"My invoice is wrong.","questions":[{"name":"is_bug","type":"predicate","instructions":"Is this a software defect?"},{"name":"0","type":"predicate","instructions":"Is this a question?"}]}', $options['body']);

            return new MockResponse();
        });

        (new DecisionClient($httpClient, 'sk-api-key', Factory::REGION_EU))->request(new Gpt('gpt-6-luna'), ['input' => 'My invoice is wrong.'], ['questions' => [
            'is_bug' => ['type' => 'predicate', 'instructions' => 'Is this a software defect?'],
            0 => ['type' => 'predicate', 'instructions' => 'Is this a question?'],
        ]]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItRejectsAnUnnormalizedPayload()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The decision model "gpt-6-luna" requires a string, an array, a "Text", an "Image" or a "Collection" input.');

        (new DecisionClient(new MockHttpClient(), 'sk-api-key'))->request(new Gpt('gpt-6-luna'), [], ['questions' => []]);
    }
}

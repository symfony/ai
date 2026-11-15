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
use Symfony\AI\Platform\Bridge\Gemini\Factory;
use Symfony\AI\Platform\Decision\Answer\Answers;
use Symfony\AI\Platform\Decision\Question\NoulQuestion;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class PlatformTest extends TestCase
{
    public function testItEvaluatesThroughTheDecisionsApi()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('https://generativelanguage.googleapis.com/v1beta/decisions', $url);
            $this->assertSame('{"model":"gemini-decision-1","input":"I need this fixed by tomorrow.","questions":{"is_urgent":{"type":"noul","instructions":"Does this convey urgency?"}}}', $options['body']);

            return new JsonMockResponse(['answers' => ['is_urgent' => ['type' => 'noul', 'noul' => 0.97, 'confidence' => 0.97]]]);
        });

        $result = Factory::createPlatform('test-key', $httpClient)->invoke('gemini-decision-1', 'I need this fixed by tomorrow.', ['questions' => [
            'is_urgent' => new NoulQuestion('Does this convey urgency?'),
        ]]);

        $this->assertTrue($result->asObject(Answers::class)->getNoul('is_urgent')->isTrue());
    }
}

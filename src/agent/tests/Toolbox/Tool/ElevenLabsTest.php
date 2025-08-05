<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Tool\ElevenLabs;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ElevenLabs::class)]
final class ElevenLabsTest extends TestCase
{
    public function testTextToSpeech()
    {
        $httpClient = new MockHttpClient(
            new MockResponse(file_get_contents(__DIR__.'/../../../../../fixtures/audio.mp3'), [
                'headers' => [
                    'Content-Type' => 'audio/mpeg',
                ],
                'http_code' => 200,
            ]),
        );

        $elevenLabs = new ElevenLabs($httpClient, 'foo', 'bar', 'baz', 'random');

        $result = $elevenLabs('Hello World');

        $this->assertCount(2, $result);
        $this->assertSame('Hello World', $result['input']);
        $this->assertNotEmpty($result['path']);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Youtube\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Youtube\YoutubeTranscriber;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class YoutubeTranscriberTest extends TestCase
{
    public function testInvoke()
    {
        $httpClient = new MockHttpClient([
            MockResponse::fromFile(__DIR__.'/Fixtures/video-page.html'),
            MockResponse::fromFile(__DIR__.'/Fixtures/captions.json'),
            MockResponse::fromFile(__DIR__.'/Fixtures/transcript.xml'),
        ]);

        $transcriber = new YoutubeTranscriber($httpClient);

        $result = $transcriber('dQw4w9WgXcQ');

        $this->assertSame(\PHP_EOL.'Hello and welcome to this video'.\PHP_EOL.'Today we will learn about PHP'.\PHP_EOL.'Thank you for watching', $result);
    }

    #[DataProvider('provideTranscriptContents')]
    public function testPreservesTranscriptContents(string $xml, string $expected)
    {
        $httpClient = new MockHttpClient([
            MockResponse::fromFile(__DIR__.'/Fixtures/video-page.html'),
            MockResponse::fromFile(__DIR__.'/Fixtures/captions.json'),
            new MockResponse($xml),
        ]);

        $transcriber = new YoutubeTranscriber($httpClient);

        $this->assertSame($expected, $transcriber('dQw4w9WgXcQ'));
        $this->assertSame(3, $httpClient->getRequestsCount());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideTranscriptContents(): iterable
    {
        yield 'empty transcript' => ['<transcript/>', ''];
        yield 'single segment' => [
            '<transcript><text start="0" dur="1">Hello</text></transcript>',
            \PHP_EOL.'Hello',
        ];
        yield 'empty segment' => [
            '<transcript><text start="0" dur="1"></text><text start="1" dur="1">Hello</text></transcript>',
            \PHP_EOL.\PHP_EOL.'Hello',
        ];
        yield 'surrounding whitespace' => [
            '<transcript><text start="0" dur="1">  Hello  </text></transcript>',
            \PHP_EOL.'  Hello  ',
        ];
        yield 'unicode, entities and embedded newline' => [
            '<transcript><text start="0" dur="1">Привет &amp; PHP&#10;第二行</text></transcript>',
            \PHP_EOL."Привет & PHP\n第二行",
        ];

        $segments = [];
        $lines = [];
        for ($i = 0; $i < 1000; ++$i) {
            $segments[] = \sprintf('<text start="%d" dur="1">Line %d</text>', $i, $i);
            $lines[] = 'Line '.$i;
        }

        yield 'long transcript' => [
            '<transcript>'.implode('', $segments).'</transcript>',
            \PHP_EOL.implode(\PHP_EOL, $lines),
        ];
    }
}

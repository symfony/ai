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
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\TranslationCollectorFormatter;
use Symfony\Component\Translation\DataCollector\TranslationDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class TranslationCollectorFormatterTest extends TestCase
{
    private TranslationCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new TranslationCollectorFormatter();
    }

    public function testGetName()
    {
        $this->assertSame('translation', $this->formatter->getName());
    }

    public function testFormat()
    {
        $collector = $this->createMock(TranslationDataCollector::class);
        $collector->method('getLocale')->willReturn('en');
        $collector->method('getFallbackLocales')->willReturn(['fr', 'de']);
        $collector->method('getCountDefines')->willReturn(5);
        $collector->method('getCountMissings')->willReturn(2);
        $collector->method('getCountFallbacks')->willReturn(1);
        $collector->method('getMessages')->willReturn([
            [
                'locale' => 'en',
                'domain' => 'messages',
                'id' => 'hello',
                'translation' => 'Hello',
                'state' => 0,
                'count' => 3,
            ],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame('en', $result['locale']);
        $this->assertSame(['fr', 'de'], $result['fallback_locales']);
        $this->assertSame(5, $result['count_defines']);
        $this->assertSame(2, $result['count_missings']);
        $this->assertSame(1, $result['count_fallbacks']);
        $this->assertCount(1, $result['messages']);
        $this->assertSame('en', $result['messages'][0]['locale']);
        $this->assertSame('messages', $result['messages'][0]['domain']);
        $this->assertSame('hello', $result['messages'][0]['id']);
        $this->assertSame('Hello', $result['messages'][0]['translation']);
        $this->assertSame('defined', $result['messages'][0]['state']);
        $this->assertSame(3, $result['messages'][0]['count']);
    }

    public function testFormatWithMissingTranslation()
    {
        $collector = $this->createMock(TranslationDataCollector::class);
        $collector->method('getLocale')->willReturn('en');
        $collector->method('getFallbackLocales')->willReturn([]);
        $collector->method('getCountDefines')->willReturn(0);
        $collector->method('getCountMissings')->willReturn(1);
        $collector->method('getCountFallbacks')->willReturn(0);
        $collector->method('getMessages')->willReturn([
            [
                'locale' => 'en',
                'domain' => 'messages',
                'id' => 'missing.key',
                'translation' => 'missing.key',
                'state' => 1,
                'count' => 1,
            ],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame('missing', $result['messages'][0]['state']);
    }

    public function testFormatWithFallbackTranslation()
    {
        $collector = $this->createMock(TranslationDataCollector::class);
        $collector->method('getLocale')->willReturn('de');
        $collector->method('getFallbackLocales')->willReturn(['en']);
        $collector->method('getCountDefines')->willReturn(0);
        $collector->method('getCountMissings')->willReturn(0);
        $collector->method('getCountFallbacks')->willReturn(1);
        $collector->method('getMessages')->willReturn([
            [
                'locale' => 'en',
                'domain' => 'messages',
                'id' => 'fallback.key',
                'translation' => 'Fallback value',
                'state' => 2,
                'count' => 1,
            ],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame('fallback', $result['messages'][0]['state']);
    }

    public function testGetSummary()
    {
        $collector = $this->createMock(TranslationDataCollector::class);
        $collector->method('getLocale')->willReturn('en');
        $collector->method('getCountDefines')->willReturn(10);
        $collector->method('getCountMissings')->willReturn(3);
        $collector->method('getCountFallbacks')->willReturn(2);

        $result = $this->formatter->getSummary($collector);

        $this->assertSame('en', $result['locale']);
        $this->assertSame(10, $result['count_defines']);
        $this->assertSame(3, $result['count_missings']);
        $this->assertSame(2, $result['count_fallbacks']);
        $this->assertArrayNotHasKey('messages', $result);
        $this->assertArrayNotHasKey('fallback_locales', $result);
    }

    public function testFormatHandlesDataObjectForFallbackLocales()
    {
        $data = $this->createMock(Data::class);
        $data->method('getValue')->with(true)->willReturn(['fr', 'de']);

        $collector = $this->createMock(TranslationDataCollector::class);
        $collector->method('getLocale')->willReturn('en');
        $collector->method('getFallbackLocales')->willReturn($data);
        $collector->method('getCountDefines')->willReturn(0);
        $collector->method('getCountMissings')->willReturn(0);
        $collector->method('getCountFallbacks')->willReturn(0);
        $collector->method('getMessages')->willReturn([]);

        $result = $this->formatter->format($collector);

        $this->assertSame(['fr', 'de'], $result['fallback_locales']);
    }

    public function testFormatHandlesDataObjectForMessages()
    {
        $messages = [
            [
                'locale' => 'en',
                'domain' => 'messages',
                'id' => 'hello',
                'translation' => 'Hello',
                'state' => 0,
                'count' => 1,
            ],
        ];

        $data = $this->createMock(Data::class);
        $data->method('getValue')->with(true)->willReturn($messages);

        $collector = $this->createMock(TranslationDataCollector::class);
        $collector->method('getLocale')->willReturn('en');
        $collector->method('getFallbackLocales')->willReturn([]);
        $collector->method('getCountDefines')->willReturn(1);
        $collector->method('getCountMissings')->willReturn(0);
        $collector->method('getCountFallbacks')->willReturn(0);
        $collector->method('getMessages')->willReturn($data);

        $result = $this->formatter->format($collector);

        $this->assertCount(1, $result['messages']);
        $this->assertSame('hello', $result['messages'][0]['id']);
        $this->assertSame('defined', $result['messages'][0]['state']);
    }
}

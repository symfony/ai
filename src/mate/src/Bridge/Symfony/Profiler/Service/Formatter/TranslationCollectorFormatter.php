<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter;

use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorFormatterInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\Translation\DataCollector\TranslationDataCollector;

/**
 * Formats translation collector data.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<TranslationDataCollector>
 */
final class TranslationCollectorFormatter implements CollectorFormatterInterface
{
    private const STATE_MAP = [0 => 'defined', 1 => 'missing', 2 => 'fallback'];

    public function getName(): string
    {
        return 'translation';
    }

    public function format(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof TranslationDataCollector);

        return [
            'locale' => $collector->getLocale(),
            'fallback_locales' => $this->extractArray($collector->getFallbackLocales()),
            'count_defines' => $collector->getCountDefines(),
            'count_missings' => $collector->getCountMissings(),
            'count_fallbacks' => $collector->getCountFallbacks(),
            'messages' => $this->formatMessages($collector->getMessages()),
        ];
    }

    public function getSummary(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof TranslationDataCollector);

        return [
            'locale' => $collector->getLocale(),
            'count_defines' => $collector->getCountDefines(),
            'count_missings' => $collector->getCountMissings(),
            'count_fallbacks' => $collector->getCountFallbacks(),
        ];
    }

    /**
     * @return array<mixed>
     */
    private function extractArray(mixed $data): array
    {
        if (\is_array($data)) {
            return $data;
        }

        if (\is_object($data) && method_exists($data, 'getValue')) {
            return (array) $data->getValue(true);
        }

        return [];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function formatMessages(mixed $messages): array
    {
        $messages = $this->extractArray($messages);

        $formatted = [];
        foreach ($messages as $message) {
            if (!\is_array($message)) {
                continue;
            }

            $formatted[] = [
                'locale' => $message['locale'] ?? null,
                'domain' => $message['domain'] ?? null,
                'id' => $message['id'] ?? null,
                'translation' => $message['translation'] ?? null,
                'state' => $this->mapState((int) ($message['state'] ?? 0)),
                'count' => $message['count'] ?? 1,
            ];
        }

        return $formatted;
    }

    private function mapState(int $state): string
    {
        return self::STATE_MAP[$state] ?? 'defined';
    }
}

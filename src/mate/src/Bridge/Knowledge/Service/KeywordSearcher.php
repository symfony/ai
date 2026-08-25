<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Knowledge\Service;

use Symfony\AI\Mate\Bridge\Knowledge\Model\PageChunk;

/**
 * Case-insensitive substring search across a provider's chunks.
 *
 * Score = total number of occurrences of the query in the chunk content
 * (with section title bonus). Snippet = ~200 chars around the first hit.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class KeywordSearcher implements SearcherInterface
{
    private const SNIPPET_RADIUS = 100;

    /**
     * @param list<PageChunk> $chunks
     *
     * @return list<array{path: string, page_title: string, section_title: string, score: int, snippet: string}>
     */
    public function search(array $chunks, string $query, int $limit = 20): array
    {
        $needle = trim($query);
        if ('' === $needle) {
            return [];
        }

        $needleLower = mb_strtolower($needle);
        $results = [];

        foreach ($chunks as $chunk) {
            $haystack = $chunk->getContent();
            $haystackLower = mb_strtolower($haystack);
            $occurrences = mb_substr_count($haystackLower, $needleLower);

            $titleHit = str_contains(mb_strtolower($chunk->getSectionTitle()), $needleLower)
                || str_contains(mb_strtolower($chunk->getPageTitle()), $needleLower);

            if (0 === $occurrences && !$titleHit) {
                continue;
            }

            $score = $occurrences + ($titleHit ? 5 : 0);

            $results[] = [
                'path' => $chunk->getPath(),
                'page_title' => $chunk->getPageTitle(),
                'section_title' => $chunk->getSectionTitle(),
                'score' => $score,
                'snippet' => $this->makeSnippet($haystack, $haystackLower, $needleLower),
            ];
        }

        usort($results, static fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return \array_slice($results, 0, max(1, $limit));
    }

    private function makeSnippet(string $haystack, string $haystackLower, string $needleLower): string
    {
        $position = mb_strpos($haystackLower, $needleLower);
        if (false === $position) {
            return mb_substr(trim($haystack), 0, self::SNIPPET_RADIUS * 2);
        }

        $start = max(0, $position - self::SNIPPET_RADIUS);
        $length = self::SNIPPET_RADIUS * 2 + mb_strlen($needleLower);
        $snippet = mb_substr($haystack, $start, $length);
        $snippet = trim(preg_replace('/\s+/', ' ', $snippet) ?? $snippet);

        if ($start > 0) {
            $snippet = '...'.$snippet;
        }
        if ($start + $length < mb_strlen($haystack)) {
            $snippet .= '...';
        }

        return $snippet;
    }
}

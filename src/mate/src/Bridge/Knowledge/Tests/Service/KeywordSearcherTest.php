<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Knowledge\Tests\Service;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Knowledge\Model\PageChunk;
use Symfony\AI\Mate\Bridge\Knowledge\Service\KeywordSearcher;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class KeywordSearcherTest extends TestCase
{
    public function testSearchFindsCaseInsensitiveContentMatches()
    {
        $chunks = [
            new PageChunk('setup', 'Setup', 'Installing', 0, 'Run composer require to install the FrameworkBundle.'),
            new PageChunk('intro', 'Intro', 'Overview', 0, 'Welcome to the docs.'),
        ];

        $searcher = new KeywordSearcher();
        $results = $searcher->search($chunks, 'frameworkbundle');

        $this->assertCount(1, $results);
        $this->assertSame('setup', $results[0]['path']);
        $this->assertStringContainsString('FrameworkBundle', $results[0]['snippet']);
    }

    public function testSearchScoresTitleMatchesHigher()
    {
        $chunks = [
            new PageChunk('a', 'Random page', 'Random', 0, 'mentions caching once'),
            new PageChunk('b', 'Caching guide', 'Strategies', 0, 'one mention'),
        ];

        $results = (new KeywordSearcher())->search($chunks, 'caching');

        $this->assertSame('b', $results[0]['path']);
    }

    public function testSearchReturnsEmptyArrayForEmptyQuery()
    {
        $chunks = [new PageChunk('a', 'A', 'A', 0, 'whatever')];

        $this->assertSame([], (new KeywordSearcher())->search($chunks, ''));
    }

    public function testSearchHonorsLimit()
    {
        $chunks = [];
        for ($i = 0; $i < 5; ++$i) {
            $chunks[] = new PageChunk('p'.$i, 'P', 'S', 0, 'symfony');
        }

        $results = (new KeywordSearcher())->search($chunks, 'symfony', 2);

        $this->assertCount(2, $results);
    }
}

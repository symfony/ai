<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\PlatformBridge\Filter;

use App\PlatformBridge\Filter\Sort;
use App\PlatformBridge\PlatformBridge;
use App\PlatformBridge\Taxonomy\Capability;
use PHPUnit\Framework\TestCase;

final class SortTest extends TestCase
{
    public function testPopularityPutsTheMostDownloadedFirst()
    {
        $this->assertSame(['OpenAI', 'Anthropic', 'Ollama', 'Voyage'], $this->names(Sort::Popularity->apply($this->bridges())), 'Ties keep the name order, bridges without downloads come last.');
    }

    public function testNameKeepsTheOrderOfTheCatalog()
    {
        $this->assertSame(['Anthropic', 'Ollama', 'OpenAI', 'Voyage'], $this->names(Sort::Name->apply($this->bridges())));
    }

    public function testCapabilitiesPutsTheMostCapableFirst()
    {
        $this->assertSame(['OpenAI', 'Anthropic', 'Ollama', 'Voyage'], $this->names(Sort::Capabilities->apply($this->bridges())));
    }

    /**
     * @return list<PlatformBridge> sorted by name, as the catalog returns them
     */
    private function bridges(): array
    {
        return [
            new PlatformBridge('Anthropic', 'anthropic', 'Anthropic', '', 'symfony/ai-anthropic-platform', 'https://github.com', capabilities: [Capability::Chat, Capability::Vision], downloads: 210_380),
            new PlatformBridge('Ollama', 'ollama', 'Ollama', '', 'symfony/ai-ollama-platform', 'https://github.com', capabilities: [Capability::Chat], downloads: 210_380),
            new PlatformBridge('OpenAi', 'open-ai', 'OpenAI', '', 'symfony/ai-open-ai-platform', 'https://github.com', capabilities: [Capability::Chat, Capability::Vision, Capability::Embeddings], downloads: 734_471),
            new PlatformBridge('Voyage', 'voyage', 'Voyage', '', 'symfony/ai-voyage-platform', 'https://github.com', capabilities: [Capability::Embeddings]),
        ];
    }

    /**
     * @param list<PlatformBridge> $bridges
     *
     * @return list<string>
     */
    private function names(array $bridges): array
    {
        return array_map(static fn (PlatformBridge $bridge): string => $bridge->name, $bridges);
    }
}

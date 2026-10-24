<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Dev;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\ModelCatalog as OpenAiModelCatalog;
use Symfony\AI\Platform\Bridge\OpenRouter\ModelCatalog;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Andrey Bolonin <andreybolonin1989@gmail.com>
 */
final class UpdateModelCatalogsTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__.'/../../dev/update-model-catalogs.php';
    }

    public function testModelsDevPreservesCuratedEntries()
    {
        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $this->assertTrue(writeCatalog($io, 'OpenAi', [], true, true));
        $this->assertStringContainsString(\sprintf('%d models (0 new, 0 removed)', \count((new OpenAiModelCatalog())->getModels())), $output->fetch());
    }

    public function testPruneRejectsEmptyAndPartialFeedsWithoutWriting()
    {
        $file = __DIR__.'/../../src/Bridge/OpenRouter/ModelCatalog.php';
        $source = file_get_contents($file);
        $baseline = array_diff_key((new ModelCatalog())->getModels(), array_flip(OPENROUTER_ROUTING_KEYS));

        foreach ([[], \array_slice($baseline, 0, (int) (\count($baseline) / 2), true)] as $fresh) {
            $output = new BufferedOutput();
            $io = new SymfonyStyle(new ArrayInput([]), $output);

            $this->assertFalse(writeCatalog($io, 'OpenRouter', $fresh, true, false, OPENROUTER_ROUTING_KEYS, prune: true));
            $this->assertStringContainsString('refusing to remove', $output->fetch());
            $this->assertSame($source, file_get_contents($file));
        }
    }

    public function testDryRunListsRemovalsWithoutWriting()
    {
        $file = __DIR__.'/../../src/Bridge/OpenRouter/ModelCatalog.php';
        $source = file_get_contents($file);
        $baseline = array_diff_key((new ModelCatalog())->getModels(), array_flip(OPENROUTER_ROUTING_KEYS));
        $removed = array_key_first($baseline);
        $fresh = $baseline;
        unset($fresh[$removed]);
        $output = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $output);

        $this->assertTrue(writeCatalog($io, 'OpenRouter', $fresh, true, true, OPENROUTER_ROUTING_KEYS, prune: true));
        $text = $output->fetch();
        $this->assertStringContainsString('0 new, 1 removed', $text);
        $this->assertStringContainsString('Removed models', $text);
        $this->assertStringContainsString($removed, $text);
        $this->assertSame($source, file_get_contents($file));
    }
}

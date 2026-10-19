<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Document\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Document\DocumentInterface;
use Symfony\AI\Store\Document\Loader\DirectoryLoader;
use Symfony\AI\Store\Document\Loader\MarkdownLoader;
use Symfony\AI\Store\Document\Loader\TextFileLoader;
use Symfony\AI\Store\Document\LoaderInterface;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;

final class DirectoryLoaderTest extends TestCase
{
    private string $directory;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->directory = sys_get_temp_dir().'/directory-loader-'.uniqid();
        $this->fs->mkdir($this->directory);

        $this->fs->dumpFile($this->directory.'/alpha.md', "# Alpha\n\nAlpha content.\n");
        $this->fs->dumpFile($this->directory.'/beta.md', "# Beta\n\nBeta content.\n");
        $this->fs->dumpFile($this->directory.'/UPPER.MD', "# Upper\n\nUpper content.\n");
        $this->fs->dumpFile($this->directory.'/notes.txt', "Plain text notes.\n");
        $this->fs->dumpFile($this->directory.'/data.csv', "col\nvalue\n");
        $this->fs->dumpFile($this->directory.'/nested/gamma.md', "# Gamma\n\nGamma content.\n");
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->directory);
    }

    public function testLoadDispatchesByExtensionRecursivelyByDefault()
    {
        $loader = new DirectoryLoader([
            'md' => new MarkdownLoader(),
            'txt' => new TextFileLoader(),
        ]);

        $documents = iterator_to_array($loader->load($this->directory), false);

        // 4 markdown files (incl. the nested one and the uppercase extension) + 1 text file; the .csv is skipped.
        $this->assertCount(5, $documents);
        $this->assertContainsOnlyInstancesOf(TextDocument::class, $documents);

        $titles = $this->titlesOf($documents);
        $this->assertSame(['Alpha', 'Beta', 'Gamma', 'Upper'], $titles);

        $contents = array_map(static fn (DocumentInterface $d) => $d->getContent(), $documents);
        $this->assertContains('Plain text notes.', $contents);
    }

    public function testNonRecursiveSkipsSubdirectories()
    {
        $loader = new DirectoryLoader(['md' => new MarkdownLoader()], recursive: false);

        $documents = iterator_to_array($loader->load($this->directory), false);

        $this->assertCount(3, $documents);
        $this->assertNotContains('Gamma', $this->titlesOf($documents));
    }

    public function testSkipsFilesWithoutRegisteredLoader()
    {
        $loader = new DirectoryLoader(['md' => new MarkdownLoader()]);

        $documents = iterator_to_array($loader->load($this->directory), false);

        // Only markdown files are handled; the .txt and .csv are skipped.
        $this->assertCount(4, $documents);
        $contents = array_map(static fn (DocumentInterface $d) => $d->getContent(), $documents);
        $this->assertNotContains('Plain text notes.', $contents);
    }

    public function testNormalizesExtensionKeysAndMatchesCaseInsensitively()
    {
        $loader = new DirectoryLoader(['MD' => new MarkdownLoader()], recursive: false);

        $documents = iterator_to_array($loader->load($this->directory), false);

        // alpha.md, beta.md and UPPER.MD all match the normalized "md" key.
        $this->assertCount(3, $documents);
        $this->assertContains('Upper', $this->titlesOf($documents));
    }

    public function testForwardsOptionsToSubLoaders()
    {
        $this->fs->dumpFile($this->directory.'/formatted.md', "# Formatted\n\n**bold** text.\n");

        $loader = new DirectoryLoader(['md' => new MarkdownLoader()], recursive: false);

        $documents = iterator_to_array($loader->load($this->directory, ['strip_formatting' => true]), false);

        $formatted = null;
        foreach ($documents as $document) {
            if ('Formatted' === ($document->getMetadata()['title'] ?? null)) {
                $formatted = $document;
                break;
            }
        }

        $this->assertInstanceOf(TextDocument::class, $formatted);
        $this->assertStringNotContainsString('**', $formatted->getContent());
    }

    public function testDispatchPassesRealFilePathsToSubLoader()
    {
        $recording = new class implements LoaderInterface {
            /**
             * @var list<string>
             */
            public array $sources = [];

            public function load(?string $source = null, array $options = []): iterable
            {
                $this->sources[] = (string) $source;

                yield new TextDocument($source ?? '', 'document for '.$source);
            }
        };

        $loader = new DirectoryLoader(['md' => $recording], recursive: false);

        iterator_to_array($loader->load($this->directory), false);

        $basenames = array_map('basename', $recording->sources);
        sort($basenames);
        $this->assertSame(['UPPER.MD', 'alpha.md', 'beta.md'], $basenames);
    }

    public function testConstructorThrowsOnEmptyLoaders()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DirectoryLoader requires at least one extension loader.');

        new DirectoryLoader([]);
    }

    public function testLoadThrowsWhenSourceIsNull()
    {
        $loader = new DirectoryLoader(['md' => new MarkdownLoader()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DirectoryLoader requires an existing directory as source, "null" given.');

        iterator_to_array($loader->load(), false);
    }

    public function testLoadThrowsWhenSourceIsNotADirectory()
    {
        $loader = new DirectoryLoader(['md' => new MarkdownLoader()]);

        $this->expectException(InvalidArgumentException::class);

        iterator_to_array($loader->load(__FILE__), false);
    }

    /**
     * @param list<DocumentInterface> $documents
     *
     * @return list<string>
     */
    private function titlesOf(array $documents): array
    {
        $titles = [];
        foreach ($documents as $document) {
            $title = $document->getMetadata()['title'] ?? null;
            if (null !== $title) {
                $titles[] = $title;
            }
        }

        sort($titles);

        return $titles;
    }
}

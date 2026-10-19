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
use Symfony\AI\Store\Document\EmbeddableDocumentInterface;
use Symfony\AI\Store\Document\Loader\RstLoader;

/**
 * @author Mohamed Senoussi <lesfootix@gmail.com>
 */
final class RstLoaderTest extends TestCase
{
    public function testSplitsSectionsAtHeadings()
    {
        $content = <<<'RST'
            Controllers
            ===========

            A controller is a PHP function.

            The Request Object
            ------------------

            Type-hint the Request argument.
            RST;

        $this->assertSame(['Controllers', 'The Request Object'], $this->titles($content));
    }

    public function testSplitsSectionsOfContentWithWindowsLineEndings()
    {
        $content = "Controllers\r\n===========\r\n\r\nA controller is a PHP function.\r\n\r\nThe Request Object\r\n------------------\r\n\r\nType-hint the Request argument.\r\n";

        $this->assertSame(['Controllers', 'The Request Object'], $this->titles($content));
    }

    public function testIgnoresIndentedLinesInLiteralBlocks()
    {
        $content = <<<'RST'
            Controllers
            ===========

            A controller can be a class::

                class LuckyController
                {
                    public function number(): Response
                    {
                        return new Response('42');
                    }
                }

            The Request Object
            ------------------

            Type-hint the Request argument.
            RST;

        $this->assertSame(['Controllers', 'The Request Object'], $this->titles($content));
    }

    /**
     * @return list<string|null>
     */
    private function titles(string $content): array
    {
        return array_map(
            static fn (EmbeddableDocumentInterface $document): ?string => $document->getMetadata()->getTitle(),
            iterator_to_array((new RstLoader())->loadContent($content, 'controller.rst'), false),
        );
    }
}

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

final class RstLoaderTest extends TestCase
{
    public function testIndentedLinesAreNotDetectedAsHeadings()
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

        $loader = new RstLoader();
        $documents = iterator_to_array($loader->loadContent($content, 'controller.rst'), false);

        $titles = array_map(
            static fn (EmbeddableDocumentInterface $doc): string => $doc->getMetadata()->getTitle() ?? '',
            $documents,
        );

        $this->assertSame(['Controllers', 'The Request Object'], $titles);
        $this->assertStringContainsString("return new Response('42');\n        }\n    }", $documents[0]->getContent());
    }
}

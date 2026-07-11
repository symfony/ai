<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\E2E;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HomeTest extends E2ETestCase
{
    public function testAllUseCasesAreLinked()
    {
        $crawler = $this->crawler();

        $this->assertSelectorTextContains('h1', 'Symfony AI');
        $this->assertSelectorCount(10, '.card');

        $links = $crawler->filter('.card a.btn')->each(static fn ($link) => $link->attr('href'));

        $this->assertSame([
            '/youtube', '/recipe', '/wikipedia', '/blog', '/movies',
            '/speech', '/video', '/crop', '/stream', '/document',
        ], $links);
    }

    public function testProfilerToolbarIsAvailable()
    {
        $this->client->waitForVisibility('.sf-toolbar');

        $this->assertSelectorExists('.sf-toolbar-block-config');
    }

    protected function requiredApiKeys(): array
    {
        return [];
    }
}

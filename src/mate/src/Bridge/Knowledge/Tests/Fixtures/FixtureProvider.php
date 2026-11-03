<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Knowledge\Tests\Fixtures;

use Symfony\AI\Mate\Bridge\Knowledge\Provider\DocsProviderInterface;

/**
 * Test provider that points at a pre-cloned fixture directory instead of a real git repo.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class FixtureProvider implements DocsProviderInterface
{
    public function __construct(
        private string $fixtureDocsDir,
        private string $name = 'fixture',
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return 'Fixture Documentation';
    }

    public function getDescription(): string
    {
        return 'In-tree RST fixtures for Knowledge bridge tests.';
    }

    public function getFormat(): string
    {
        return 'rst';
    }

    public function sync(string $cacheDir): string
    {
        return $this->fixtureDocsDir.'/index.rst';
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Knowledge;

use Symfony\AI\Mate\Bridge\Knowledge\Provider\DocsProviderInterface;
use Symfony\AI\Mate\Bridge\Knowledge\Service\GitFetcher;

/**
 * Exposes the official Symfony documentation (https://github.com/symfony/symfony-docs)
 * as a knowledge provider.
 *
 * Only registered when the Knowledge bridge is installed (see config.php).
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SymfonyDocsProvider implements DocsProviderInterface
{
    public function __construct(
        private GitFetcher $fetcher,
        private string $repositoryUrl = 'https://github.com/symfony/symfony-docs.git',
        private string $branch = '7.3',
    ) {
    }

    public function getName(): string
    {
        return 'symfony';
    }

    public function getTitle(): string
    {
        return 'Symfony Documentation';
    }

    public function getDescription(): string
    {
        return 'Official Symfony framework documentation, branch '.$this->branch.'.';
    }

    public function getFormat(): string
    {
        return 'rst';
    }

    public function sync(string $cacheDir): string
    {
        $repoDir = rtrim($cacheDir, '/').'/docs';
        $this->fetcher->fetch($this->repositoryUrl, $this->branch, $repoDir);

        return $repoDir.'/index.rst';
    }
}

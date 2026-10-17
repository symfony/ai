<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Writes the platform bridges of symfony/ai and their number of downloads to a local
 * file, which the platform bridges page reads. Scheduled every morning, see .upsun/config.yaml.
 *
 * Both GitHub and Packagist are read without any token.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
#[AsCommand(
    name: 'app:platform-bridges:update',
    description: 'Refresh the list of platform bridges and their downloads on Packagist',
)]
final readonly class PlatformBridgesUpdateCommand
{
    private const string SPLIT_CONFIGURATION_URL = 'https://raw.githubusercontent.com/symfony/ai/main/splitsh.json';
    private const string BRIDGES_DIRECTORY = 'src/platform/src/Bridge/';
    private const array OPTIONS = [
        'headers' => ['User-Agent' => 'ai.symfony.com (https://ai.symfony.com)'],
        'timeout' => 10,
        'max_duration' => 30,
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $dataPath,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $packages = $this->fetchPackages($io);
        if (null === $packages) {
            return Command::FAILURE;
        }

        $downloads = $this->fetchDownloads($packages);

        $bridges = [];
        foreach ($packages as $directory => $package) {
            $bridges[$directory] = ['package' => $package, 'downloads' => $downloads[$directory]];
        }

        (new Filesystem())->dumpFile($this->dataPath, json_encode([
            'updatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'bridges' => $bridges,
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n");

        $unknown = array_keys(array_filter($downloads, static fn (?int $count): bool => null === $count));
        if ([] !== $unknown) {
            $io->note(\sprintf('No download count for: %s.', implode(', ', $unknown)));
        }

        $io->success(\sprintf('Updated %d platform bridge(s) in %s.', \count($bridges), $this->dataPath));

        return Command::SUCCESS;
    }

    /**
     * Reads splitsh.json, which lists the packages split from the monorepo, and keeps
     * the ones living directly in the platform bridges directory.
     *
     * @return array<string, string>|null package names indexed by bridge directory, null when the list cannot be read
     */
    private function fetchPackages(SymfonyStyle $io): ?array
    {
        try {
            $splits = $this->httpClient->request('GET', self::SPLIT_CONFIGURATION_URL, self::OPTIONS)->toArray();
        } catch (ExceptionInterface $exception) {
            $io->error(\sprintf('Cannot read %s, the data file is left untouched: %s', self::SPLIT_CONFIGURATION_URL, $exception->getMessage()));

            return null;
        }

        $packages = [];
        foreach (\is_array($splits['subtrees'] ?? null) ? $splits['subtrees'] : [] as $split => $path) {
            $directory = \is_string($path) && str_starts_with($path, self::BRIDGES_DIRECTORY) ? substr($path, \strlen(self::BRIDGES_DIRECTORY)) : '';
            if ('' !== $directory && !str_contains($directory, '/')) {
                $packages[$directory] = 'symfony/'.$split;
            }
        }

        if ([] === $packages) {
            $io->error('No platform bridge found in splitsh.json, the data file is left untouched.');

            return null;
        }

        ksort($packages);

        return $packages;
    }

    /**
     * A package missing from Packagist, or failing to answer, keeps the count of the
     * previous update, if any.
     *
     * @param array<string, string> $packages indexed by bridge directory
     *
     * @return array<string, int|null> indexed by bridge directory
     */
    private function fetchDownloads(array $packages): array
    {
        $responses = [];
        foreach ($packages as $directory => $package) {
            $responses[$directory] = $this->httpClient->request('GET', \sprintf('https://packagist.org/packages/%s/stats.json', $package), self::OPTIONS);
        }

        $previous = is_file($this->dataPath) ? json_decode((string) file_get_contents($this->dataPath), true) : null;

        $downloads = [];
        foreach ($responses as $directory => $response) {
            try {
                $count = $response->toArray()['downloads']['total'] ?? null;
            } catch (ExceptionInterface) {
                $count = null;
            }

            $previousCount = $previous['bridges'][$directory]['downloads'] ?? null;
            $downloads[$directory] = \is_int($count) ? $count : (\is_int($previousCount) ? $previousCount : null);
        }

        return $downloads;
    }
}

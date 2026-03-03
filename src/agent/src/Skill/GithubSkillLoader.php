<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Skill;

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Skill\Validation\SkillValidator;
use Symfony\AI\Agent\Skill\Validation\SkillValidatorInterface;
use Symfony\Component\String\UnicodeString;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Loads Agent Skills from GitHub repositories using the GitHub Contents API.
 *
 * Each repository entry must contain:
 *   - repository: "owner/repo" format
 *   - path: (optional) subdirectory within the repo (default: "")
 *   - branch: (optional) branch name (default: "main")
 *   - token: (optional) GitHub personal access token for private repositories
 *
 * @see https://docs.github.com/en/rest/repos/contents
 * @see https://agentskills.io/specification
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class GithubSkillLoader implements SkillLoaderInterface
{
    private const GITHUB_API_BASE = 'https://api.github.com';
    private const GITHUB_RAW_BASE = 'https://raw.githubusercontent.com';

    /**
     * @param array<int, array{repository: string, path?: string, branch?: string, token?: string|null}> $repositories
     */
    public function __construct(
        private readonly array $repositories,
        private readonly HttpClientInterface $httpClient,
        private readonly SkillParserInterface $parser = new SkillParser(),
        private readonly SkillValidatorInterface $skillValidator = new SkillValidator(),
        private readonly string $githubVersion = '2022-11-28',
    ) {
    }

    public function loadSkill(string $name): ?SkillInterface
    {
        foreach ($this->repositories as $repository) {
            $config = $this->normalizeRepository($repository);

            try {
                $content = $this->fetchRawFile($config, $this->buildSkillPath($config['path'], $name, 'SKILL.md'));
            } catch (\Throwable) {
                continue;
            }

            try {
                $skill = $this->parser->parseFromContent(
                    $content,
                    \sprintf('github://%s/%s', $config['repository'], $name),
                    $this->createScriptsLoader($config, $name),
                    $this->createReferencesLoader($config, $name),
                    $this->createAssetsLoader($config, $name),
                );

                if ($skill->getName() !== $name) {
                    continue;
                }

                $validation = $this->skillValidator->validate($skill);

                if (!$validation->isValid()) {
                    throw new InvalidArgumentException(\sprintf('The "%s" is not a valid skill.', $skill->getName()));
                }

                return $skill;
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    public function loadSkills(): array
    {
        $skills = [];

        foreach ($this->repositories as $repository) {
            $config = $this->normalizeRepository($repository);

            try {
                $directories = $this->listSkillDirectories($config);
            } catch (\Throwable) {
                continue;
            }

            foreach ($directories as $skillName) {
                try {
                    $content = $this->fetchRawFile($config, $this->buildSkillPath($config['path'], $skillName, 'SKILL.md'));

                    $skill = $this->parser->parseFromContent(
                        $content,
                        \sprintf('github://%s/%s', $config['repository'], $skillName),
                        $this->createScriptsLoader($config, $skillName),
                        $this->createReferencesLoader($config, $skillName),
                        $this->createAssetsLoader($config, $skillName),
                    );

                    $validation = $this->skillValidator->validate($skill);

                    if (!$validation->isValid()) {
                        continue;
                    }

                    $skills[$skill->getName()] = $skill;
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return $skills;
    }

    public function discoverMetadata(): array
    {
        $metadata = [];

        foreach ($this->repositories as $repository) {
            $config = $this->normalizeRepository($repository);

            try {
                $directories = $this->listSkillDirectories($config);
            } catch (\Throwable) {
                continue;
            }

            foreach ($directories as $skillName) {
                try {
                    $content = $this->fetchRawFile($config, $this->buildSkillPath($config['path'], $skillName, 'SKILL.md'));
                    $skillMetadata = $this->parser->parseMetadataFromContent(
                        $content,
                        \sprintf('github://%s/%s', $config['repository'], $skillName),
                    );

                    $metadata[$skillMetadata->getName()] = $skillMetadata;
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return $metadata;
    }

    /**
     * Lists skill directories within the configured path of a repository.
     *
     * @param array{repository: string, path: string, branch: string, token: string|null} $config
     *
     * @return string[] Skill directory names
     */
    private function listSkillDirectories(array $config): array
    {
        [$owner, $repo] = explode('/', $config['repository'], 2);
        $apiPath = '' !== $config['path'] ? '/'.$config['path'] : '';

        $url = \sprintf('%s/repos/%s/%s/contents%s', self::GITHUB_API_BASE, $owner, $repo, $apiPath);

        $response = $this->httpClient->request('GET', $url, [
            'headers' => $this->buildHeaders($config['token']),
            'query' => ['ref' => $config['branch']],
        ]);

        if (200 !== $response->getStatusCode()) {
            return [];
        }

        $entries = $response->toArray();
        $directories = [];

        foreach ($entries as $entry) {
            if ('dir' === ($entry['type'] ?? null) && \is_string($entry['name'] ?? null)) {
                $directories[] = $entry['name'];
            }
        }

        return $directories;
    }

    /**
     * Fetches raw file content from GitHub.
     *
     * @param array{repository: string, path: string, branch: string, token: string|null} $config
     */
    private function fetchRawFile(array $config, string $filePath): string
    {
        if (null !== $config['token']) {
            return $this->fetchRawFileViaApi($config, $filePath);
        }

        [$owner, $repo] = explode('/', $config['repository'], 2);

        $url = \sprintf('%s/%s/%s/%s/%s', self::GITHUB_RAW_BASE, $owner, $repo, $config['branch'], $filePath);

        $response = $this->httpClient->request('GET', $url);

        if (200 !== $response->getStatusCode()) {
            throw new InvalidArgumentException(\sprintf('Unable to fetch "%s" from GitHub (HTTP %d).', $filePath, $response->getStatusCode()));
        }

        return $response->getContent();
    }

    /**
     * Fetches raw file content via the GitHub API (needed for private repos).
     *
     * @param array{repository: string, path: string, branch: string, token: string|null} $config
     */
    private function fetchRawFileViaApi(array $config, string $filePath): string
    {
        [$owner, $repo] = explode('/', $config['repository'], 2);

        $url = \sprintf('%s/repos/%s/%s/contents/%s', self::GITHUB_API_BASE, $owner, $repo, $filePath);

        $response = $this->httpClient->request('GET', $url, [
            'headers' => array_merge($this->buildHeaders($config['token']), [
                'Accept' => 'application/vnd.github.raw+json',
            ]),
            'query' => [
                'ref' => $config['branch'],
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new InvalidArgumentException(\sprintf('Unable to fetch "%s" from GitHub API (HTTP %d).', $filePath, $response->getStatusCode()));
        }

        return $response->getContent();
    }

    /**
     * @param array{repository: string, path: string, branch: string, token: string|null} $config
     */
    private function createScriptsLoader(array $config, string $skillName): \Closure
    {
        return fn (string $script): string => $this->fetchRawFile($config, $this->buildSkillPath($config['path'], $skillName, 'scripts/'.$script));
    }

    /**
     * @param array{repository: string, path: string, branch: string, token: string|null} $config
     */
    private function createReferencesLoader(array $config, string $skillName): \Closure
    {
        return function (string $reference) use ($config, $skillName): ?string {
            try {
                return $this->fetchRawFile($config, $this->buildSkillPath($config['path'], $skillName, 'references/'.$reference));
            } catch (\Throwable) {
                return null;
            }
        };
    }

    /**
     * @param array{repository: string, path: string, branch: string, token: string|null} $config
     */
    private function createAssetsLoader(array $config, string $skillName): \Closure
    {
        return function (string $asset) use ($config, $skillName): ?string {
            try {
                return $this->fetchRawFile($config, $this->buildSkillPath($config['path'], $skillName, 'assets/'.$asset));
            } catch (\Throwable) {
                return null;
            }
        };
    }

    private function buildSkillPath(string $basePath, string $skillName, string $file): string
    {
        $parts = array_filter([$basePath, $skillName, $file], static fn (string $p): bool => '' !== $p);

        return implode('/', $parts);
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(?string $token): array
    {
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => $this->githubVersion,
        ];

        if (null !== $token) {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        return $headers;
    }

    /**
     * @param array{repository: string, path?: string, branch?: string, token?: string|null} $repository
     *
     * @return array{repository: string, path: string, branch: string, token: string|null}
     */
    private function normalizeRepository(array $repository): array
    {
        $repo = new UnicodeString($repository['repository']);

        if ($repo->startsWith('https://github.com/')) {
            $repo = $repo->slice((new UnicodeString('https://github.com/'))->length());
        }

        $repo = $repo->trim('/');
        if ($repo->endsWith('.git')) {
            $repo = $repo->slice(0, -4);
        }

        if (!$repo->containsAny('/')) {
            throw new InvalidArgumentException(\sprintf('Invalid GitHub repository format "%s". Expected "owner/repo".', $repo));
        }

        return [
            'repository' => $repo,
            'path' => $repository['path'] ?? '',
            'branch' => $repository['branch'] ?? 'main',
            'token' => $repository['token'] ?? null,
        ];
    }
}

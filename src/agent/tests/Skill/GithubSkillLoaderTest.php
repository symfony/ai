<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Skill;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Skill\GithubSkillLoader;
use Symfony\AI\Agent\Skill\SkillParser;
use Symfony\AI\Agent\Skill\Validation\SkillValidator;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GithubSkillLoaderTest extends TestCase
{
    private const SKILL_CONTENT = <<<'MD'
---
name: test-skill
description: A test skill loaded from GitHub
---
Do something useful.
MD;

    public function testDiscoverMetadataListsSkillDirectories()
    {
        $responses = [
            // List directory contents
            new MockResponse(json_encode([
                ['type' => 'dir', 'name' => 'test-skill'],
                ['type' => 'dir', 'name' => 'other-skill'],
                ['type' => 'file', 'name' => 'README.md'],
            ])),
            // Fetch test-skill/SKILL.md
            new MockResponse(self::SKILL_CONTENT),
            // Fetch other-skill/SKILL.md
            new MockResponse(<<<'MD'
---
name: other-skill
description: Another skill from GitHub
---
Other instructions.
MD),
        ];

        $loader = $this->createLoader($responses);
        $metadata = $loader->discoverMetadata();

        $this->assertCount(2, $metadata);
        $this->assertArrayHasKey('test-skill', $metadata);
        $this->assertArrayHasKey('other-skill', $metadata);
        $this->assertSame('A test skill loaded from GitHub', $metadata['test-skill']->getDescription());
    }

    public function testLoadSkillReturnsMatchingSkill()
    {
        $responses = [
            // Fetch test-skill/SKILL.md via raw URL
            new MockResponse(self::SKILL_CONTENT),
        ];

        $loader = $this->createLoader($responses);
        $skill = $loader->loadSkill('test-skill');

        $this->assertNotNull($skill);
        $this->assertSame('test-skill', $skill->getName());
        $this->assertSame('A test skill loaded from GitHub', $skill->getDescription());
        $this->assertSame('Do something useful.', $skill->getBody());
    }

    public function testLoadSkillReturnsNullWhenNotFound()
    {
        $responses = [
            // 404 from GitHub
            new MockResponse('Not Found', ['http_code' => 404]),
        ];

        $loader = $this->createLoader($responses);
        $skill = $loader->loadSkill('nonexistent-skill');

        $this->assertNull($skill);
    }

    public function testLoadSkillsReturnsAllValidSkills()
    {
        $responses = [
            // List directory
            new MockResponse(json_encode([
                ['type' => 'dir', 'name' => 'test-skill'],
                ['type' => 'dir', 'name' => 'broken-skill'],
            ])),
            // test-skill/SKILL.md
            new MockResponse(self::SKILL_CONTENT),
            // broken-skill/SKILL.md - malformed
            new MockResponse('Not valid YAML frontmatter'),
        ];

        $loader = $this->createLoader($responses);
        $skills = $loader->loadSkills();

        $this->assertCount(1, $skills);
        $this->assertArrayHasKey('test-skill', $skills);
    }

    public function testLoadSkillsReturnsEmptyWhenApiReturnsError()
    {
        $responses = [
            new MockResponse('Unauthorized', ['http_code' => 403]),
        ];

        $loader = $this->createLoader($responses);
        $skills = $loader->loadSkills();

        $this->assertSame([], $skills);
    }

    public function testDiscoverMetadataSkipsInvalidSkills()
    {
        $responses = [
            new MockResponse(json_encode([
                ['type' => 'dir', 'name' => 'valid-skill'],
                ['type' => 'dir', 'name' => 'broken'],
            ])),
            // valid-skill/SKILL.md
            new MockResponse(self::SKILL_CONTENT),
            // broken/SKILL.md - 404
            new MockResponse('Not Found', ['http_code' => 404]),
        ];

        $loader = $this->createLoader($responses);
        $metadata = $loader->discoverMetadata();

        $this->assertCount(1, $metadata);
        $this->assertArrayHasKey('test-skill', $metadata);
    }

    public function testLoadSkillWithGithubUrl()
    {
        $responses = [
            new MockResponse(self::SKILL_CONTENT),
        ];

        $loader = $this->createLoader($responses, 'https://github.com/symfony/ai-skills');
        $skill = $loader->loadSkill('test-skill');

        $this->assertNotNull($skill);
        $this->assertSame('test-skill', $skill->getName());
    }

    public function testLoadSkillWithAuthentication()
    {
        $requestHeaders = [];
        $responses = [
            new MockResponse(self::SKILL_CONTENT, ['http_code' => 200]),
        ];

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestHeaders, &$responses): MockResponse {
            $requestHeaders = $options['headers'] ?? [];
            static $index = 0;

            return $responses[$index++];
        });

        $loader = new GithubSkillLoader(
            [['repository' => 'owner/private-repo', 'token' => 'ghp_test_token']],
            $httpClient,
            new SkillParser(),
            new SkillValidator(),
        );

        $skill = $loader->loadSkill('test-skill');

        $this->assertNotNull($skill);
        // With a token, it uses the API endpoint (not raw URL)
        $this->assertContains('Authorization: Bearer ghp_test_token', $requestHeaders);
    }

    public function testLoadSkillWithCustomBranchAndPath()
    {
        $capturedUrl = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$capturedUrl): MockResponse {
            $capturedUrl = $url;

            return new MockResponse(self::SKILL_CONTENT);
        });

        $loader = new GithubSkillLoader(
            [['repository' => 'owner/repo', 'path' => 'my-skills', 'branch' => 'develop']],
            $httpClient,
            new SkillParser(),
            new SkillValidator(),
        );

        $loader->loadSkill('test-skill');

        $this->assertNotNull($capturedUrl);
        $this->assertStringContainsString('/develop/', $capturedUrl);
        $this->assertStringContainsString('my-skills/test-skill/SKILL.md', $capturedUrl);
    }

    public function testLoadReferenceFromGithub()
    {
        $callIndex = 0;
        $httpClient = new MockHttpClient(static function () use (&$callIndex): MockResponse {
            ++$callIndex;
            if (1 === $callIndex) {
                return new MockResponse(self::SKILL_CONTENT);
            }

            return new MockResponse('Reference content from GitHub');
        });

        $loader = new GithubSkillLoader(
            [['repository' => 'owner/repo']],
            $httpClient,
            new SkillParser(),
            new SkillValidator(),
        );

        $skill = $loader->loadSkill('test-skill');

        $this->assertNotNull($skill);
        $this->assertSame('Reference content from GitHub', $skill->loadReference('guide.md'));
    }

    public function testLoadAssetReturnsNullOn404()
    {
        $callIndex = 0;
        $httpClient = new MockHttpClient(static function () use (&$callIndex): MockResponse {
            ++$callIndex;
            if (1 === $callIndex) {
                return new MockResponse(self::SKILL_CONTENT);
            }

            return new MockResponse('Not Found', ['http_code' => 404]);
        });

        $loader = new GithubSkillLoader(
            [['repository' => 'owner/repo']],
            $httpClient,
            new SkillParser(),
            new SkillValidator(),
        );

        $skill = $loader->loadSkill('test-skill');

        $this->assertNotNull($skill);
        $this->assertNull($skill->loadAsset('missing.png'));
    }

    public function testDiscoverMetadataFromMultipleRepositories()
    {
        $callIndex = 0;
        $httpClient = new MockHttpClient(static function () use (&$callIndex): MockResponse {
            ++$callIndex;

            return match ($callIndex) {
                // Repo 1: list dirs
                1 => new MockResponse(json_encode([['type' => 'dir', 'name' => 'skill-a']])),
                // Repo 1: fetch skill-a/SKILL.md
                2 => new MockResponse("---\nname: skill-a\ndescription: Skill from repo one\n---\nBody A."),
                // Repo 2: list dirs
                3 => new MockResponse(json_encode([['type' => 'dir', 'name' => 'skill-b']])),
                // Repo 2: fetch skill-b/SKILL.md
                4 => new MockResponse("---\nname: skill-b\ndescription: Skill from repo two\n---\nBody B."),
                default => new MockResponse('', ['http_code' => 404]),
            };
        });

        $loader = new GithubSkillLoader(
            [
                ['repository' => 'owner/repo-one'],
                ['repository' => 'owner/repo-two'],
            ],
            $httpClient,
            new SkillParser(),
            new SkillValidator(),
        );

        $metadata = $loader->discoverMetadata();

        $this->assertCount(2, $metadata);
        $this->assertArrayHasKey('skill-a', $metadata);
        $this->assertArrayHasKey('skill-b', $metadata);
    }

    /**
     * @param MockResponse[] $responses
     */
    private function createLoader(array $responses, string $repository = 'owner/repo'): GithubSkillLoader
    {
        return new GithubSkillLoader(
            [['repository' => $repository]],
            new MockHttpClient($responses),
            new SkillParser(),
            new SkillValidator(),
        );
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Capability;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Capability\DotenvTool;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DotenvToolTest extends TestCase
{
    private string $projectDir;

    /**
     * @var list<string>
     */
    private array $envKeysTouched = [];

    /**
     * @var list<string>
     */
    private array $filesWritten = [];

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/mate_dotenv_test_'.uniqid();
        mkdir($this->projectDir);
        $this->filesWritten = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->filesWritten as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        rmdir($this->projectDir);

        foreach ($this->envKeysTouched as $key) {
            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);
        }
        $this->envKeysTouched = [];
    }

    public function testListsVariableDeclaredInBaseEnvFileAndResolvedFromIt()
    {
        $this->writeFile('.env', "DATABASE_URL=mysql://user:pass@127.0.0.1/app\n");
        $this->setRuntimeEnv('DATABASE_URL', 'mysql://user:pass@127.0.0.1/app');

        $result = $this->decode((new DotenvTool($this->projectDir))->check());

        $variable = $this->findVariable($result, 'DATABASE_URL');
        $this->assertSame(['.env'], $variable['declared_in']);
        $this->assertTrue($variable['resolved']);
        $this->assertSame('file', $variable['state']);
        $this->assertSame(\strlen('mysql://user:pass@127.0.0.1/app'), $variable['length']);
    }

    public function testNeverReturnsTheRawResolvedValue()
    {
        $secret = 'sk-live-'.bin2hex(random_bytes(16));
        $this->writeFile('.env', "API_KEY=placeholder\n");
        $this->setRuntimeEnv('API_KEY', $secret);

        $json = (new DotenvTool($this->projectDir))->check();

        $this->assertStringNotContainsString($secret, $json);
        $this->assertStringNotContainsString('placeholder'.\PHP_EOL, $json); // sanity: file was actually read
    }

    public function testDeclaredEmptyInFileWhenTheWinningFileAssignsAnEmptyValue()
    {
        $this->writeFile('.env', "SOME_KEY=\n");

        $result = $this->decode((new DotenvTool($this->projectDir))->check());

        $variable = $this->findVariable($result, 'SOME_KEY');
        $this->assertSame(['.env'], $variable['declared_in']);
        $this->assertFalse($variable['resolved']);
        $this->assertSame('declared_empty_in_file', $variable['state']);
        $this->assertSame(0, $variable['length']);
        $this->assertNull($variable['preview']);
    }

    public function testDeclaredNotResolvedInThisProcessWhenFileValueIsNeverExported()
    {
        $this->writeFile('.env', "SOME_KEY=a-real-non-empty-value\n");

        $result = $this->decode((new DotenvTool($this->projectDir))->check());

        $variable = $this->findVariable($result, 'SOME_KEY');
        $this->assertSame(['.env'], $variable['declared_in']);
        $this->assertFalse($variable['resolved']);
        $this->assertSame('declared_not_resolved_in_this_process', $variable['state']);
    }

    public function testAmbientOverrideWhenResolvedValueDiffersFromEveryDeclaredFileValue()
    {
        $this->writeFile('.env', "API_KEY=placeholder-from-env\n");
        $this->setRuntimeEnv('API_KEY', 'a-totally-different-real-secret');

        $result = $this->decode((new DotenvTool($this->projectDir))->check());

        $variable = $this->findVariable($result, 'API_KEY');
        $this->assertSame(['.env'], $variable['declared_in']);
        $this->assertTrue($variable['resolved']);
        $this->assertSame('ambient_override', $variable['state']);
    }

    public function testAmbientOnlyForAKeyLookupNotDeclaredInAnyFile()
    {
        $this->writeFile('.env', "APP_ENV=dev\n");
        $this->setRuntimeEnv('HUGGINGFACE_API_KEY', 'hf_totally-real-secret-value');

        $result = $this->decode((new DotenvTool($this->projectDir))->check(key: 'HUGGINGFACE_API_KEY'));

        $this->assertCount(1, $result['variables']);
        $variable = $result['variables'][0];
        $this->assertSame('HUGGINGFACE_API_KEY', $variable['key']);
        $this->assertSame([], $variable['declared_in']);
        $this->assertTrue($variable['resolved']);
        $this->assertSame('ambient_only', $variable['state']);
        $this->assertStringNotContainsString('hf_totally-real-secret-value', ResponseEncoder::encode($result));
    }

    public function testNotSetForAKeyLookupThatResolvesNowhere()
    {
        $this->writeFile('.env', "APP_ENV=dev\n");

        $result = $this->decode((new DotenvTool($this->projectDir))->check(key: 'TOTALLY_UNKNOWN_VAR_XYZ'));

        $variable = $result['variables'][0];
        $this->assertSame([], $variable['declared_in']);
        $this->assertFalse($variable['resolved']);
        $this->assertSame('not_set', $variable['state']);
    }

    public function testLaterFileOverridesEarlierFileForTheSameKeyMatchingSymfonyPrecedence()
    {
        $this->writeFile('.env', "APP_ENV=dev\nSHARED_KEY=from-base\n");
        $this->writeFile('.env.local', "SHARED_KEY=from-local\n");
        $this->setRuntimeEnv('APP_ENV', 'dev');
        $this->setRuntimeEnv('SHARED_KEY', 'from-local');

        $result = $this->decode((new DotenvTool($this->projectDir))->check());

        $variable = $this->findVariable($result, 'SHARED_KEY');
        $this->assertSame(['.env', '.env.local'], $variable['declared_in']);
        $this->assertSame('file', $variable['state']);
    }

    public function testEnvLocalIsSkippedWhenAppEnvIsTest()
    {
        $this->writeFile('.env', "APP_ENV=test\nSHARED_KEY=from-base\n");
        $this->writeFile('.env.local', "SHARED_KEY=from-local\n");
        $this->setRuntimeEnv('APP_ENV', 'test');

        $result = $this->decode((new DotenvTool($this->projectDir))->check());

        $file = $this->findFile($result, '.env.local');
        $this->assertTrue($file['exists']); // the file exists on disk...
        $variable = $this->findVariable($result, 'SHARED_KEY');
        $this->assertSame(['.env'], $variable['declared_in']); // ...but its declarations are not consulted

        $envTestFile = $this->findFile($result, '.env.test');
        $this->assertFalse($envTestFile['exists']);
    }

    public function testFallsBackToEnvDistWhenEnvFileIsAbsent()
    {
        $this->writeFile('.env.dist', "APP_ENV=dev\nSOME_KEY=from-dist\n");

        $result = $this->decode((new DotenvTool($this->projectDir))->check());

        $file = $this->findFile($result, '.env.dist');
        $this->assertTrue($file['exists']);
        $variable = $this->findVariable($result, 'SOME_KEY');
        $this->assertSame(['.env.dist'], $variable['declared_in']);
    }

    public function testAppSpecificEnvFileIsLoadedAfterLocal()
    {
        $this->writeFile('.env', "APP_ENV=prod\n");
        $this->writeFile('.env.prod', "PROD_ONLY_KEY=value\n");
        $this->setRuntimeEnv('APP_ENV', 'prod');

        $result = $this->decode((new DotenvTool($this->projectDir))->check());

        $variable = $this->findVariable($result, 'PROD_ONLY_KEY');
        $this->assertSame(['.env.prod'], $variable['declared_in']);
    }

    public function testUnparseableFileIsReportedButDoesNotBreakDiscovery()
    {
        $this->writeFile('.env', "APP_ENV=dev\nGOOD_KEY=fine\n\"unterminated\n");

        $result = $this->decode((new DotenvTool($this->projectDir))->check());

        $file = $this->findFile($result, '.env');
        $this->assertFalse($file['parseable']);
    }

    public function testMasksShortValuesWithAFixedWidthMaskRegardlessOfLength()
    {
        $this->writeFile('.env', "A=x\nB=xy\n");
        $this->setRuntimeEnv('A', 'x');
        $this->setRuntimeEnv('B', 'xy');

        $result = $this->decode((new DotenvTool($this->projectDir))->check());

        $this->assertSame('**', $this->findVariable($result, 'A')['preview']);
        $this->assertSame('**', $this->findVariable($result, 'B')['preview']);
    }

    public function testMasksLongerValuesWithFirstAndLastCharacterOnly()
    {
        $this->writeFile('.env', "PLAIN_LOOKING=dev-value\nSECRET_KEY=abcdefghijklmnop\n");
        $this->setRuntimeEnv('PLAIN_LOOKING', 'dev-value');
        $this->setRuntimeEnv('SECRET_KEY', 'abcdefghijklmnop');

        $result = $this->decode((new DotenvTool($this->projectDir))->check());

        // Masking is uniform: a plain-looking key name gets exactly the same treatment as a
        // secret-shaped key name. Nothing in the preview reveals more than first+last character.
        $this->assertSame('d***e', $this->findVariable($result, 'PLAIN_LOOKING')['preview']);
        $this->assertSame('a***p', $this->findVariable($result, 'SECRET_KEY')['preview']);
    }

    public function testFlagsAnObviousPlaceholderValue()
    {
        $this->writeFile('.env', "API_KEY=changeme\n");
        $this->setRuntimeEnv('API_KEY', 'changeme');

        $result = $this->decode((new DotenvTool($this->projectDir))->check());

        $this->assertTrue($this->findVariable($result, 'API_KEY')['looks_like_placeholder']);
    }

    public function testDoesNotFlagARealLookingValueAsPlaceholder()
    {
        $this->writeFile('.env', "API_KEY=abcdefghijklmnop\n");
        $this->setRuntimeEnv('API_KEY', 'abcdefghijklmnop');

        $result = $this->decode((new DotenvTool($this->projectDir))->check());

        $this->assertFalse($this->findVariable($result, 'API_KEY')['looks_like_placeholder']);
    }

    public function testReportsCountAndTruncatesWhenOverTheLimit()
    {
        $lines = [];
        for ($i = 0; $i < 5; ++$i) {
            $lines[] = "KEY_{$i}=value{$i}";
        }
        $this->writeFile('.env', implode("\n", $lines)."\n");

        $result = $this->decode((new DotenvTool($this->projectDir))->check(limit: 2));

        $this->assertSame(5, $result['count']);
        $this->assertTrue($result['truncated']);
        $this->assertCount(2, $result['variables']);
    }

    private function writeFile(string $relativePath, string $contents): void
    {
        $path = $this->projectDir.'/'.$relativePath;
        file_put_contents($path, $contents);
        $this->filesWritten[] = $path;
    }

    private function setRuntimeEnv(string $key, string $value): void
    {
        $_SERVER[$key] = $value;
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
        $this->envKeysTouched[] = $key;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        $decoded = ResponseEncoder::decode($json);
        \assert(\is_array($decoded));

        return $decoded;
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function findVariable(array $result, string $key): array
    {
        foreach ($result['variables'] as $variable) {
            if ($variable['key'] === $key) {
                return $variable;
            }
        }

        $this->fail(\sprintf('Variable "%s" not found in result.', $key));
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function findFile(array $result, string $file): array
    {
        foreach ($result['files'] as $entry) {
            if ($entry['file'] === $file) {
                return $entry;
            }
        }

        $this->fail(\sprintf('File "%s" not found in result.', $file));
    }
}

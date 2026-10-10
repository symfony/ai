<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Command;

use App\Command\PlatformBridgesUpdateCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PlatformBridgesUpdateCommandTest extends TestCase
{
    private const SPLITS = '{"subtrees": {"ai-agent": {"prefixes": []}, "ai-brave-tool": "src/agent/src/Bridge/Brave", "ai-open-ai-platform": "src/platform/src/Bridge/OpenAi", "ai-ollama-platform": "src/platform/src/Bridge/Ollama", "ai-venice-platform": "src/platform/src/Bridge/Venice"}}';

    private string $dataPath;

    protected function setUp(): void
    {
        $this->dataPath = sys_get_temp_dir().'/platform_bridges_'.bin2hex(random_bytes(4)).'.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->dataPath);
    }

    public function testUpdateWritesThePlatformBridgesAndTheirDownloads()
    {
        $tester = $this->tester([
            'symfony/ai-open-ai-platform' => new MockResponse('{"downloads": {"total": 734471, "monthly": 136734, "daily": 6105}}'),
            'symfony/ai-ollama-platform' => new MockResponse('{"downloads": {"total": 210380}}'),
            'symfony/ai-venice-platform' => new MockResponse('{"downloads": {"total": 3}}'),
        ]);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertSame([
            'Ollama' => ['package' => 'symfony/ai-ollama-platform', 'downloads' => 210380],
            'OpenAi' => ['package' => 'symfony/ai-open-ai-platform', 'downloads' => 734471],
            'Venice' => ['package' => 'symfony/ai-venice-platform', 'downloads' => 3],
        ], $this->data()['bridges']);
    }

    public function testPackagesPackagistCannotTellAboutKeepTheirPreviousDownloads()
    {
        file_put_contents($this->dataPath, '{"updatedAt": "2026-09-29T05:00:00+00:00", "bridges": {"Ollama": {"package": "symfony/ai-ollama-platform", "downloads": 200000}}}');

        $tester = $this->tester([
            'symfony/ai-open-ai-platform' => new MockResponse('{"downloads": {"total": 734471}}'),
            'symfony/ai-ollama-platform' => new MockResponse('Server Error', ['http_code' => 500]),
            'symfony/ai-venice-platform' => new MockResponse('{"status": "error", "message": "Package not found"}', ['http_code' => 404]),
        ]);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('No download count for: Venice.', $tester->getDisplay());

        $bridges = $this->data()['bridges'];
        $this->assertSame(734471, $bridges['OpenAi']['downloads']);
        $this->assertSame(200000, $bridges['Ollama']['downloads'], 'A failing package keeps its previous count.');
        $this->assertNull($bridges['Venice']['downloads'], 'A package not on Packagist yet is listed anyway.');
    }

    public function testTheDataFileIsLeftUntouchedWhenTheBridgesCannotBeListed()
    {
        file_put_contents($this->dataPath, '{"previous": true}');

        $tester = $this->tester([], new MockResponse('', ['http_code' => 503]));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('left untouched', $tester->getDisplay());
        $this->assertSame('{"previous": true}', file_get_contents($this->dataPath));
    }

    public function testTheDataFileIsLeftUntouchedWhenGitHubCannotBeReached()
    {
        $tester = $this->tester([], new MockResponse((static function (): \Generator {
            yield new TransportException('Could not resolve host');
        })()));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertFileDoesNotExist($this->dataPath);
    }

    /**
     * @param array<string, MockResponse> $packagist responses indexed by package
     */
    private function tester(array $packagist, ?MockResponse $splits = null): CommandTester
    {
        $splits ??= new MockResponse(self::SPLITS);

        $client = new MockHttpClient(static function (string $method, string $url) use ($packagist, $splits): MockResponse {
            if ('https://raw.githubusercontent.com/symfony/ai/main/splitsh.json' === $url) {
                return $splits;
            }

            if (1 === preg_match('#^https://packagist\.org/packages/(.+)/stats\.json$#', $url, $matches)) {
                return $packagist[$matches[1]];
            }

            throw new \LogicException(\sprintf('Unexpected request to "%s".', $url));
        });

        $application = new Application();
        $application->addCommand(new PlatformBridgesUpdateCommand($client, $this->dataPath));

        return new CommandTester($application->find('app:platform-bridges:update'));
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        return json_decode((string) file_get_contents($this->dataPath), true, flags: \JSON_THROW_ON_ERROR);
    }
}

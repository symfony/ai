<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Mate;

use Mate\SymfonyAiFeaturesTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(SymfonyAiFeaturesTool::class)]
final class SymfonyAiFeaturesToolTest extends TestCase
{
    private const ENV_VAR = 'MATE_TEST_SYMFONY_AI_FEATURES_API_KEY';

    private string $directory;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/symfony-ai-features-tool-'.uniqid();
        $this->fs = new Filesystem();
        $this->fs->mkdir($this->directory.'/config/packages');
        $this->fs->dumpFile($this->directory.'/composer.json', '{"require":{}}');
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->directory);
        unset($_ENV[self::ENV_VAR]);
    }

    public function testHasApiKeyIsFalseWhenEnvVarPlaceholderIsUnset()
    {
        unset($_ENV[self::ENV_VAR]);
        $this->writeAiConfig('%env('.self::ENV_VAR.')%');

        $platform = $this->getOpenAiPlatform();

        $this->assertFalse($platform['has_api_key']);
        $this->assertSame(self::ENV_VAR, $platform['api_key_env_var']);
    }

    public function testHasApiKeyIsFalseWhenEnvVarPlaceholderIsEmpty()
    {
        $_ENV[self::ENV_VAR] = '';
        $this->writeAiConfig('%env('.self::ENV_VAR.')%');

        $platform = $this->getOpenAiPlatform();

        $this->assertFalse($platform['has_api_key']);
    }

    public function testHasApiKeyIsTrueWhenEnvVarPlaceholderResolvesToARealValue()
    {
        $_ENV[self::ENV_VAR] = 'sk-a-real-secret';
        $this->writeAiConfig('%env('.self::ENV_VAR.')%');

        $platform = $this->getOpenAiPlatform();

        $this->assertTrue($platform['has_api_key']);
        $this->assertSame(self::ENV_VAR, $platform['api_key_env_var']);
    }

    public function testHasApiKeyIsFalseWhenLiteralValueIsEmpty()
    {
        $this->writeAiConfig('');

        $platform = $this->getOpenAiPlatform();

        $this->assertFalse($platform['has_api_key']);
        $this->assertArrayNotHasKey('api_key_env_var', $platform);
    }

    public function testHasApiKeyIsTrueWhenLiteralValueIsSet()
    {
        $this->writeAiConfig('sk-a-hardcoded-secret');

        $platform = $this->getOpenAiPlatform();

        $this->assertTrue($platform['has_api_key']);
        $this->assertArrayNotHasKey('api_key_env_var', $platform);
    }

    public function testFeaturesNeverExposeTheApiKeyValue()
    {
        $_ENV[self::ENV_VAR] = 'sk-a-real-secret';
        $this->writeAiConfig('%env('.self::ENV_VAR.')%');

        $tool = new SymfonyAiFeaturesTool($this->directory);
        $features = $tool->getFeatures();

        $encoded = json_encode($features);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('sk-a-real-secret', $encoded);
    }

    /**
     * @return array<string, mixed>
     */
    private function getOpenAiPlatform(): array
    {
        $tool = new SymfonyAiFeaturesTool($this->directory);
        $features = $tool->getFeatures();
        $platforms = $features['platforms'] ?? [];

        $this->assertArrayHasKey(0, $platforms);

        return $platforms[0];
    }

    private function writeAiConfig(string $apiKey): void
    {
        $encodedApiKey = json_encode($apiKey);
        $this->assertIsString($encodedApiKey);

        $this->fs->dumpFile(
            $this->directory.'/config/packages/ai.yaml',
            'ai:'."\n".'    platform:'."\n".'        openai:'."\n".'            api_key: '.$encodedApiKey."\n"
        );
    }
}

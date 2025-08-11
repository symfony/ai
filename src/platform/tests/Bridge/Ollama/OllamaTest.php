<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Ollama;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Capability;

#[CoversClass(Ollama::class)]
#[Small]
final class OllamaTest extends TestCase
{
    #[DataProvider('provideModelsWithToolCallingCapability')]
    public function testModelsWithToolCallingCapability(string $modelName)
    {
        $model = Ollama::create($modelName);

        $this->assertTrue(
            $model->supports(Capability::TOOL_CALLING),
            \sprintf('Model "%s" should support tool calling capability', $modelName)
        );
    }

    #[DataProvider('provideModelsWithoutToolCallingCapability')]
    public function testModelsWithoutToolCallingCapability(string $modelName)
    {
        $model = Ollama::create($modelName);

        $this->assertFalse(
            $model->supports(Capability::TOOL_CALLING),
            \sprintf('Model "%s" should not support tool calling capability', $modelName)
        );
    }

    #[DataProvider('provideModelsWithMultipleInputCapabilities')]
    public function testModelsWithMultipleInputCapabilities(string $modelName)
    {
        $model = Ollama::create($modelName);

        $this->assertTrue(
            $model->supports(Capability::INPUT_MULTIPLE),
            \sprintf('Model "%s" should not support multiple input capabilities', $modelName)
        );
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideModelsWithToolCallingCapability(): iterable
    {
        // Models that match the llama3.x pattern
        yield 'llama3.1' => [Ollama::LLAMA_3_1];
        yield 'llama3.2' => [Ollama::LLAMA_3_2];

        // Models that match the qwen pattern
        yield 'qwen2' => [Ollama::QWEN_2];
        yield 'qwen2.5' => [Ollama::QWEN_2_5];
        yield 'qwen2.5-coder' => [Ollama::QWEN_2_5_CODER];
        yield 'qwen3' => [Ollama::QWEN_3];

        // Models that match the deepseek pattern
        yield 'deepseek-r1' => [Ollama::DEEPSEEK_R_1];

        // Models that match the mistral pattern
        yield 'mistral' => [Ollama::MISTRAL];
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideModelsWithoutToolCallingCapability(): iterable
    {
        // Models that don't match any of the tool calling patterns
        yield 'llama3' => [Ollama::LLAMA_3]; // No version number
        yield 'llama2' => [Ollama::LLAMA_2];
        yield 'gemma' => [Ollama::GEMMA];
        yield 'gemma2' => [Ollama::GEMMA_2];
        yield 'gemma3' => [Ollama::GEMMA_3];
        yield 'gemma3n' => [Ollama::GEMMA_3_N];
        yield 'phi3' => [Ollama::PHI_3];
        yield 'llava' => [Ollama::LLAVA];
        yield 'qwen2.5vl' => [Ollama::QWEN_2_5_VL]; // This has 'vl' suffix which doesn't match the pattern
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideModelsWithMultipleInputCapabilities(): iterable
    {
        yield 'nomic-embed-text' => [Ollama::NOMIC_EMBED_TEXT];
        yield 'bge-m3' => [Ollama::BGE_M3];
        yield 'all-minilm' => [Ollama::ALL_MINILM];
    }
}

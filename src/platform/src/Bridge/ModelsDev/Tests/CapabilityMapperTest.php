<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelsDev\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ModelsDev\CapabilityMapper;
use Symfony\AI\Platform\Capability;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class CapabilityMapperTest extends TestCase
{
    public function testMapCompletionModelWithToolCalling()
    {
        $modelData = [
            'id' => 'gpt-4o',
            'name' => 'GPT-4o',
            'family' => 'gpt',
            'attachment' => true,
            'reasoning' => false,
            'tool_call' => true,
            'structured_output' => true,
            'temperature' => true,
            'modalities' => [
                'input' => ['text', 'image'],
                'output' => ['text'],
            ],
            'cost' => ['input' => 2.5, 'output' => 10],
            'limit' => ['context' => 128000, 'output' => 16384],
        ];

        $capabilities = CapabilityMapper::map($modelData);

        $this->assertContains(Capability::INPUT_MESSAGES, $capabilities);
        $this->assertContains(Capability::OUTPUT_TEXT, $capabilities);
        $this->assertContains(Capability::OUTPUT_STREAMING, $capabilities);
        $this->assertContains(Capability::TOOL_CALLING, $capabilities);
        $this->assertContains(Capability::OUTPUT_STRUCTURED, $capabilities);
        $this->assertContains(Capability::INPUT_IMAGE, $capabilities);
        $this->assertNotContains(Capability::THINKING, $capabilities);
        $this->assertNotContains(Capability::EMBEDDINGS, $capabilities);
    }

    public function testMapReasoningModel()
    {
        $modelData = [
            'id' => 'o3',
            'name' => 'o3',
            'family' => 'o',
            'attachment' => true,
            'reasoning' => true,
            'tool_call' => true,
            'temperature' => false,
            'modalities' => [
                'input' => ['text', 'image'],
                'output' => ['text'],
            ],
            'cost' => ['input' => 10, 'output' => 40],
            'limit' => ['context' => 200000, 'output' => 100000],
        ];

        $capabilities = CapabilityMapper::map($modelData);

        $this->assertContains(Capability::THINKING, $capabilities);
        $this->assertContains(Capability::TOOL_CALLING, $capabilities);
        $this->assertContains(Capability::INPUT_IMAGE, $capabilities);
    }

    public function testMapEmbeddingModelByFamily()
    {
        $modelData = [
            'id' => 'text-embedding-3-small',
            'name' => 'text-embedding-3-small',
            'family' => 'text-embedding',
            'attachment' => false,
            'reasoning' => false,
            'tool_call' => false,
            'temperature' => false,
            'modalities' => [
                'input' => ['text'],
                'output' => ['text'],
            ],
            'cost' => ['input' => 0.02, 'output' => 0],
            'limit' => ['context' => 8191, 'output' => 1536],
        ];

        $capabilities = CapabilityMapper::map($modelData);

        $this->assertContains(Capability::INPUT_TEXT, $capabilities);
        $this->assertContains(Capability::EMBEDDINGS, $capabilities);
        $this->assertNotContains(Capability::INPUT_MESSAGES, $capabilities);
        $this->assertNotContains(Capability::OUTPUT_STREAMING, $capabilities);
    }

    public function testMapEmbeddingModelById()
    {
        $modelData = [
            'id' => 'mistral-embed',
            'name' => 'Mistral Embed',
            'family' => 'mistral-embed',
            'attachment' => false,
            'reasoning' => false,
            'tool_call' => false,
            'temperature' => false,
            'modalities' => [
                'input' => ['text'],
                'output' => ['text'],
            ],
            'cost' => ['input' => 0.1, 'output' => 0],
            'limit' => ['context' => 8192, 'output' => 1024],
        ];

        $this->assertTrue(CapabilityMapper::isEmbeddingModel($modelData));
    }

    public function testIsNotEmbeddingModel()
    {
        $modelData = [
            'id' => 'gpt-4o',
            'family' => 'gpt',
            'attachment' => true,
            'reasoning' => false,
            'tool_call' => true,
            'modalities' => [
                'input' => ['text'],
                'output' => ['text'],
            ],
        ];

        $this->assertFalse(CapabilityMapper::isEmbeddingModel($modelData));
    }

    public function testMapModelWithPdfInput()
    {
        $modelData = [
            'id' => 'claude-sonnet-4-20250514',
            'name' => 'Claude Sonnet 4',
            'family' => 'claude-sonnet',
            'attachment' => true,
            'reasoning' => false,
            'tool_call' => true,
            'temperature' => true,
            'modalities' => [
                'input' => ['text', 'image', 'pdf'],
                'output' => ['text'],
            ],
            'cost' => ['input' => 3, 'output' => 15],
            'limit' => ['context' => 200000, 'output' => 8192],
        ];

        $capabilities = CapabilityMapper::map($modelData);

        $this->assertContains(Capability::INPUT_PDF, $capabilities);
        $this->assertContains(Capability::INPUT_IMAGE, $capabilities);
    }

    public function testMapModelWithAudioModalities()
    {
        $modelData = [
            'id' => 'some-audio-model',
            'name' => 'Audio Model',
            'family' => 'audio',
            'attachment' => true,
            'reasoning' => false,
            'tool_call' => false,
            'temperature' => true,
            'modalities' => [
                'input' => ['text', 'audio'],
                'output' => ['text', 'audio'],
            ],
            'cost' => ['input' => 1, 'output' => 2],
            'limit' => ['context' => 8192, 'output' => 4096],
        ];

        $capabilities = CapabilityMapper::map($modelData);

        $this->assertContains(Capability::INPUT_AUDIO, $capabilities);
        $this->assertContains(Capability::OUTPUT_AUDIO, $capabilities);
    }

    public function testMapModelWithoutFamily()
    {
        $modelData = [
            'id' => 'some-model',
            'name' => 'Some Model',
            'attachment' => false,
            'reasoning' => false,
            'tool_call' => true,
            'temperature' => true,
            'modalities' => [
                'input' => ['text'],
                'output' => ['text'],
            ],
            'cost' => ['input' => 1, 'output' => 2],
            'limit' => ['context' => 8192, 'output' => 4096],
        ];

        $this->assertFalse(CapabilityMapper::isEmbeddingModel($modelData));

        $capabilities = CapabilityMapper::map($modelData);

        $this->assertContains(Capability::INPUT_MESSAGES, $capabilities);
        $this->assertContains(Capability::TOOL_CALLING, $capabilities);
    }

    public function testMapModelWithImageOutput()
    {
        $modelData = [
            'id' => 'dall-e-3',
            'name' => 'DALL-E 3',
            'family' => 'dall-e',
            'attachment' => false,
            'reasoning' => false,
            'tool_call' => false,
            'temperature' => false,
            'modalities' => [
                'input' => ['text'],
                'output' => ['image'],
            ],
            'cost' => ['input' => 0, 'output' => 0],
            'limit' => ['context' => 4096, 'output' => 1],
        ];

        $capabilities = CapabilityMapper::map($modelData);

        $this->assertContains(Capability::OUTPUT_IMAGE, $capabilities);
    }
}

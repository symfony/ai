<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * @author Joshua Behrens <code@joshua-behrens.de>
 */
class Ollama extends Model
{
    public const DEEPSEEK_R_1 = 'deepseek-r1';
    public const GEMMA_3_N = 'gemma3n';
    public const GEMMA_3 = 'gemma3';
    public const QWEN_3 = 'qwen3';
    public const QWEN_2_5_VL = 'qwen2.5vl';
    public const LLAMA_3_1 = 'llama3.1';
    public const LLAMA_3_2 = 'llama3.2';
    public const MISTRAL = 'mistral';
    public const QWEN_2_5 = 'qwen2.5';
    public const LLAMA_3 = 'llama3';
    public const LLAVA = 'llava';
    public const PHI_3 = 'phi3';
    public const GEMMA_2 = 'gemma2';
    public const QWEN_2_5_CODER = 'qwen2.5-coder';
    public const GEMMA = 'gemma';
    public const QWEN = 'qwen';
    public const QWEN_2 = 'qwen2';
    public const LLAMA_2 = 'llama2';
    public const NOMIC_EMBED_TEXT = 'nomic-embed-text';
    public const BGE_M3 = 'bge-m3';
    public const ALL_MINILM = 'all-minilm';

    private const TOOL_PATTERNS = [
        '/./' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STRUCTURED,
        ],
        '/^llama\D*3(\D*\d+)/' => [
            Capability::TOOL_CALLING,
        ],
        '/^qwen\d(\.\d)?(-coder)?$/' => [
            Capability::TOOL_CALLING,
        ],
        '/^(deepseek|mistral)/' => [
            Capability::TOOL_CALLING,
        ],
        '/^(nomic|bge|all-minilm).*/' => [
            Capability::INPUT_MULTIPLE,
        ],
    ];

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(string $name, array $options = [])
    {
        $capabilities = [];

        foreach (self::TOOL_PATTERNS as $pattern => $possibleCapabilities) {
            if (1 === preg_match($pattern, $name)) {
                foreach ($possibleCapabilities as $capability) {
                    $capabilities[] = $capability;
                }
            }
        }

        parent::__construct($name, $capabilities, $options);
    }
}

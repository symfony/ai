<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\DockerModelRunner;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
class Completions extends Model
{
    public const GEMMA_3_N = 'ai/gemma3n';
    public const GEMMA_3 = 'ai/gemma3';
    public const QWEN_2_5 = 'ai/qwen2.5';
    public const QWEN_3 = 'ai/qwen3';
    public const QWEN_3_CODER = 'ai/qwen3-coder';
    public const LLAMA_3_1 = 'ai/llama3.1';
    public const LLAMA_3_2 = 'ai/llama3.2';
    public const LLAMA_3_3 = 'ai/llama3.3';
    public const MISTRAL = 'ai/mistral';
    public const MISTRAL_NEMO = 'ai/mistral-nemo';
    public const PHI_4 = 'ai/phi4';
    public const DEEPSEEK_R_1 = 'ai/deepseek-r1-distill-llama';
    public const SEED_OSS = 'ai/seed-oss';
    public const GPT_OSS = 'ai/gpt-oss';
    public const SMOLLM_2 = 'ai/smollm2';
    public const SMOLLM_3 = 'ai/smollm3';

    public function __construct(string $name, array $options = [])
    {
        parent::__construct($name, Capability::cases(), $options);
    }
}

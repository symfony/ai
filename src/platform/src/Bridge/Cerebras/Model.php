<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cerebras;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model as BaseModel;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class Model extends BaseModel
{
    public const LLAMA_4_SCOUT_17B_16E_INSTRUCT = 'llama-4-scout-17b-16e-instruct';
    public const LLAMA3_1_8B = 'llama3.1-8b';
    public const LLAMA_3_3_70B = 'llama-3.3-70b';
    public const LLAMA_4_MAVERICK_17B_128E_INSTRUCT = 'llama-4-maverick-17b-128e-instruct';
    public const QWEN_3_32B = 'qwen-3-32b';
    public const QWEN_3_235B_A22B_INSTRUCT_2507 = 'qwen-3-235b-a22b-instruct-2507';
    public const QWEN_3_235B_A22B_THINKING_2507 = 'qwen-3-235b-a22b-thinking-2507';
    public const QWEN_3_CODER_480B = 'qwen-3-coder-480b';
    public const GPT_OSS_120B = 'gpt-oss-120b';

    public const CAPABILITIES = [
        Capability::INPUT_MESSAGES,
        Capability::OUTPUT_TEXT,
        Capability::OUTPUT_STREAMING,
    ];

    /**
     * @see https://inference-docs.cerebras.ai/api-reference/chat-completions for details like options
     */
    public function __construct(
        string $name,
        array $capabilities = self::CAPABILITIES,
        array $options = [],
    ) {
        parent::__construct($name, $capabilities, $options);
    }
}
